<?php
/**
 * Theme switcher control, shared by the authenticated navbar and the guest
 * (login/setup) corner bar.
 *
 * @var array<string,string> $themes  value => label
 * @var string               $theme   currently selected value
 */
?>
<div class="input-group input-group-sm w-auto" title="Theme">
    <span class="input-group-text"><i class="bi bi-palette"></i></span>
    <select class="form-select form-select-sm" data-theme-select aria-label="Theme">
        <?php foreach ($themes as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $theme === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
