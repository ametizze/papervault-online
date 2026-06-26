<?php
/** @var bool $vaultUnlocked @var int $entryCount @var int $noteCount */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <span class="vault-state <?= $vaultUnlocked ? 'unlocked' : 'locked' ?>">
        Vault: <?= $vaultUnlocked ? 'Unlocked' : 'Locked' ?>
    </span>
</div>

<?php if (!$vaultUnlocked): ?>
    <div class="card warning-banner">
        <div class="card-body">
            <h2 class="h5">Your vault is locked</h2>
            <p class="mb-3">Unlock your vault to view and manage your passwords and notes.</p>
            <a class="btn btn-success" href="/vault/unlock">Unlock vault</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-muted">Password entries</h2>
                    <div class="display-6"><?= (int) $entryCount ?></div>
                    <a href="/entries" class="stretched-link">Manage passwords</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-muted">Markdown notes</h2>
                    <div class="display-6"><?= (int) $noteCount ?></div>
                    <a href="/notes" class="stretched-link">Manage notes</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-muted">Quick actions</h2>
                    <div class="d-grid gap-2 mt-2">
                        <a href="/entries/create" class="btn btn-sm btn-outline-primary">New password</a>
                        <a href="/notes/create" class="btn btn-sm btn-outline-primary">New note</a>
                        <a href="/generator" class="btn btn-sm btn-outline-secondary">Generate password</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
