<?php
/**
 * Main layout.
 *
 * @var string $content   rendered child view HTML
 * @var string $title     page title
 * @var array  $flash     flash messages
 */
use SimpleVault\Core\Csrf;
use SimpleVault\Core\Session;

$appName = (string) config('app_name', 'SimpleVault');
$authenticated = Session::isAuthenticated();
$unlocked = Session::isVaultUnlocked();
$extraScripts = $extraScripts ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> &middot; <?= e($appName) ?></title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<?php if ($authenticated): ?>
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="/"><?= e($appName) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/entries">Passwords</a></li>
                <li class="nav-item"><a class="nav-link" href="/notes">Notes</a></li>
                <li class="nav-item"><a class="nav-link" href="/generator">Generator</a></li>
                <li class="nav-item"><a class="nav-link" href="/import">Import/Export</a></li>
                <li class="nav-item"><a class="nav-link" href="/settings">Settings</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="vault-state <?= $unlocked ? 'unlocked' : 'locked' ?>">
                    Vault: <?= $unlocked ? 'Unlocked' : 'Locked' ?>
                </span>
                <?php if ($unlocked): ?>
                    <form method="post" action="/vault/lock" class="m-0">
                        <?= Csrf::field() ?>
                        <button class="btn btn-sm btn-outline-warning" type="submit">Lock Vault</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-success" href="/vault/unlock">Unlock</a>
                <?php endif; ?>
                <form method="post" action="/logout" class="m-0">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Log out</button>
                </form>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container py-3">
    <?php foreach ($flash as $message): ?>
        <div class="alert alert-<?= e($message['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($message['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<footer class="app-footer text-center py-4">
    <div class="container">
        <?= e($appName) ?> &mdash; self-hosted personal vault. Not a replacement for
        mature password managers. If you lose your Master Password or Key File,
        your data cannot be recovered.
    </div>
</footer>

<script src="/assets/bootstrap.bundle.min.js"></script>
<script src="/assets/app.js"></script>
<script src="/assets/markdown-preview.js"></script>
<?php foreach ($extraScripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
