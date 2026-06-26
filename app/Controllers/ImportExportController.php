<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use JsonException;
use RuntimeException;
use SimpleVault\Core\App;
use SimpleVault\Core\Request;
use SimpleVault\Core\Response;
use SimpleVault\Core\Session;
use SimpleVault\Core\Uuid;
use SimpleVault\Crypto\CryptoService;
use SimpleVault\Markdown\MarkdownExportService;
use SimpleVault\Markdown\MarkdownImportService;
use SimpleVault\Models\Note;
use SimpleVault\Repositories\AuditRepository;
use SimpleVault\Repositories\EntryRepository;
use SimpleVault\Repositories\NoteRepository;
use SimpleVault\Repositories\VaultRepository;
use SimpleVault\Support\BackupService;

/**
 * Encrypted full-vault backup export/import and Markdown notes export/import.
 */
final class ImportExportController extends Controller
{
    private CryptoService $crypto;

    public function __construct(
        private NoteRepository $notes = new NoteRepository(),
        private EntryRepository $entries = new EntryRepository(),
        private VaultRepository $vaults = new VaultRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
        $this->crypto = new CryptoService();
    }

    // --- Pages -------------------------------------------------------------

    public function index(Request $request): Response
    {
        return $this->view('import-export/index', [], 'Import / Export');
    }

    public function notesExportPage(Request $request): Response
    {
        return $this->view('notes/export', [
            'notes' => $this->loadNotes(),
        ], 'Export Notes');
    }

    public function notesImportPage(Request $request): Response
    {
        return $this->view('notes/import', [], 'Import Notes');
    }

    // --- Encrypted full-vault backup --------------------------------------

    public function exportBackup(Request $request): Response
    {
        $backup = new BackupService();
        $data = $backup->export($this->userId());
        $json = $backup->toJson($data);

        $this->audit->log($this->userId(), 'backup_exported', $request->ip, $request->userAgent);

        $filename = 'simplevault-backup-' . date('Y-m-d-His') . '.json';
        return Response::download($json, $filename, 'application/json');
    }

    public function importBackup(Request $request): Response
    {
        $mode = $request->string('mode', 'merge'); // merge | replace

        try {
            $backup = $this->readBackupUpload($request);
        } catch (RuntimeException $e) {
            $this->flash('danger', $e->getMessage());
            return $this->redirect('/import');
        }

        $backupService = new BackupService();
        try {
            $backupService->validate($backup);
        } catch (RuntimeException $e) {
            $this->flash('danger', 'Invalid backup: ' . $e->getMessage());
            return $this->redirect('/import');
        }

        if ($mode === 'replace') {
            return $this->importReplace($request, $backup);
        }

        return $this->importMerge($request, $backup);
    }

    // --- Markdown notes export --------------------------------------------

    public function exportNotesMarkdown(Request $request): Response
    {
        $scope = $request->string('scope', 'all'); // all | client | project | single
        $exporter = new MarkdownExportService();
        $notes = $this->loadNotes();

        if ($scope === 'client') {
            $client = trim($request->string('client'));
            $notes = array_values(array_filter($notes, static fn (Note $n) => strcasecmp($n->client(), $client) === 0));
        } elseif ($scope === 'project') {
            $project = trim($request->string('project'));
            $notes = array_values(array_filter($notes, static fn (Note $n) => strcasecmp($n->project(), $project) === 0));
        } elseif ($scope === 'single') {
            $uuid = $request->string('uuid');
            $notes = array_values(array_filter($notes, static fn (Note $n) => $n->uuid === $uuid));
        }

        if ($notes === []) {
            $this->flash('warning', 'No notes matched your selection.');
            return $this->redirect('/notes/export');
        }

        $this->audit->log($this->userId(), 'notes_exported_markdown', $request->ip, $request->userAgent);

        // Single note -> .md, otherwise -> .zip.
        if (count($notes) === 1) {
            $note = $notes[0];
            return Response::download(
                $exporter->noteToMarkdown($note),
                $exporter->filenameFor($note),
                'text/markdown; charset=utf-8'
            );
        }

        $zip = $exporter->notesToZip($notes);
        return Response::download($zip, 'simplevault-notes-' . date('Y-m-d') . '.zip', 'application/zip');
    }

    // --- Markdown notes import --------------------------------------------

    public function importNotesMarkdown(Request $request): Response
    {
        $importer = new MarkdownImportService();
        $maxFiles = (int) App::config('max_import_files', 100);
        $maxBytes = (int) App::config('max_upload_mb', 10) * 1024 * 1024;

        $documents = [];
        try {
            $documents = $this->collectMarkdownUploads($request, $importer, $maxFiles, $maxBytes);
        } catch (RuntimeException $e) {
            $this->flash('danger', $e->getMessage());
            return $this->redirect('/notes/import');
        }

        if ($documents === []) {
            $this->flash('warning', 'No Markdown files were provided.');
            return $this->redirect('/notes/import');
        }

        $key = $this->requireVaultKey();
        $imported = 0;
        foreach ($documents as $doc) {
            try {
                $payload = $importer->parse($doc['content'], $doc['name']);
            } catch (RuntimeException) {
                continue; // skip oversized / invalid file
            }
            $encrypted = $this->crypto->encryptJson($payload, $key);
            $this->notes->create($this->userId(), Uuid::v4(), $encrypted['ciphertext'], $encrypted['nonce'], false);
            $imported++;
        }

        $this->audit->log($this->userId(), 'notes_imported_markdown', $request->ip, $request->userAgent);
        $this->flash('success', "Imported $imported note(s).");

        return $this->redirect('/notes');
    }

