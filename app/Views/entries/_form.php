<?php
/**
 * Shared entry form fields.
 * @var array $old @var array $errors @var string $action @var string|null $suggestedPassword
 */
use SimpleVault\Core\Csrf;

$val = fn (string $k): string => e((string) ($old[$k] ?? ''));
$tagsValue = '';
if (isset($old['tags'])) {
    $tagsValue = is_array($old['tags']) ? implode(', ', $old['tags']) : (string) $old['tags'];
}
$err = fn (string $f): string => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
?>
<form method="post" action="<?= e($action) ?>" autocomplete="off">
    <?= Csrf::field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-control" value="<?= $val('title') ?>" required>
            <?= $err('title') ?>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="favorite" value="1" id="favorite" <?= !empty($old['favorite']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="favorite">Favorite</label>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label">Website URL</label>
            <input type="text" name="url" class="form-control" value="<?= $val('url') ?>" placeholder="https://example.com">
        </div>
        <div class="col-md-6">
            <label class="form-label">Username / email</label>
            <input type="text" name="username" class="form-control" value="<?= $val('username') ?>">
        </div>

        <div class="col-12">
            <label class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="entry_password" class="form-control secret-field" value="<?= e((string) ($old['password'] ?? $suggestedPassword ?? '')) ?>">
                <button class="btn btn-outline-secondary" type="button" data-toggle-visibility="#entry_password">Show</button>
                <button class="btn btn-outline-secondary" type="button" data-copy-target="#entry_password">Copy</button>
            </div>
            <small class="text-muted">Need a strong one? Use the <a href="/generator">generator</a>.</small>
        </div>

        <div class="col-md-6">
            <label class="form-label">Client</label>
            <input type="text" name="client" class="form-control" value="<?= $val('client') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Project</label>
            <input type="text" name="project" class="form-control" value="<?= $val('project') ?>">
        </div>

        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= e((string) ($old['notes'] ?? '')) ?></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Tags (comma separated)</label>
            <input type="text" name="tags" class="form-control" value="<?= e($tagsValue) ?>" placeholder="dev, work">
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-outline-secondary" href="/entries">Cancel</a>
    </div>
</form>
