<?php
/**
 * Reusable Markdown editor widget: labelled textarea with a live, sanitized
 * preview and a Write/Preview toggle. Multiple instances can coexist on one
 * page as long as each gets a distinct $mdId (used to pair the parts in JS).
 *
 * @var string      $mdName   Form field name.
 * @var string      $mdId     Unique group id for this editor on the page.
 * @var string      $mdValue  Current raw Markdown value.
 * @var string      $mdLabel  Label shown above the editor.
 * @var int         $mdRows   Textarea rows (optional, default 12).
 * @var string|null $mdError  Validation error message (optional).
 */
$mdRows = $mdRows ?? 12;
$mdCountId = $mdId . '-count';
?>
<div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
        <label class="form-label mb-0"><?= e($mdLabel) ?></label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-md-toggle="<?= e($mdId) ?>">Preview</button>
    </div>
    <textarea name="<?= e($mdName) ?>" class="form-control font-monospace" rows="<?= (int) $mdRows ?>"
              data-md-source="<?= e($mdId) ?>" data-counter="#<?= e($mdCountId) ?>"><?= e($mdValue) ?></textarea>
    <div class="markdown-body border rounded p-3 mt-2 bg-white d-none" data-md-preview="<?= e($mdId) ?>"></div>
    <small class="text-muted"><span id="<?= e($mdCountId) ?>">0</span> characters. Preview is sanitized; raw HTML is escaped.</small>
    <?php if (!empty($mdError)): ?>
        <div class="invalid-feedback d-block"><?= e($mdError) ?></div>
    <?php endif; ?>
</div>