    // --- Internal: import modes -------------------------------------------

    private function importReplace(Request $request, array $backup): Response
    {
        // Always create an automatic encrypted backup before destructive ops.
        $this->autoBackup();

        $payload = $backup['payload'];

        $wrapped = [
            'salt' => (string) $payload['salt'],
            'encrypted_vault_key' => (string) $payload['encrypted_vault_key'],
            'vault_key_nonce' => (string) $payload['vault_key_nonce'],
            'kdf_ops_limit' => (int) $payload['kdf_ops_limit'],
            'kdf_mem_limit' => (int) $payload['kdf_mem_limit'],
        ];

        $this->vaults->updateWrappedKey($this->userId(), $wrapped, (bool) ($payload['key_file_required'] ?? 0));
        $this->entries->deleteAllForUser($this->userId());
        $this->notes->deleteAllForUser($this->userId());

        foreach (($payload['entries'] ?? []) as $row) {
            $this->entries->insertRaw($this->userId(), $row);
        }
        foreach (($payload['notes'] ?? []) as $row) {
            $this->notes->insertRaw($this->userId(), $row);
        }

        // The new data is encrypted under the backup's Master Password.
        Session::lockVault();
        $this->audit->log($this->userId(), 'backup_imported_replace', $request->ip, $request->userAgent);

        $this->flash('success', 'Vault replaced from backup. Unlock using the Master Password (and Key File) from that backup.');
        return $this->redirect('/vault/unlock');
    }

    private function importMerge(Request $request, array $backup): Response
    {
        $currentKey = $this->requireVaultKey();
        $payload = $backup['payload'];

        // Unwrap the BACKUP vault key with the supplied backup master password.
        $backupMaster = $request->string('backup_master_password');
        if (trim($backupMaster) === '') {
            $this->flash('danger', 'Merging requires the Master Password used to create the backup.');
            return $this->redirect('/import');
        }

        $keyFileMaterial = null;
        if ((int) ($payload['key_file_required'] ?? 0) === 1) {
            try {
                $keyFileMaterial = $this->readMergeKeyFile($request);
            } catch (RuntimeException $e) {
                $this->flash('danger', $e->getMessage());
                return $this->redirect('/import');
            }
        }

        $vaultKeyService = new \SimpleVault\Crypto\VaultKeyService(
            $this->crypto,
            new \SimpleVault\Crypto\KeyDerivationService()
        );

        try {
            $backupVaultKey = $vaultKeyService->unwrapVaultKey([
                'salt' => $payload['salt'],
                'encrypted_vault_key' => $payload['encrypted_vault_key'],
                'vault_key_nonce' => $payload['vault_key_nonce'],
                'kdf_ops_limit' => $payload['kdf_ops_limit'],
                'kdf_mem_limit' => $payload['kdf_mem_limit'],
            ], $backupMaster, $keyFileMaterial);
        } catch (RuntimeException) {
            $this->flash('danger', 'Could not unlock the backup with the provided Master Password / Key File.');
            return $this->redirect('/import');
        }

        $this->autoBackup();

        $importedEntries = $this->mergeRecords($payload['entries'] ?? [], $backupVaultKey, $currentKey, $this->entries);
        $importedNotes = $this->mergeRecords($payload['notes'] ?? [], $backupVaultKey, $currentKey, $this->notes);

        sodium_memzero($backupVaultKey);
        if ($keyFileMaterial !== null) {
            sodium_memzero($keyFileMaterial);
        }

        $this->audit->log($this->userId(), 'backup_imported_merge', $request->ip, $request->userAgent);
        $this->flash('success', "Merge complete: imported $importedEntries entr(ies) and $importedNotes note(s).");

        return $this->redirect('/');
    }

    /**
     * Decrypt records with the backup key and re-encrypt with the current key.
     *
     * @param EntryRepository|NoteRepository $repo
     */
    private function mergeRecords(array $rows, string $backupKey, string $currentKey, object $repo): int
    {
        $imported = 0;
        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            if (!Uuid::isValid($uuid) || $repo->existsByUuid($this->userId(), $uuid)) {
                continue; // skip duplicates
            }

            try {
                $plaintext = $this->crypto->decrypt(
                    (string) $row['encrypted_payload'],
                    (string) $row['payload_nonce'],
                    $backupKey
                );
            } catch (RuntimeException) {
                continue;
            }

            $reEncrypted = $this->crypto->encrypt($plaintext, $currentKey);
            $repo->insertRaw($this->userId(), [
                'uuid' => $uuid,
                'encrypted_payload' => $reEncrypted['ciphertext'],
                'payload_nonce' => $reEncrypted['nonce'],
                'favorite' => (int) ($row['favorite'] ?? 0),
                'archived' => (int) ($row['archived'] ?? 0),
                'created_at' => $row['created_at'] ?? now_iso(),
                'updated_at' => $row['updated_at'] ?? now_iso(),
            ]);
            $imported++;
        }

