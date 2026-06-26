<?php
use SimpleVault\Core\Csrf;
use SimpleVault\Core\Session;

$unlocked = Session::isVaultUnlocked();
?>
<h1 class="h3 mb-4">Import / Export</h1>

<div class="row g-4">
    <!-- Encrypted backup export -->
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5">Encrypted full-vault backup</h2>
            <p class="text-muted">Exports an encrypted JSON file containing your vault key
                (wrapped by your Master Password), all entries, and all notes. It contains
                <strong>no plaintext secrets</strong>.</p>
            <form method="post" action="/export">
                <?= Csrf::field() ?>
                <button class="btn btn-primary" type="submit">Download encrypted backup</button>
            </form>
        </div></div>
    </div>

    <!-- Plaintext notes export -->
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5">Markdown notes export</h2>
            <p class="text-muted">Export notes as plaintext <code>.md</code> files or a ZIP.
                <strong>Not encrypted.</strong></p>
            <?php if ($unlocked): ?>
                <a class="btn btn-outline-primary" href="/notes/export">Go to notes export</a>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="/vault/unlock">Unlock vault to export notes</a>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- Encrypted backup import -->
    <div class="col-12">
        <div class="card"><div class="card-body">
            <h2 class="h5">Restore from encrypted backup</h2>
            <p class="text-muted mb-3">Choose how to apply the backup. A safety backup of your
                current vault is created automatically before any destructive change.</p>

            <form method="post" action="/import" enctype="multipart/form-data" data-confirm="Import this backup? Replace mode overwrites your current vault.">
                <?= Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label">Backup file (.json)</label>
                    <input type="file" name="backup_file" class="form-control" accept="application/json,.json" required>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mode" id="mode_merge" value="merge" checked>
                        <label class="form-check-label" for="mode_merge">
                            <strong>Merge</strong> into current vault (requires the backup's Master Password;
                            re-encrypts imported records under your current vault key; skips duplicates).
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mode" id="mode_replace" value="replace">
                        <label class="form-check-label" for="mode_replace">
                            <strong>Replace</strong> current vault (overwrites everything; afterwards you unlock
                            with the Master Password from the backup).
                        </label>
                    </div>
                </div>

                <fieldset class="border rounded p-3 mb-3">
                    <legend class="float-none w-auto px-2 h6">For Merge mode</legend>
                    <div class="mb-2">
                        <label class="form-label">Backup's Master Password</label>
                        <input type="password" name="backup_master_password" class="form-control" autocomplete="off">
                    </div>
                    <div>
                        <label class="form-label">Backup's Key File (only if it required one)</label>
                        <input type="file" name="backup_key_file" class="form-control" accept="application/json,.json">
                    </div>
                </fieldset>

                <button class="btn btn-danger" type="submit">Import backup</button>
            </form>
        </div></div>
    </div>

    <div class="col-12">
        <div class="card"><div class="card-body">
            <h2 class="h5">Import notes from Markdown</h2>
            <?php if ($unlocked): ?>
                <a class="btn btn-outline-primary" href="/notes/import">Go to notes import</a>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="/vault/unlock">Unlock vault to import notes</a>
            <?php endif; ?>
        </div></div>
    </div>
</div>
