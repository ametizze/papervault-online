<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use RuntimeException;
use SimpleVault\Core\App;
use SimpleVault\Core\Request;
use SimpleVault\Core\Response;
use SimpleVault\Core\Session;
use SimpleVault\Core\Uuid;
use SimpleVault\Core\Validator;
use SimpleVault\Crypto\CryptoService;
use SimpleVault\Markdown\MarkdownExportService;
use SimpleVault\Markdown\MarkdownPreviewService;
use SimpleVault\Models\Note;
use SimpleVault\Repositories\AuditRepository;
use SimpleVault\Repositories\NoteRepository;

/**
 * CRUD for encrypted Markdown notes. All routes require an unlocked vault.
 */
final class NoteController extends Controller
{
    private CryptoService $crypto;

    public function __construct(
        private NoteRepository $notes = new NoteRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
        $this->crypto = new CryptoService();
    }

    public function index(Request $request): Response
    {
        $includeArchived = $request->boolean('archived');
        $rows = $this->notes->allForUser($this->userId(), $includeArchived);
        $key = $this->vaultKey();

        $notes = [];
        foreach ($rows as $row) {
            try {
                $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $key);
            } catch (RuntimeException) {
                continue;
            }
            $notes[] = Note::fromRow($row, $payload);
        }

        return $this->view('notes/index', [
            'notes' => $notes,
            'includeArchived' => $includeArchived,
        ], 'Notes');
    }

    public function create(Request $request): Response
    {
        return $this->view('notes/create', ['errors' => [], 'old' => []], 'New Note');
    }

    public function store(Request $request): Response
    {
        $validator = $this->validate($request);
        if ($validator->fails()) {
            return $this->view('notes/create', [
                'errors' => $validator->errors(),
                'old' => $request->body,
            ], 'New Note');
        }

        $payload = $this->payloadFromRequest($request);
        $encrypted = $this->crypto->encryptJson($payload, $this->vaultKey());

        $uuid = Uuid::v4();
        $this->notes->create($this->userId(), $uuid, $encrypted['ciphertext'], $encrypted['nonce'], $request->boolean('favorite'));
        $this->audit->log($this->userId(), 'note_created', $request->ip, $request->userAgent);

        $this->flash('success', 'Note saved.');
        return $this->redirect('/notes/' . $uuid);
    }

    public function show(Request $request, array $params): Response
    {
        $note = $this->findNoteOr404((string) $params['id']);
        if ($note instanceof Response) {
            return $note;
        }

        $renderedHtml = (new MarkdownPreviewService())->toHtml($note->markdown());

        return $this->view('notes/show', [
            'note' => $note,
            'renderedHtml' => $renderedHtml,
        ], $note->title());
    }

    public function edit(Request $request, array $params): Response
    {
        $note = $this->findNoteOr404((string) $params['id']);
        if ($note instanceof Response) {
            return $note;
        }

        return $this->view('notes/edit', [
            'note' => $note,
            'errors' => [],
            'old' => $note->payload + ['favorite' => $note->favorite],
        ], 'Edit ' . $note->title());
    }

    public function update(Request $request, array $params): Response
    {
        $row = $this->notes->findForUser($this->userId(), (string) $params['id']);
        if ($row === null) {
            return $this->notFound();
        }

        $validator = $this->validate($request);
        if ($validator->fails()) {
            return $this->view('notes/edit', [
                'note' => Note::fromRow($row, $this->payloadFromRequest($request)),
                'errors' => $validator->errors(),
                'old' => $request->body,
            ], 'Edit Note');
        }

        $payload = $this->payloadFromRequest($request);
        $encrypted = $this->crypto->encryptJson($payload, $this->vaultKey());

        $this->notes->update($this->userId(), (string) $params['id'], $encrypted['ciphertext'], $encrypted['nonce'], $request->boolean('favorite'));
        $this->audit->log($this->userId(), 'note_updated', $request->ip, $request->userAgent);

        $this->flash('success', 'Note updated.');
        return $this->redirect('/notes/' . $params['id']);
    }

    public function archive(Request $request, array $params): Response
    {
        if (!$this->notes->existsByUuid($this->userId(), (string) $params['id'])) {
            return $this->notFound();
        }
        $archived = !$request->boolean('unarchive');
        $this->notes->setArchived($this->userId(), (string) $params['id'], $archived);
        $this->flash('info', $archived ? 'Note archived.' : 'Note restored.');

        return $this->redirect('/notes');
    }

    public function destroy(Request $request, array $params): Response
    {
        if (!$this->notes->existsByUuid($this->userId(), (string) $params['id'])) {
            return $this->notFound();
        }
        $this->notes->delete($this->userId(), (string) $params['id']);
        $this->audit->log($this->userId(), 'note_deleted', $request->ip, $request->userAgent);
        $this->flash('info', 'Note permanently deleted.');

        return $this->redirect('/notes');
    }

    /**
     * Duplicate a note: decrypt the original, store a copy with a new UUID.
     */
    public function duplicate(Request $request, array $params): Response
    {
        $uuid = (string) $params['id'];
        if (!Uuid::isValid($uuid)) {
            return $this->notFound();
        }
        $row = $this->notes->findForUser($this->userId(), $uuid);
        if ($row === null) {
            return $this->notFound();
        }

        $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $this->vaultKey());
        $payload['title'] = mb_substr((string) ($payload['title'] ?? 'Untitled') . ' (copy)', 0, 200);

        $encrypted = $this->crypto->encryptJson($payload, $this->vaultKey());
        $newUuid = Uuid::v4();
        $this->notes->create($this->userId(), $newUuid, $encrypted['ciphertext'], $encrypted['nonce'], (bool) $row['favorite']);
        $this->audit->log($this->userId(), 'note_duplicated', $request->ip, $request->userAgent);

        $this->flash('success', 'Note duplicated. You can edit the copy now.');
        return $this->redirect('/notes/' . $newUuid . '/edit');
    }

    /**
     * Return a single note's decrypted Markdown as JSON, for the quick-copy
     * button on the list. Requires auth + an unlocked vault + CSRF, and the
     * note must belong to the current user. The plaintext is never placed in
     * the list HTML; it is fetched on demand and not cached.
     */
    public function copyMarkdown(Request $request, array $params): Response
    {
        $uuid = (string) $params['id'];
        if (!Uuid::isValid($uuid)) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $row = $this->notes->findForUser($this->userId(), $uuid);
        if ($row === null) {
            return Response::json(['error' => 'not_found'], 404);
        }

        $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $this->vaultKey());
        $this->audit->log($this->userId(), 'note_markdown_copied', $request->ip, $request->userAgent);

        return Response::json(['value' => (string) ($payload['markdown'] ?? '')])
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Apply an action (archive/unarchive/delete) to many selected notes.
     */
    public function bulk(Request $request): Response
    {
        $action = $request->string('action');
        $uuids = $request->input('uuids', []);

        if (!is_array($uuids) || $uuids === []) {
            $this->flash('warning', 'No notes were selected.');
            return $this->redirect('/notes');
        }
        if (!in_array($action, ['archive', 'unarchive', 'delete'], true)) {
            $this->flash('danger', 'Unknown bulk action.');
            return $this->redirect('/notes');
        }

        $count = 0;
        foreach ($uuids as $uuid) {
            $uuid = (string) $uuid;
            if (!Uuid::isValid($uuid) || !$this->notes->existsByUuid($this->userId(), $uuid)) {
                continue;
            }
            if ($action === 'delete') {
                $this->notes->delete($this->userId(), $uuid);
            } else {
                $this->notes->setArchived($this->userId(), $uuid, $action === 'archive');
            }
            $count++;
        }

        $this->audit->log($this->userId(), 'notes_bulk_' . $action, $request->ip, $request->userAgent);
        $this->flash('info', $count . ' ' . ($count === 1 ? 'note' : 'notes') . ' updated.');

        return $this->redirect('/notes');
    }

    /**
     * Export a single note as a plaintext Markdown file download.
     */
    public function exportMarkdown(Request $request, array $params): Response
    {
        $note = $this->findNoteOr404((string) $params['id']);
        if ($note instanceof Response) {
            return $note;
        }

        $exporter = new MarkdownExportService();
        $markdown = $exporter->noteToMarkdown($note);
        $filename = $exporter->filenameFor($note);

        $this->audit->log($this->userId(), 'note_exported_markdown', $request->ip, $request->userAgent);

        return Response::download($markdown, $filename, 'text/markdown; charset=utf-8');
    }

    // --- Helpers -----------------------------------------------------------

    private function validate(Request $request): Validator
    {
        $maxBytes = (int) App::config('max_markdown_note_kb', 512) * 1024;

        return Validator::make($request->body)
            ->required('title', 'Title')
            ->maxLength('title', 200, 'Title')
            ->maxLength('markdown', $maxBytes, 'Markdown content');
    }

    /**
     * @return array<string,mixed>
     */
    private function payloadFromRequest(Request $request): array
    {
        return [
            'title' => trim($request->string('title')),
            'client' => trim($request->string('client')),
            'project' => trim($request->string('project')),
            'markdown' => $request->string('markdown'),
            'tags' => $this->parseTags($request->string('tags')),
        ];
    }

    /** @return array<int,string> */
    private function parseTags(string $raw): array
    {
        $tags = array_filter(array_map('trim', explode(',', $raw)), static fn ($t) => $t !== '');

        return array_values(array_unique(array_map(static fn ($t) => mb_substr($t, 0, 50), $tags)));
    }

    private function findNoteOr404(string $uuid): Note|Response
    {
        if (!Uuid::isValid($uuid)) {
            return $this->notFound();
        }
        $row = $this->notes->findForUser($this->userId(), $uuid);
        if ($row === null) {
            return $this->notFound();
        }

        $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $this->vaultKey());

        return Note::fromRow($row, $payload);
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
