<?php
/** @var string $email @var bool $keyFileRequired @var array $recent @var array $errors */
use SimpleVault\Core\Csrf;
?>
<h1 class="h3 mb-4">Settings</h1>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5">Account</h2>
            <p class="mb-3"><strong>Email:</strong> <?= e($email) ?></p>
            <p class="mb-3"><strong>Key File required:</strong> <?= $keyFileRequired ? 'Yes' : 'No' ?></p>

            <h3 class="h6 mt-4">Change account (login) password</h3>
            <form method="post" action="/settings/account-password" autocomplete="off">
                <?= Csrf::field() ?>
                <div class="mb-2">
                    <label class="form-label">Current password</label>
                    <input type="password" name="current_account_password" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">New password</label>
                    <input type="password" name="new_account_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="new_account_password_confirm" class="form-control" required>
                </div>
                <button class="btn btn-outline-primary" type="submit">Update account password</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5">Change Master Password</h2>
            <p class="text-muted small">This re-wraps your vault key with a new salt. Your entries
                and notes are not re-encrypted.</p>
            <form method="post" action="/settings/master-password" enctype="multipart/form-data" autocomplete="off">
                <?= Csrf::field() ?>
                <div class="mb-2">
                    <label class="form-label">Current Master Password</label>
                    <input type="password" name="current_master_password" class="form-control" required>
                </div>
                <?php if ($keyFileRequired): ?>
                    <div class="mb-2">
                        <label class="form-label">Current Key File</label>
                        <input type="file" name="current_key_file" class="form-control" accept=".json" required>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <label class="form-label">New Master Password</label>
                    <input type="password" name="new_master_password" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Confirm new Master Password</label>
                    <input type="password" name="new_master_password_confirm" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Key File</label>
                    <select name="key_file_mode" class="form-select">
                        <option value="keep">Keep current setting</option>
                        <option value="new">Generate a NEW Key File (downloads now, becomes required)</option>
                        <option value="none">Disable Key File requirement</option>
                    </select>
                </div>
                <button class="btn btn-warning" type="submit">Change Master Password</button>
            </form>
        </div></div>
    </div>

    <div class="col-12">
        <div class="card"><div class="card-body">
            <h2 class="h5">Recent activity</h2>
            <table class="table table-sm mb-0">
                <thead><tr><th>Event</th><th>IP</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td><?= e((string) $row['event_type']) ?></td>
                        <td><small><?= e((string) ($row['ip_address'] ?? '')) ?></small></td>
                        <td><small><?= e((string) $row['created_at']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recent === []): ?>
                    <tr><td colspan="3" class="text-muted">No activity recorded yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
