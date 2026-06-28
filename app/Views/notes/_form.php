<?php
/**
 * Shared note form with Markdown editor + live preview.
 * @var array $old @var array $errors @var string $action
 */
use SimpleVault\Core\Csrf;
use SimpleVault\Core\View;

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

        <div class="col-md-4">
            <label class="form-label">Ticket # / ref</label>
            <input type="text" name="ticket" class="form-control" value="<?= $val('ticket') ?>" placeholder="e.g. INC-1234">
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">&mdash; None &mdash;</option>
                <?php foreach (\SimpleVault\Models\Note::STATUSES as $sv => $sl): ?>
                    <option value="<?= e($sv) ?>" <?= ($old['status'] ?? '') === $sv ? 'selected' : '' ?>><?= e($sl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Expires</label>
            <input type="date" name="expiresAt" class="form-control" value="<?= $val('expiresAt') ?>">
        </div>

        <?= View::renderPartial('partials/_markdown_field', [
            'mdName' => 'markdown',
            'mdId' => 'note-markdown',
            'mdValue' => (string) ($old['markdown'] ?? ''),
            'mdLabel' => 'Markdown content',
            'mdRows' => 16,
            'mdError' => $errors['markdown'] ?? null,
        ]) ?>

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
