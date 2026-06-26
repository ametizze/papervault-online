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

        return $this->view('entries/show', ['entry' => $entry], $entry->title());
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

        $validator = Validator::make($request->body)
            ->required('title', 'Title')
            ->maxLength('title', 200, 'Title');

        if ($validator->fails()) {
            $payload = $this->payloadFromRequest($request);
            return $this->view('entries/edit', [
                'entry' => Entry::fromRow($row, $payload),
                'errors' => $validator->errors(),
                'old' => $request->body,
            ], 'Edit Password');
        }

        $payload = $this->payloadFromRequest($request);
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

    // --- Helpers -----------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    private function payloadFromRequest(Request $request): array
    {
        return [
            'title' => trim($request->string('title')),
            'url' => trim($request->string('url')),
            'username' => trim($request->string('username')),
            'password' => $request->string('password'),
            'notes' => $request->string('notes'),
            'client' => trim($request->string('client')),
            'project' => trim($request->string('project')),
            'tags' => $this->parseTags($request->string('tags')),
        ];
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
