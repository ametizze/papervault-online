<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use RuntimeException;
use SimpleVault\Core\App;
use SimpleVault\Core\Csrf;
use SimpleVault\Core\Request;
use SimpleVault\Core\Response;
use SimpleVault\Core\Session;
use SimpleVault\Core\Validator;
use SimpleVault\Crypto\CryptoService;
use SimpleVault\Crypto\KeyDerivationService;
use SimpleVault\Crypto\KeyFileService;
use SimpleVault\Crypto\VaultKeyService;
use SimpleVault\Repositories\AuditRepository;
use SimpleVault\Repositories\UserRepository;
use SimpleVault\Repositories\VaultRepository;

/**
 * Settings: change Master Password, change account password, manage Key File.
 *
 * Changing the Master Password only re-wraps the Vault Key with a new salt; it
 * never decrypts/re-encrypts individual entries or notes.
 */
final class SettingsController extends Controller
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private VaultRepository $vaults = new VaultRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->users->findById($this->userId());
        $vault = $this->vaults->findByUserId($this->userId());

        return $this->view('settings/index', [
            'email' => $user['email'] ?? '',
            'keyFileRequired' => (bool) ($vault['key_file_required'] ?? false),
            'recent' => $this->audit->recent($this->userId(), 15),
            'errors' => [],
        ], 'Settings');
    }

    /**
     * Change the Master Password. Requires the current Master Password (and the
     * Key File if one is required) to unwrap the Vault Key first.
     */
    public function changeMasterPassword(Request $request): Response
    {
        $vault = $this->vaults->findByUserId($this->userId());
        if ($vault === null) {
            $this->flash('danger', 'No vault found.');
            return $this->redirect('/settings');
        }

        $minMaster = (int) App::config('min_master_password_length', 12);
        $validator = Validator::make($request->body)
            ->required('current_master_password', 'Current Master Password')
            ->required('new_master_password', 'New Master Password')
            ->minLength('new_master_password', $minMaster, 'New Master Password')
            ->matches('new_master_password_confirm', 'new_master_password', 'New Master Password confirmation');

        if ($validator->fails()) {
            $this->flash('danger', $validator->firstError() ?? 'Validation failed.');
            return $this->redirect('/settings');
        }

        $keyFileService = new KeyFileService();
        $vaultKeyService = new VaultKeyService(new CryptoService(), new KeyDerivationService());

        // Read current Key File material if required.
        $currentKeyFileMaterial = null;
        if ((bool) $vault['key_file_required']) {
            try {
                $currentKeyFileMaterial = $this->readUploadedKeyFile($request, 'current_key_file');
            } catch (RuntimeException $e) {
                $this->flash('danger', $e->getMessage());
                return $this->redirect('/settings');
            }
        }

        // Unwrap with current credentials.
        try {
            $rawVaultKey = $vaultKeyService->unwrapVaultKey(
                $vault,
                $request->string('current_master_password'),
                $currentKeyFileMaterial,
            );
        } catch (RuntimeException) {
            $this->audit->log($this->userId(), 'master_password_change_failed', $request->ip, $request->userAgent);
            $this->flash('danger', 'Current Master Password (or Key File) is incorrect.');
            return $this->redirect('/settings');
        }

        // Optionally rotate / keep / disable the key file requirement.
        $keyFileMode = $request->string('key_file_mode', 'keep'); // keep | new | none
        $newKeyFileMaterial = $currentKeyFileMaterial;
        $keyFileRequired = (bool) $vault['key_file_required'];
        $keyFileDownload = null;

        if ($keyFileMode === 'new') {
            $keyFile = $keyFileService->generate();
            $keyFileDownload = $keyFileService->toJson($keyFile);
            $newKeyFileMaterial = $keyFileService->extractMaterial($keyFileDownload);
            $keyFileRequired = true;
        } elseif ($keyFileMode === 'none') {
            $newKeyFileMaterial = null;
            $keyFileRequired = false;
        }

        // Re-wrap with the new Master Password + fresh salt.
        $wrapped = $vaultKeyService->rewrapVaultKey(
            $rawVaultKey,
            $request->string('new_master_password'),
            $newKeyFileMaterial,
            (int) App::config('kdf_ops_limit'),
            (int) App::config('kdf_mem_limit'),
        );

        $this->vaults->updateWrappedKey($this->userId(), $wrapped, $keyFileRequired);

        // Update the live session key (it is unchanged, but re-store to be safe).
        Session::unlockVault($rawVaultKey);
        sodium_memzero($rawVaultKey);
        Csrf::rotate();

        $this->audit->log($this->userId(), 'master_password_changed', $request->ip, $request->userAgent);

        if ($keyFileDownload !== null) {
            $this->flash('success', 'Master Password changed. Your NEW Key File download will begin — it is now required to unlock.');
            return Response::download($keyFileDownload, 'simplevault-keyfile.json', 'application/json');
        }

        $this->flash('success', 'Master Password changed successfully.');
        return $this->redirect('/settings');
    }

    /**
     * Change the account (login) password. Independent of vault encryption.
     */
    public function changeAccountPassword(Request $request): Response
    {
        $user = $this->users->findById($this->userId());
        if ($user === null) {
            $this->flash('danger', 'Account not found.');
            return $this->redirect('/settings');
        }

        $minAccount = (int) App::config('min_account_password_length', 10);
        $validator = Validator::make($request->body)
            ->required('current_account_password', 'Current password')
            ->required('new_account_password', 'New password')
            ->minLength('new_account_password', $minAccount, 'New password')
            ->matches('new_account_password_confirm', 'new_account_password', 'New password confirmation');

        if ($validator->fails()) {
            $this->flash('danger', $validator->firstError() ?? 'Validation failed.');
            return $this->redirect('/settings');
        }

        if (!password_verify($request->string('current_account_password'), (string) $user['password_hash'])) {
            $this->flash('danger', 'Current password is incorrect.');
            return $this->redirect('/settings');
        }

        $this->users->updatePasswordHash(
            $this->userId(),
            password_hash($request->string('new_account_password'), PASSWORD_ARGON2ID)
        );
        $this->audit->log($this->userId(), 'account_password_changed', $request->ip, $request->userAgent);

        $this->flash('success', 'Account password changed.');
        return $this->redirect('/settings');
    }

    private function readUploadedKeyFile(Request $request, string $field): string
    {
        $file = $request->file($field);
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('A Key File is required for this action.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8192) {
            throw new RuntimeException('Key File upload is invalid.');
        }
        $tmp = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid Key File upload.');
        }
        $content = file_get_contents($tmp);
        @unlink($tmp);
        if ($content === false) {
            throw new RuntimeException('Could not read the Key File.');
        }

        return (new KeyFileService())->extractMaterial($content);
    }
}
