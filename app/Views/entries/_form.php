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

// Normalize any previously submitted / stored custom fields so the form can
// re-render them after a validation error or when editing.
$customFields = [];
if (isset($old['fields']) && is_array($old['fields'])) {
    foreach ($old['fields'] as $f) {
        if (!is_array($f)) {
            continue;
        }
        $customFields[] = [
            'label' => (string) ($f['label'] ?? ''),
            'value' => (string) ($f['value'] ?? ''),
            'secret' => !empty($f['secret']),
        ];
    }
}

// Render a single custom-field row. $idx must be unique within the form so the
// posted "fields[$idx][...]" names stay grouped together.
$fieldRow = static function (string|int $idx, string $label, string $value, bool $secret): string {
    ob_start(); ?>
    <div class="row g-2 mb-2 align-items-center" data-field-row>
        <div class="col-md-4">
            <input type="text" name="fields[<?= e((string) $idx) ?>][label]" class="form-control" placeholder="Label (e.g. mysql)" value="<?= e($label) ?>">
        </div>
        <div class="col-md-5">
            <input type="text" name="fields[<?= e((string) $idx) ?>][value]" class="form-control" placeholder="Value" value="<?= e($value) ?>" autocomplete="off">
        </div>
        <div class="col-md-3 d-flex align-items-center gap-3">
            <div class="form-check mb-0">
                <input type="hidden" name="fields[<?= e((string) $idx) ?>][secret]" value="0">
                <input class="form-check-input" type="checkbox" name="fields[<?= e((string) $idx) ?>][secret]" value="1" <?= $secret ? 'checked' : '' ?>>
                <label class="form-check-label">Secret</label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row aria-label="Remove field">&times;</button>
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

        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= e((string) ($old['notes'] ?? '')) ?></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Tags (comma separated)</label>
            <input type="text" name="tags" class="form-control" value="<?= e($tagsValue) ?>" placeholder="dev, work">
        </div>

        <div class="col-12">
            <label class="form-label mb-1">Custom fields</label>
            <p class="text-muted small mb-2">Group several secrets under one entry — e.g. mysql, redis and ssh passwords for the same server or project.</p>
            <div data-field-container>
                <?php foreach ($customFields as $i => $f): ?>
                    <?= $fieldRow($i, $f['label'], $f['value'], $f['secret']) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-field="[data-field-container]" data-template="#custom-field-template">+ Add field</button>
            <template id="custom-field-template">
                <?= $fieldRow('__INDEX__', '', '', true) ?>
            </template>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-outline-secondary" href="/entries">Cancel</a>
    </div>
</form>