        return $imported;
    }

    // --- Internal: uploads / helpers --------------------------------------

    private function readBackupUpload(Request $request): array
    {
        $file = $request->file('backup_file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Please choose a backup file.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Backup upload failed.');
        }
        $maxBytes = (int) App::config('max_upload_mb', 10) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Backup file is too large.');
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException('Invalid upload.');
        }

        $content = file_get_contents($file['tmp_name']);
        @unlink($file['tmp_name']);
        if ($content === false) {
            throw new RuntimeException('Could not read the backup file.');
        }

        try {
            $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Backup file is not valid JSON.');
        }
        if (!is_array($data)) {
            throw new RuntimeException('Backup file structure is invalid.');
        }

        return $data;
    }

    private function readMergeKeyFile(Request $request): string
    {
        $file = $request->file('backup_key_file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('The backup requires a Key File to merge.');
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > 8192) {
            throw new RuntimeException('Invalid Key File upload.');
        }
        $content = file_get_contents($file['tmp_name']);
        @unlink($file['tmp_name']);
        if ($content === false) {
            throw new RuntimeException('Could not read the Key File.');
        }

        return (new \SimpleVault\Crypto\KeyFileService())->extractMaterial($content);
    }

    /**
     * Gather Markdown documents from single/multiple file or ZIP uploads.
     *
     * @return array<int, array{name:string, content:string}>
     */
    private function collectMarkdownUploads(Request $request, MarkdownImportService $importer, int $maxFiles, int $maxBytes): array
    {
        $documents = [];

        // ZIP upload.
        $zip = $request->file('zip_file');
        if ($zip !== null && ($zip['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if (($zip['size'] ?? 0) > $maxBytes) {
                throw new RuntimeException('ZIP file is too large.');
            }
            if (!is_uploaded_file($zip['tmp_name'] ?? '')) {
                throw new RuntimeException('Invalid ZIP upload.');
            }
            // Move to a private temp file with a random name before processing.
            $tmp = $this->moveToTemp($zip['tmp_name'], 'zip');
            try {
                $documents = $importer->extractZip($tmp, $maxFiles);
            } finally {
                @unlink($tmp);
            }

            return $documents;
        }

        // One or more .md files (input name: md_files[]).
        $files = $request->file('md_files');
        if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                if (count($documents) >= $maxFiles) {
                    break;
                }
                $name = (string) $files['name'][$i];
                if (!$importer->hasMarkdownExtension($name)) {
                    continue;
                }
                if (($files['size'][$i] ?? 0) > $maxBytes) {
                    continue;
                }
                if (!is_uploaded_file($files['tmp_name'][$i] ?? '')) {
                    continue;
                }
                $content = file_get_contents($files['tmp_name'][$i]);
                @unlink($files['tmp_name'][$i]);
                if ($content === false) {
                    continue;
                }
                $documents[] = ['name' => basename($name), 'content' => $content];
            }
        }

        return $documents;
    }

    private function moveToTemp(string $source, string $ext): string
    {
        $dir = base_path('storage/temp');
        $target = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($source, $target)) {
            throw new RuntimeException('Could not stage the uploaded file.');
        }

        return $target;
    }

    /**
     * Write an automatic encrypted backup to storage/backups before destructive
     * operations. The file contains only encrypted data.
     */
    private function autoBackup(): void
    {
        try {
            $backup = (new BackupService())->export($this->userId());
            $json = (new BackupService())->toJson($backup);
            $file = base_path('storage/backups/auto-' . $this->userId() . '-' . date('Y-m-d-His') . '.json');
            file_put_contents($file, $json, LOCK_EX);
        } catch (RuntimeException) {
            // If there is nothing to back up yet, ignore.
        }
    }

    /**
     * @return array<int, Note>
     */
    private function loadNotes(): array
    {
        $key = $this->requireVaultKey();
        $rows = $this->notes->allForUser($this->userId(), true);
        $notes = [];
        foreach ($rows as $row) {
            try {
                $payload = $this->crypto->decryptJson($row['encrypted_payload'], $row['payload_nonce'], $key);
            } catch (RuntimeException) {
                continue;
            }
            $notes[] = Note::fromRow($row, $payload);
        }

        return $notes;
    }

    private function requireVaultKey(): string
    {
        $key = Session::vaultKey();
        if ($key === null) {
            throw new RuntimeException('Vault is locked.');
        }

        return $key;
    }
}
