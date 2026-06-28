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
use SimpleVault\Core\View;

$appName = (string) config('app_name', 'SimpleVault');
$authenticated = Session::isAuthenticated();
$unlocked = Session::isVaultUnlocked();
$extraScripts = $extraScripts ?? [];

// Theme is persisted in a cookie and applied server-side so there is no
// flash of the wrong theme on load and no inline script is needed.
$themes = ['light' => 'Light', 'dracula' => 'Dracula', 'monokai' => 'Monokai', 'win95' => 'Windows 95'];
$theme = (string) ($_COOKIE['theme'] ?? 'light');
if (!isset($themes[$theme])) {
    $theme = 'light';
}
$bsTheme = in_array($theme, ['dracula', 'monokai'], true) ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>" data-bs-theme="<?= e($bsTheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> &middot; <?= e($appName) ?></title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/bootstrap-icons.css">
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
                <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/entries"><i class="bi bi-key me-1"></i>Passwords</a></li>
                <li class="nav-item"><a class="nav-link" href="/notes"><i class="bi bi-journal-text me-1"></i>Notes</a></li>
                <li class="nav-item"><a class="nav-link" href="/generator"><i class="bi bi-shuffle me-1"></i>Generator</a></li>
                <li class="nav-item"><a class="nav-link" href="/import"><i class="bi bi-arrow-down-up me-1"></i>Import/Export</a></li>
                <li class="nav-item"><a class="nav-link" href="/settings"><i class="bi bi-gear me-1"></i>Settings</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <?= View::renderPartial('partials/_theme_select', ['themes' => $themes, 'theme' => $theme]) ?>
                <span class="vault-state <?= $unlocked ? 'unlocked' : 'locked' ?>">
                    <i class="bi bi-<?= $unlocked ? 'unlock' : 'lock' ?>-fill me-1"></i><?= $unlocked ? 'Unlocked' : 'Locked' ?>
                </span>
                <?php if ($unlocked): ?>
                    <form method="post" action="/vault/lock" class="m-0">
                        <?= Csrf::field() ?>
                        <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-lock me-1"></i>Lock Vault</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-success" href="/vault/unlock"><i class="bi bi-unlock me-1"></i>Unlock</a>
                <?php endif; ?>
                <form method="post" action="/logout" class="m-0">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Log out</button>
                </form>
            </div>
        </div>
    </div>
</nav>
<?php else: ?>
<div class="container d-flex justify-content-end pt-2">
    <?= View::renderPartial('partials/_theme_select', ['themes' => $themes, 'theme' => $theme]) ?>
</div>
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
