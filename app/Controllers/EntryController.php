<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use RuntimeException;
use SimpleVault\Core\Request;
use SimpleVault\Core\Response;
use SimpleVault\Core\Session;
use SimpleVault\Core\Uuid;
use SimpleVault\Core\Validator;
use SimpleVault\Crypto\CryptoService;
use SimpleVault\Markdown\MarkdownPreviewService;
use SimpleVault\Models\Entry;
use SimpleVault\Repositories\AuditRepository;
use SimpleVault\Repositories\EntryRepository;
use SimpleVault\Support\PasswordGenerator;

/**
 * CRUD for encrypted password entries. All routes require an unlocked vault.
 */
final class EntryController extends Controller
{
    private CryptoService $crypto;

    public function __construct(
        private EntryRepository $entries = new EntryRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
        $this->crypto = new CryptoService();
    }

    public function index(Request $request): Response
    {
        $includeArchived = $request->boolean('archived');
        $rows = $this->entries->allForUser($this->userId(), $includeArchived);
        $key = $this->vaultKey();

        $entries = [];
        foreach ($rows as $row) {
            try {
                $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $key);
            } catch (RuntimeException) {
                continue; // skip undecryptable rows rather than crash
            }
            $entries[] = Entry::fromRow($row, $payload);
        }

