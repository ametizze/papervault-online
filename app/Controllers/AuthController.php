<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use SimpleVault\Core\App;
use SimpleVault\Core\Csrf;
use SimpleVault\Core\RateLimiter;
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
 * Handles first-run setup, login, and logout.
 */
final class AuthController extends Controller
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private VaultRepository $vaults = new VaultRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
    }

    // --- Setup -------------------------------------------------------------

    public function showSetup(Request $request): Response
    {
        if (!$this->setupAllowed()) {
            $this->flash('warning', 'Setup is not available.');
            return $this->redirect('/login');
        }

        return $this->view('auth/setup', ['errors' => [], 'old' => []], 'Setup');
    }

    public function setup(Request $request): Response
    {
        if (!$this->setupAllowed()) {
            $this->flash('warning', 'Setup is not available.');
            return $this->redirect('/login');
        }

        $minAccount = (int) App::config('min_account_password_length', 10);
        $minMaster = (int) App::config('min_master_password_length', 12);

        $validator = Validator::make($request->body)
            ->required('email', 'Email')->email('email', 'Email')
            ->required('account_password', 'Account password')
            ->minLength('account_password', $minAccount, 'Account password')
            ->required('master_password', 'Master Password')
            ->minLength('master_password', $minMaster, 'Master Password')
            ->matches('master_password_confirm', 'master_password', 'Master Password confirmation')
            ->accepted('recovery_ack', 'Recovery warning');

        if ($validator->fails()) {
            return $this->view('auth/setup', [
                'errors' => $validator->errors(),
                'old' => ['email' => $request->string('email')],
            ], 'Setup');
        }

        $email = strtolower(trim($request->string('email')));
        if ($this->users->findByEmail($email) !== null) {
            return $this->view('auth/setup', [
                'errors' => ['email' => 'An account with this email already exists.'],
                'old' => ['email' => $email],
            ], 'Setup');
        }

        $accountPassword = $request->string('account_password');
        $masterPassword = $request->string('master_password');
        $useKeyFile = $request->boolean('use_key_file');

        // Optional Key File: generate fresh material for download.
        $keyFileService = new KeyFileService();
        $keyFileMaterial = null;
        $keyFileDownload = null;
        if ($useKeyFile) {
            $keyFile = $keyFileService->generate();
            $keyFileMaterial = $keyFileService->extractMaterial($keyFileService->toJson($keyFile));
            $keyFileDownload = $keyFileService->toJson($keyFile);
        }

        // Create the user (account password hashed with Argon2id).
        $hash = password_hash($accountPassword, PASSWORD_ARGON2ID);
        $userId = $this->users->create($email, $hash);

        // Build the vault envelope.
        $crypto = new CryptoService();
        $vaultKeyService = new VaultKeyService($crypto, new KeyDerivationService());
        $rawVaultKey = $vaultKeyService->generateVaultKey();

        $wrapped = $vaultKeyService->wrapVaultKey(
            $rawVaultKey,
            $masterPassword,
            $keyFileMaterial,
            (int) App::config('kdf_ops_limit'),
            (int) App::config('kdf_mem_limit'),
        );

        $this->vaults->create($userId, $wrapped, $useKeyFile);

        sodium_memzero($rawVaultKey);

        $this->audit->log($userId, 'setup_completed', $request->ip, $request->userAgent);
        Session::login($userId);
        Csrf::rotate();

        // If a Key File was generated, deliver it as a one-time download.
        if ($keyFileDownload !== null) {
            $this->flash('success', 'Account created. Your Key File download will begin — store it safely. It is required to unlock your vault.');
            // Stash for the unlock screen reminder, then return the file.
            return Response::download($keyFileDownload, 'simplevault-keyfile.json', 'application/json');
        }

        $this->flash('success', 'Account created. Unlock your vault to begin.');
        return $this->redirect('/vault/unlock');
    }

    // --- Login / Logout ----------------------------------------------------

    public function showLogin(Request $request): Response
    {
        // If no users exist yet, send to setup.
        if ($this->users->count() === 0) {
            return $this->redirect('/setup');
        }

        return $this->view('auth/login', ['errors' => [], 'old' => []], 'Log in');
    }

    public function login(Request $request): Response
    {
        $email = strtolower(trim($request->string('email')));
        $password = $request->string('password');
        $limiter = new RateLimiter(App::db());

        if ($limiter->tooManyAttempts($request->ip, $email)) {
            $seconds = $limiter->secondsUntilRetry($request->ip, $email);
            $this->audit->log(null, 'login_rate_limited', $request->ip, $request->userAgent);
            return $this->view('auth/login', [
                'errors' => ['email' => 'Too many attempts. Try again in about ' . ceil($seconds / 60) . ' minute(s).'],
                'old' => ['email' => $email],
            ], 'Log in');
        }

        $user = $this->users->findByEmail($email);

        // Always run a verify to keep timing consistent and avoid user enumeration.
        $hash = $user['password_hash'] ?? '$argon2id$v=19$m=65536,t=4,p=1$ZHVtbXlzYWx0ZHVtbXk$0000000000000000000000000000000000000000000';
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            $limiter->recordFailure($request->ip, $email);
            $this->audit->log($user['id'] ?? null, 'login_failed', $request->ip, $request->userAgent);
            return $this->view('auth/login', [
                'errors' => ['email' => 'Invalid credentials.'],
                'old' => ['email' => $email],
            ], 'Log in');
        }

        $limiter->reset($request->ip, $email);

        // Rehash if parameters changed.
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_ARGON2ID));
        }

        Session::login((int) $user['id']);
        Csrf::rotate();
        $this->audit->log((int) $user['id'], 'login_success', $request->ip, $request->userAgent);

        $this->flash('success', 'Logged in. Unlock your vault to access your data.');
        return $this->redirect('/vault/unlock');
    }

    public function logout(Request $request): Response
    {
        $userId = Session::userId();
        $this->audit->log($userId, 'logout', $request->ip, $request->userAgent);
        Session::logout();

        return $this->redirect('/login');
    }

    private function setupAllowed(): bool
    {
        if ($this->users->count() === 0) {
            return true;
        }

        return (bool) App::config('allow_public_registration', false);
    }
}
