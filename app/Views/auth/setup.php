<?php
/** @var array $errors @var array $old */
use SimpleVault\Core\Csrf;

$err = fn (string $f): string => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';

// Editable copy lives in config/app.php ('setup_text', overridable via .env).
$text = config('setup_text', []);
$minAccount = (int) config('min_account_password_length');
$minMaster = (int) config('min_master_password_length');
$accountHelp = sprintf((string) ($text['account_password_help'] ?? ''), $minAccount);
$masterHelp = sprintf((string) ($text['master_password_help'] ?? ''), $minMaster);
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <h1 class="h3 mb-3">Welcome to <?= e(config('app_name')) ?></h1>
        <p class="text-muted"><?= e($text['intro'] ?? '') ?></p>

        <div class="card warning-banner mb-4">
            <div class="card-body">
                <strong><?= e($text['recovery_title'] ?? '') ?></strong>
                <p class="mb-0"><?= e($text['recovery_warning'] ?? '') ?></p>
            </div>
        </div>

        <form method="post" action="/setup" enctype="multipart/form-data" autocomplete="off">
            <?= Csrf::field() ?>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
                <?= $err('email') ?>
            </div>

            <hr>
            <h2 class="h6 text-uppercase text-muted"><i class="bi bi-person-badge me-1"></i>Account (login) password</h2>
            <div class="mb-3">
                <label class="form-label">Account password</label>
                <input type="password" name="account_password" class="form-control" required>
                <div class="form-text"><?= e($accountHelp) ?></div>
                <?= $err('account_password') ?>
            </div>

            <hr>
            <h2 class="h6 text-uppercase text-muted"><i class="bi bi-shield-lock me-1"></i>Master Password (encrypts your vault)</h2>
            <div class="mb-3">
                <label class="form-label">Master Password</label>
                <input type="password" name="master_password" class="form-control" required>
                <div class="form-text"><?= e($masterHelp) ?></div>
                <?= $err('master_password') ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Master Password</label>
                <input type="password" name="master_password_confirm" class="form-control" required>
                <?= $err('master_password_confirm') ?>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="use_key_file" id="use_key_file" value="1">
                <label class="form-check-label" for="use_key_file">
                    <?= e($text['key_file_help'] ?? '') ?>
                </label>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="recovery_ack" id="recovery_ack" value="1" required>
                <label class="form-check-label" for="recovery_ack">
                    <?= e($text['recovery_ack_label'] ?? '') ?>
                </label>
                <?= $err('recovery_ack') ?>
            </div>

            <button class="btn btn-primary" type="submit"><i class="bi bi-shield-plus me-1"></i>Create account &amp; vault</button>
        </form>
    </div>
</div>
