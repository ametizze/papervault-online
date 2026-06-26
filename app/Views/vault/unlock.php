<?php
/** @var bool $keyFileRequired @var array $errors */
use SimpleVault\Core\Csrf;
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <h1 class="h3 mb-3">Unlock your vault</h1>
        <p class="text-muted">Enter your Master Password<?= $keyFileRequired ? ' and upload your Key File' : '' ?> to decrypt your data.</p>

        <div class="card">
            <div class="card-body">
                <form method="post" action="/vault/unlock" enctype="multipart/form-data" autocomplete="off">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Master Password</label>
                        <div class="input-group">
                            <input type="password" name="master_password" id="master_password" class="form-control" required autofocus>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-visibility="#master_password">Show</button>
                        </div>
                        <?php if (isset($errors['master_password'])): ?>
                            <div class="invalid-feedback d-block"><?= e($errors['master_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Key File <?= $keyFileRequired ? '<span class="text-danger">(required)</span>' : '<span class="text-muted">(optional)</span>' ?>
                        </label>
                        <input type="file" name="key_file" class="form-control" accept="application/json,.json" <?= $keyFileRequired ? 'required' : '' ?>>
                        <?php if (isset($errors['key_file'])): ?>
                            <div class="invalid-feedback d-block"><?= e($errors['key_file']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-success w-100" type="submit">Unlock</button>
                </form>
            </div>
        </div>
    </div>
</div>