        return $this->view('entries/index', [
            'entries' => $entries,
            'includeArchived' => $includeArchived,
        ], 'Passwords');
    }

    public function create(Request $request): Response
    {
        return $this->view('entries/create', [
            'errors' => [],
            'old' => [],
            'suggestedPassword' => (new PasswordGenerator())->generate(),
        ], 'New Password');
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->body)
            ->required('title', 'Title')
            ->maxLength('title', 200, 'Title');

        if ($validator->fails()) {
            return $this->view('entries/create', [
                'errors' => $validator->errors(),
                'old' => $request->body,
                'suggestedPassword' => $request->string('password'),
            ], 'New Password');
        }

        $payload = $this->payloadFromRequest($request);
        $encrypted = $this->crypto->encryptJson($payload, $this->vaultKey());

        $uuid = Uuid::v4();
        $this->entries->create($this->userId(), $uuid, $encrypted['ciphertext'], $encrypted['nonce'], $request->boolean('favorite'));
        $this->audit->log($this->userId(), 'entry_created', $request->ip, $request->userAgent);

        $this->flash('success', 'Password entry saved.');
        return $this->redirect('/entries/' . $uuid);
    }

    public function show(Request $request, array $params): Response
    {
        $entry = $this->findEntryOr404($params['id']);
        if ($entry instanceof Response) {
            return $entry;
        }

        return $this->view('entries/show', [
            'entry' => $entry,
            'markdown' => new MarkdownPreviewService(),
        ], $entry->title());
    }

    public function edit(Request $request, array $params): Response
    {
        $entry = $this->findEntryOr404($params['id']);
        if ($entry instanceof Response) {
            return $entry;
        }

        return $this->view('entries/edit', [
            'entry' => $entry,
            'errors' => [],
            'old' => $entry->payload + ['favorite' => $entry->favorite],
        ], 'Edit ' . $entry->title());
    }

    public function update(Request $request, array $params): Response
    {
        $row = $this->entries->findForUser($this->userId(), (string) $params['id']);
        if ($row === null) {
            return $this->notFound();
        }

        $oldPayload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $this->vaultKey());
        $previousById = $this->fieldsById($oldPayload);

        $validator = Validator::make($request->body)
            ->required('title', 'Title')
            ->maxLength('title', 200, 'Title');

        if ($validator->fails()) {
            $payload = $this->payloadFromRequest($request, $previousById);
            return $this->view('entries/edit', [
                'entry' => Entry::fromRow($row, $payload),
                'errors' => $validator->errors(),
                'old' => $request->body,
            ], 'Edit Password');
        }

        $payload = $this->payloadFromRequest($request, $previousById);
        $encrypted = $this->crypto->encryptJson($payload, $this->vaultKey());

        $this->entries->update($this->userId(), (string) $params['id'], $encrypted['ciphertext'], $encrypted['nonce'], $request->boolean('favorite'));
        $this->audit->log($this->userId(), 'entry_updated', $request->ip, $request->userAgent);

        $this->flash('success', 'Password entry updated.');
        return $this->redirect('/entries/' . $params['id']);
    }

    public function archive(Request $request, array $params): Response
    {
        if (!$this->entries->existsByUuid($this->userId(), (string) $params['id'])) {
            return $this->notFound();
        }
        $archived = !$request->boolean('unarchive');
        $this->entries->setArchived($this->userId(), (string) $params['id'], $archived);
        $this->flash('info', $archived ? 'Entry archived.' : 'Entry restored.');

        return $this->redirect('/entries');
    }

    public function destroy(Request $request, array $params): Response
    {
        if (!$this->entries->existsByUuid($this->userId(), (string) $params['id'])) {
            return $this->notFound();
        }
        $this->entries->delete($this->userId(), (string) $params['id']);
        $this->audit->log($this->userId(), 'entry_deleted', $request->ip, $request->userAgent);
        $this->flash('info', 'Entry permanently deleted.');

        return $this->redirect('/entries');
    }

    /**
     * Duplicate an entry: decrypt the original, store a copy with a new UUID.
     */
    public function duplicate(Request $request, array $params): Response
    {
        $uuid = (string) $params['id'];
        if (!Uuid::isValid($uuid)) {
            return $this->notFound();
        }
        $row = $this->entries->findForUser($this->userId(), $uuid);
        if ($row === null) {
            return $this->notFound();
        }

        $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $this->vaultKey());
        $payload['title'] = mb_substr((string) ($payload['title'] ?? 'Untitled') . ' (copy)', 0, 200);
        $payload['fields'] = $this->freshFields($payload['fields'] ?? []);

        $encrypted = $this->crypto->encryptJson($payload, $this->vaultKey());
        $newUuid = Uuid::v4();
        $this->entries->create($this->userId(), $newUuid, $encrypted['ciphertext'], $encrypted['nonce'], (bool) $row['favorite']);
        $this->audit->log($this->userId(), 'entry_duplicated', $request->ip, $request->userAgent);

        $this->flash('success', 'Entry duplicated. You can edit the copy now.');
        return $this->redirect('/entries/' . $newUuid . '/edit');
    }

    /**
     * Return a single entry's decrypted password as JSON, for the quick-copy
     * button on the list. Requires auth + an unlocked vault + CSRF, and the
     * entry must belong to the current user. The plaintext is never placed in
     * the list HTML; it is fetched on demand and not cached.
     */
    public function copyPassword(Request $request, array $params): Response
    {
        $uuid = (string) $params['id'];
        if (!Uuid::isValid($uuid)) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $row = $this->entries->findForUser($this->userId(), $uuid);
        if ($row === null) {
            return Response::json(['error' => 'not_found'], 404);
        }

        $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $this->vaultKey());
        $this->audit->log($this->userId(), 'entry_password_copied', $request->ip, $request->userAgent);

        return Response::json(['value' => (string) ($payload['password'] ?? '')])
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Apply an action (archive/unarchive/delete) to many selected entries.
     */
    public function bulk(Request $request): Response
    {
        $action = $request->string('action');
        $uuids = $request->input('uuids', []);

        if (!is_array($uuids) || $uuids === []) {
            $this->flash('warning', 'No entries were selected.');
            return $this->redirect('/entries');
        }
        if (!in_array($action, ['archive', 'unarchive', 'delete'], true)) {
            $this->flash('danger', 'Unknown bulk action.');
            return $this->redirect('/entries');
        }

        $count = 0;
        foreach ($uuids as $uuid) {
            $uuid = (string) $uuid;
            if (!Uuid::isValid($uuid) || !$this->entries->existsByUuid($this->userId(), $uuid)) {
                continue;
            }
            if ($action === 'delete') {
                $this->entries->delete($this->userId(), $uuid);
            } else {
                $this->entries->setArchived($this->userId(), $uuid, $action === 'archive');
            }
            $count++;
        }

        $this->audit->log($this->userId(), 'entries_bulk_' . $action, $request->ip, $request->userAgent);
        $this->flash('info', $count . ' ' . ($count === 1 ? 'entry' : 'entries') . ' updated.');

        return $this->redirect('/entries');
    }

    // --- Helpers -----------------------------------------------------------

    /**
     * @param array<string,array{id:string,name:string,value:string,secret:bool,observation:string,createdAt:?string,updatedAt:?string}> $previousById
     *        Existing custom fields keyed by id, used to preserve per-field
     *        timestamps across an edit.
     * @return array<string,mixed>
     */
    private function payloadFromRequest(Request $request, array $previousById = []): array
    {
        return [
            'title' => trim($request->string('title')),
            'url' => trim($request->string('url')),
            'username' => trim($request->string('username')),
            'password' => $request->string('password'),
            'notes' => $request->string('notes'),
            'body' => $request->string('body'),
            'client' => trim($request->string('client')),
            'project' => trim($request->string('project')),
            'tags' => $this->parseTags($request->string('tags')),
            'fields' => $this->parseFields($request->input('fields', []), $previousById),
        ];
    }

    /**
     * Normalize the dynamic custom-field rows posted by the entry form. Rows
     * with no name, value and observation are dropped (this also discards the
     * inert template row). Each field carries a stable id and createdAt; the
     * updatedAt stamp only moves when the field's contents actually change,
     * matched against $previousById by id.
     *
     * @param array<string,array{id:string,name:string,value:string,secret:bool,observation:string,createdAt:?string,updatedAt:?string}> $previousById
     * @return list<array{id:string,name:string,value:string,secret:bool,observation:string,createdAt:string,updatedAt:string}>
     */
    private function parseFields(mixed $raw, array $previousById = []): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $now = now_iso();
        $fields = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = mb_substr(trim((string) ($item['name'] ?? '')), 0, 100);
            $value = (string) ($item['value'] ?? '');
            $observation = mb_substr(trim((string) ($item['observation'] ?? '')), 0, 500);
            if ($name === '' && $value === '' && $observation === '') {
                continue;
            }
            $secret = filter_var($item['secret'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $id = (string) ($item['id'] ?? '');
            $previous = ($id !== '' && isset($previousById[$id])) ? $previousById[$id] : null;
            if ($previous !== null) {
                $changed = $previous['name'] !== $name
                    || $previous['value'] !== $value
                    || $previous['secret'] !== $secret
                    || $previous['observation'] !== $observation;
                $createdAt = $previous['createdAt'] ?? $now;
                $updatedAt = $changed ? $now : ($previous['updatedAt'] ?? $now);
            } else {
                $id = Uuid::v4();
                $createdAt = $now;
                $updatedAt = $now;
            }

            $fields[] = [
                'id' => $id,
                'name' => $name,
                'value' => $value,
                'secret' => $secret,
                'observation' => $observation,
                'createdAt' => $createdAt,
                'updatedAt' => $updatedAt,
            ];
        }

        return $fields;
    }

    /**
     * Re-stamp custom fields for a duplicated entry: a fresh id and timestamps
     * so the copy does not share field identity with the original.
     *
     * @return list<array{id:string,name:string,value:string,secret:bool,observation:string,createdAt:string,updatedAt:string}>
     */
    private function freshFields(mixed $fields): array
    {
        $now = now_iso();
        $out = [];
        foreach (Entry::normalizeFields($fields) as $field) {
            $field['id'] = Uuid::v4();
            $field['createdAt'] = $now;
            $field['updatedAt'] = $now;
            $out[] = $field;
        }

        return $out;
    }

    /**
     * Index an entry's existing custom fields by id, for timestamp diffing.
     *
     * @param array<string,mixed> $payload
     * @return array<string,array{id:string,name:string,value:string,secret:bool,observation:string,createdAt:?string,updatedAt:?string}>
     */
    private function fieldsById(array $payload): array
    {
        $map = [];
        foreach (Entry::normalizeFields($payload['fields'] ?? []) as $field) {
            if ($field['id'] !== '') {
                $map[$field['id']] = $field;
            }
        }

        return $map;
    }

    /** @return array<int,string> */
    private function parseTags(string $raw): array
    {
        $tags = array_filter(array_map('trim', explode(',', $raw)), static fn ($t) => $t !== '');

        return array_values(array_unique(array_map(static fn ($t) => mb_substr($t, 0, 50), $tags)));
    }

    private function findEntryOr404(string $uuid): Entry|Response
    {
        if (!Uuid::isValid($uuid)) {
            return $this->notFound();
        }
        $row = $this->entries->findForUser($this->userId(), $uuid);
        if ($row === null) {
            return $this->notFound();
        }

        $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $this->vaultKey());

        return Entry::fromRow($row, $payload);
    }

    private function vaultKey(): string
    {
        $key = Session::vaultKey();
        if ($key === null) {
            throw new RuntimeException('Vault is locked.');
        }

        return $key;
    }

    private function notFound(): Response
    {
        return $this->view('errors/404', [], 'Not Found');
    }
}
