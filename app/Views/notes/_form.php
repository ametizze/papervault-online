<?php
/**
 * Shared note form with Markdown editor + live preview.
 * @var array $old @var array $errors @var string $action
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
            <label class="form-label">Client</label>
            <input type="text" name="client" class="form-control" value="<?= $val('client') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Project</label>
            <input type="text" name="project" class="form-control" value="<?= $val('project') ?>">
        </div>

        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0">Markdown content</label>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-md-toggle>Preview</button>
            </div>
            <textarea name="markdown" class="form-control font-monospace" rows="16" data-md-source data-counter="#md-count"><?= e((string) ($old['markdown'] ?? '')) ?></textarea>
            <div class="markdown-body border rounded p-3 mt-2 bg-white d-none" data-md-preview></div>
            <small class="text-muted"><span id="md-count">0</span> characters. Preview is sanitized; raw HTML is escaped.</small>
            <?= $err('markdown') ?>
        </div>

        <div class="col-12">
            <label class="form-label">Tags (comma separated)</label>
            <input type="text" name="tags" class="form-control" value="<?= e($tagsValue) ?>" placeholder="architecture, deployment">
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-outline-secondary" href="/notes">Cancel</a>
    </div>
</form>
