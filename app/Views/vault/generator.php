<?php
/** @var string $password @var array $options @var string|null $error */
$opt = fn (string $k): bool => (bool) ($options[$k] ?? false);
?>
<h1 class="h3 mb-4">Password generator</h1>

<div class="row">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label">Generated password</label>
                <div class="input-group mb-2">
                    <input type="text" id="generated" class="form-control secret-field" value="<?= e($password) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" data-copy-target="#generated">Copy</button>
                </div>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger py-2"><?= e($error) ?></div>
                <?php endif; ?>
                <small class="text-muted">Generated server-side with <code>random_int()</code>.</small>
            </div>
        </div>

        <form method="get" action="/generator" class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Length: <strong><?= (int) $options['length'] ?></strong></label>
                    <input type="range" name="length" min="8" max="64" value="<?= (int) $options['length'] ?>" class="form-range">
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="upper" value="1" id="upper" <?= $opt('upper') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="upper">Uppercase (A-Z)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="lower" value="1" id="lower" <?= $opt('lower') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="lower">Lowercase (a-z)</label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="digits" value="1" id="digits" <?= $opt('digits') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="digits">Numbers (0-9)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="symbols" value="1" id="symbols" <?= $opt('symbols') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="symbols">Symbols</label>
                        </div>
                    </div>
                </div>
                <div class="form-check mt-2 mb-3">
                    <input class="form-check-input" type="checkbox" name="avoid_ambiguous" value="1" id="avoid_ambiguous" <?= $opt('avoid_ambiguous') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="avoid_ambiguous">Avoid ambiguous characters</label>
                </div>
                <button class="btn btn-primary" type="submit">Generate</button>
            </div>
        </form>
    </div>
</div>
