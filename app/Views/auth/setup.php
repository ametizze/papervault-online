<?php
/** @var array $errors @var array $old */
use SimpleVault\Core\Csrf;

$err = fn (string $f): string => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <h1 class="h3 mb-3">Welcome to <?= e(config('app_name')) ?></h1>
        <p class="text-muted">Create the first account and your encrypted vault.</p>

        <div class="card warning-banner mb-4">
            <div class="card-body">
                <strong>Important — no recovery.</strong>
                <p class="mb-0">If you lose your Master Password or Key File, your saved
                passwords and notes <strong>cannot be recovered</strong>. There is no reset.</p>
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
            <h2 class="h6 text-uppercase text-muted">Account (login) password</h2>
            <div class="mb-3">
                <label class="form-label">Account password</label>
                <input type="password" name="account_password" class="form-control" required>
                <div class="form-text">Used to log in. Minimum <?= (int) config('min_account_password_length') ?> characters.</div>
                <?= $err('account_password') ?>
            </div>

            <hr>
            <h2 class="h6 text-uppercase text-muted">Master Password (encrypts your vault)</h2>
            <div class="mb-3">
                <label class="form-label">Master Password</label>
                <input type="password" name="master_password" class="form-control" required>
                <div class="form-text">Minimum <?= (int) config('min_master_password_length') ?> characters. It is never stored.</div>
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
                    Generate an optional Key File (second factor). You will download it now and must
                    upload it every time you unlock. Store it separately from your password.
                </label>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="recovery_ack" id="recovery_ack" value="1" required>
                <label class="form-check-label" for="recovery_ack">
                    I understand there is <strong>no recovery</strong> if I lose my Master Password or Key File.
                </label>
                <?= $err('recovery_ack') ?>
            </div>

            <button class="btn btn-primary" type="submit">Create account &amp; vault</button>
        </form>
    </div>
</div>
