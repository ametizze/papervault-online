<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use RuntimeException;
use SimpleVault\Core\Request;
use SimpleVault\Core\Response;
use SimpleVault\Core\Session;
use SimpleVault\Crypto\CryptoService;
use SimpleVault\Crypto\KeyDerivationService;
use SimpleVault\Crypto\KeyFileService;
use SimpleVault\Crypto\VaultKeyService;
use SimpleVault\Repositories\AuditRepository;
use SimpleVault\Repositories\EntryRepository;
use SimpleVault\Repositories\NoteRepository;
use SimpleVault\Repositories\VaultRepository;

/**
 * Vault unlock/lock and the dashboard.
 */
final class VaultController extends Controller
{
    public function __construct(
        private VaultRepository $vaults = new VaultRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
    }

    public function showUnlock(Request $request): Response
    {
        if (Session::isVaultUnlocked()) {
            return $this->redirect('/');
        }

        $vault = $this->vaults->findByUserId($this->userId());
        if ($vault === null) {
            $this->flash('danger', 'No vault found for this account.');
            return $this->redirect('/login');
        }

        return $this->view('vault/unlock', [
            'keyFileRequired' => (bool) $vault['key_file_required'],
            'errors' => [],
        ], 'Unlock Vault');
    }

    public function unlock(Request $request): Response
    {
        $vault = $this->vaults->findByUserId($this->userId());
        if ($vault === null) {
            $this->flash('danger', 'No vault found for this account.');
            return $this->redirect('/login');
        }

        $masterPassword = $request->string('master_password');
        if (trim($masterPassword) === '') {
            return $this->view('vault/unlock', [
                'keyFileRequired' => (bool) $vault['key_file_required'],
                'errors' => ['master_password' => 'Master Password is required.'],
            ], 'Unlock Vault');
        }

        // Optional / required Key File handling.
        $keyFileMaterial = null;
        try {
            $keyFileMaterial = $this->readKeyFile($request, (bool) $vault['key_file_required']);
        } catch (RuntimeException $e) {
            return $this->view('vault/unlock', [
                'keyFileRequired' => (bool) $vault['key_file_required'],
                'errors' => ['key_file' => $e->getMessage()],
            ], 'Unlock Vault');
        }

        $vaultKeyService = new VaultKeyService(new CryptoService(), new KeyDerivationService());

        try {
            $rawVaultKey = $vaultKeyService->unwrapVaultKey($vault, $masterPassword, $keyFileMaterial);
        } catch (RuntimeException) {
            $this->audit->log($this->userId(), 'vault_unlock_failed', $request->ip, $request->userAgent);
            return $this->view('vault/unlock', [
                'keyFileRequired' => (bool) $vault['key_file_required'],
                'errors' => ['master_password' => 'Could not unlock the vault. Check your Master Password' . ((bool) $vault['key_file_required'] ? ' and Key File.' : '.')],
            ], 'Unlock Vault');
        }

        Session::unlockVault($rawVaultKey);
        sodium_memzero($rawVaultKey);
        if ($keyFileMaterial !== null) {
            sodium_memzero($keyFileMaterial);
        }

        $this->audit->log($this->userId(), 'vault_unlocked', $request->ip, $request->userAgent);
        $this->flash('success', 'Vault unlocked.');

        return $this->redirect('/');
    }

    public function lock(Request $request): Response
    {
        Session::lockVault();
        $this->audit->log($this->userId(), 'vault_locked', $request->ip, $request->userAgent);
        $this->flash('info', 'Vault locked.');

        return $this->redirect('/vault/unlock');
    }

    public function dashboard(Request $request): Response
    {
        $entries = new EntryRepository();
        $notes = new NoteRepository();

        return $this->view('vault/dashboard', [
            'vaultUnlocked' => Session::isVaultUnlocked(),
            'entryCount' => count($entries->allForUser($this->userId(), true)),
            'noteCount' => count($notes->allForUser($this->userId(), true)),
        ], 'Dashboard');
    }

    /**
     * Read and validate an uploaded Key File, returning its raw material.
     *
     * @throws RuntimeException when required but missing, or invalid
     */
    private function readKeyFile(Request $request, bool $required): ?string
    {
        $file = $request->file('key_file');

        $noUpload = $file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;

        if ($noUpload) {
            if ($required) {
                throw new RuntimeException('A Key File is required to unlock this vault.');
            }
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Key File upload failed.');
        }

        if (($file['size'] ?? 0) > 8192) {
            throw new RuntimeException('Key File is too large.');
        }

        $tmpPath = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid Key File upload.');
        }

        $content = file_get_contents($tmpPath);
        @unlink($tmpPath);

        if ($content === false) {
            throw new RuntimeException('Could not read the Key File.');
        }

        return (new KeyFileService())->extractMaterial($content);
    }
}
