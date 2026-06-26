<?php
/** @var array $errors @var array $old */
use SimpleVault\Core\Csrf;
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <h1 class="h3 mb-4 text-center"><?= e(config('app_name')) ?></h1>
        <div class="card">
            <div class="card-body">
                <h2 class="h5 mb-3">Log in</h2>
                <form method="post" action="/login" autocomplete="off">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="alert alert-danger py-2"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                    <button class="btn btn-primary w-100" type="submit">Log in</button>
                </form>
            </div>
        </div>
    </div>
</div>
