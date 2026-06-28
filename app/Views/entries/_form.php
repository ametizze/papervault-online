<?php
/**
 * Shared entry form fields.
 * @var array $old @var array $errors @var string $action @var string|null $suggestedPassword
 */
use SimpleVault\Core\Csrf;
use SimpleVault\Core\View;

$val = fn (string $k): string => e((string) ($old[$k] ?? ''));
$tagsValue = '';
if (isset($old['tags'])) {
    $tagsValue = is_array($old['tags']) ? implode(', ', $old['tags']) : (string) $old['tags'];
}
$err = fn (string $f): string => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';

// Normalize any previously submitted / stored custom fields so the form can
// re-render them after a validation error or when editing. Reads both the new
// "name" key and the legacy "label" key.
$customFields = SimpleVault\Models\Entry::normalizeFields($old['fields'] ?? []);

// Render a single custom-field row. $idx must be unique within the form so the
// posted "fields[$idx][...]" names stay grouped together. The stable field id
// travels in a hidden input so the server can preserve per-field timestamps.
$typeLabels = ['text' => 'Text', 'password' => 'Password', 'url' => 'URL', 'email' => 'Email', 'totp' => 'TOTP'];
$fieldRow = static function (string|int $idx, array $field) use ($typeLabels): string {
    $n = static fn (string $k): string => 'fields[' . $idx . '][' . $k . ']';
    $type = (string) ($field['type'] ?? 'text');
    ob_start(); ?>
    <div class="border rounded p-2 mb-2 d-flex gap-2 align-items-start" data-field-row>
        <span class="text-muted pt-1" data-drag-handle title="Drag to reorder" aria-label="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
        <div class="flex-grow-1">
        <input type="hidden" name="<?= e($n('id')) ?>" value="<?= e((string) ($field['id'] ?? '')) ?>">
        <div class="row g-2 align-items-center">
            <div class="col-6 col-md-2">
                <select name="<?= e($n('type')) ?>" class="form-select form-select-sm" data-field-type>
                    <?php foreach ($typeLabels as $tv => $tl): ?>
                        <option value="<?= e($tv) ?>" <?= $type === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <input type="text" name="<?= e($n('name')) ?>" class="form-control form-control-sm" placeholder="Name (e.g. mysql)" value="<?= e((string) ($field['name'] ?? '')) ?>" data-field-name>
            </div>
            <div class="col-md-4">
                <input type="text" name="<?= e($n('value')) ?>" class="form-control form-control-sm" placeholder="Value / secret" value="<?= e((string) ($field['value'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="col-md-3 d-flex align-items-center gap-2">
                <div class="form-check mb-0">
                    <input type="hidden" name="<?= e($n('secret')) ?>" value="0">
                    <input class="form-check-input" type="checkbox" name="<?= e($n('secret')) ?>" value="1" <?= !empty($field['secret']) ? 'checked' : '' ?>>
                    <label class="form-check-label small">Secret</label>
                </div>
                <input type="date" name="<?= e($n('expiresAt')) ?>" class="form-control form-control-sm" title="Expires" value="<?= e((string) ($field['expiresAt'] ?? '')) ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row aria-label="Remove field">&times;</button>
            </div>
            <div class="col-12">
                <input type="text" name="<?= e($n('observation')) ?>" class="form-control form-control-sm" placeholder="Observation (optional, Markdown)" value="<?= e((string) ($field['observation'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-text text-danger d-none small" data-dup-warning>Duplicate field name.</div>
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
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

        <?= View::renderPartial('partials/_markdown_field', [
            'mdName' => 'notes',
            'mdId' => 'entry-notes',
            'mdValue' => (string) ($old['notes'] ?? ''),
            'mdLabel' => 'Notes (Markdown)',
            'mdRows' => 3,
            'mdError' => $errors['notes'] ?? null,
        ]) ?>

        <?= View::renderPartial('partials/_markdown_field', [
            'mdName' => 'body',
            'mdId' => 'entry-body',
            'mdValue' => (string) ($old['body'] ?? ''),
            'mdLabel' => 'Details / documentation (Markdown)',
            'mdRows' => 10,
            'mdError' => $errors['body'] ?? null,
        ]) ?>

        <div class="col-12">
            <label class="form-label">Tags (comma separated)</label>
            <input type="text" name="tags" class="form-control" value="<?= e($tagsValue) ?>" placeholder="dev, work">
        </div>

        <div class="col-12">
            <label class="form-label mb-1">Custom fields</label>
            <p class="text-muted small mb-2">Group several secrets under one entry — e.g. mysql, redis and ssh passwords for the same server or project.</p>
            <div data-field-container>
                <?php foreach ($customFields as $i => $f): ?>
                    <?= $fieldRow($i, $f) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-field="[data-field-container]" data-template="#custom-field-template"><i class="bi bi-plus-lg me-1"></i>Add field</button>
            <template id="custom-field-template">
                <?= $fieldRow('__INDEX__', ['secret' => true]) ?>
            </template>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Save</button>
        <a class="btn btn-outline-secondary" href="/entries"><i class="bi bi-x-lg me-1"></i>Cancel</a>
    </div>
</form>
