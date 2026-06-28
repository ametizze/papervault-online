<?php
/** @var \SimpleVault\Models\Entry $entry @var \SimpleVault\Markdown\MarkdownPreviewService $markdown */
use SimpleVault\Core\Csrf;

$url = $entry->get('url');

// Render an expiry badge for a field, or '' when it has no expiry date.
$expiryBadge = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date . ' 23:59:59');
    if ($ts === false) {
        return '';
    }
    $days = (int) floor(($ts - time()) / 86400);
    if ($days < 0) {
        return '<span class="badge text-bg-danger">expired ' . e($date) . '</span>';
    }
    if ($days <= 14) {
        return '<span class="badge text-bg-warning">expires in ' . $days . 'd</span>';
    }
    return '<span class="badge text-bg-light text-muted">expires ' . e($date) . '</span>';
};
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $entry->favorite ? '★ ' : '' ?><?= e($entry->title()) ?></h1>
        <?php foreach ($entry->tags() as $tag): ?>
            <span class="badge bg-light text-dark tag-badge"><?= e($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="/entries/<?= e($entry->uuid) ?>/edit"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a class="btn btn-sm btn-outline-secondary" href="/entries"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card mb-3"><div class="card-body">
    <dl class="row mb-0">
        <?php if ($url !== ''): ?>
            <dt class="col-sm-3">Website</dt>
            <dd class="col-sm-9"><a href="<?= e(filter_var($url, FILTER_VALIDATE_URL) ? $url : '#') ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($url) ?></a></dd>
        <?php endif; ?>

        <dt class="col-sm-3">Username</dt>
        <dd class="col-sm-9">
            <span id="username"><?= e($entry->get('username')) ?></span>
            <button class="btn btn-sm btn-link p-0 ms-2" type="button" data-copy-target="#username">Copy</button>
        </dd>

        <dt class="col-sm-3">Password</dt>
        <dd class="col-sm-9">
            <div class="input-group" style="max-width: 420px;">
                <input type="password" id="password" class="form-control secret-field" value="<?= e($entry->get('password')) ?>" readonly>
                <button class="btn btn-outline-secondary" type="button" data-toggle-visibility="#password">Show</button>
                <button class="btn btn-outline-secondary" type="button" data-copy-target="#password">Copy</button>
            </div>
        </dd>

        <?php foreach ($entry->fields() as $i => $field): ?>
            <dt class="col-sm-3"><?= e($field['name']) ?> <span class="badge text-bg-light text-muted fw-normal"><?= e($field['type']) ?></span></dt>
            <dd class="col-sm-9">
                <?php if ($field['type'] === 'totp'): ?>
                    <div class="d-inline-flex align-items-center gap-2" data-totp data-totp-url="/entries/<?= e($entry->uuid) ?>/fields/<?= e($field['id']) ?>/totp">
                        <code class="fs-5" data-totp-code>······</code>
                        <span class="badge rounded-pill text-bg-secondary" data-totp-remaining title="seconds left">–</span>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-totp-copy>Copy</button>
                    </div>
                <?php elseif ($field['type'] === 'url' && filter_var($field['value'], FILTER_VALIDATE_URL)): ?>
                    <a href="<?= e($field['value']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($field['value']) ?></a>
                <?php elseif ($field['type'] === 'email' && $field['value'] !== ''): ?>
                    <a href="mailto:<?= e($field['value']) ?>"><?= e($field['value']) ?></a>
                <?php elseif ($field['secret']): ?>
                    <div class="input-group input-group-sm" style="max-width: 420px;">
                        <input type="password" id="cf<?= (int) $i ?>" class="form-control secret-field" value="<?= e($field['value']) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-toggle-visibility="#cf<?= (int) $i ?>">Show</button>
                        <button class="btn btn-outline-secondary" type="button" data-copy-target="#cf<?= (int) $i ?>">Copy</button>
                    </div>
                <?php else: ?>
                    <span class="md-inline"><?= $markdown->toInline($field['value']) /* sanitized */ ?></span>
                    <input type="hidden" id="cf<?= (int) $i ?>" value="<?= e($field['value']) ?>">
                    <button class="btn btn-sm btn-link p-0 ms-2" type="button" data-copy-target="#cf<?= (int) $i ?>">Copy</button>
                <?php endif; ?>
                <?php if ($badge = $expiryBadge($field['expiresAt'])): ?>
                    <span class="ms-2"><?= $badge /* pre-escaped */ ?></span>
                <?php endif; ?>
                <?php if ($field['observation'] !== ''): ?>
                    <div class="text-muted small mt-1 md-inline"><?= $markdown->toInline($field['observation']) /* sanitized */ ?></div>
                <?php endif; ?>
                <?php if (!empty($field['updatedAt'])): ?>
                    <div class="text-muted small">Updated <?= e($field['updatedAt']) ?></div>
                <?php endif; ?>
                <?php if ($field['history'] !== []): ?>
                    <details class="small mt-1">
                        <summary class="text-muted">History (<?= count($field['history']) ?>)</summary>
                        <ul class="list-unstyled mb-0 mt-1">
                            <?php foreach ($field['history'] as $j => $h): ?>
                                <li class="d-flex align-items-center gap-2 mb-1">
                                    <?php if ($field['secret']): ?>
                                        <input type="password" id="cfh<?= (int) $i ?>_<?= (int) $j ?>" class="form-control form-control-sm secret-field" style="max-width: 240px;" value="<?= e($h['value']) ?>" readonly>
                                        <button class="btn btn-sm btn-link p-0" type="button" data-toggle-visibility="#cfh<?= (int) $i ?>_<?= (int) $j ?>">Show</button>
                                        <button class="btn btn-sm btn-link p-0" type="button" data-copy-target="#cfh<?= (int) $i ?>_<?= (int) $j ?>">Copy</button>
                                    <?php else: ?>
                                        <code><?= e($h['value']) ?></code>
                                    <?php endif; ?>
                                    <span class="text-muted">&middot; <?= e($h['at']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </dd>
        <?php endforeach; ?>

        <?php if ($entry->get('client') !== '' || $entry->get('project') !== ''): ?>
            <dt class="col-sm-3">Client / Project</dt>
            <dd class="col-sm-9"><?= e(trim($entry->get('client') . ' / ' . $entry->get('project'), ' /')) ?></dd>
        <?php endif; ?>

        <?php if ($entry->get('notes') !== ''): ?>
            <dt class="col-sm-3">Notes</dt>
            <dd class="col-sm-9"><div class="markdown-body"><?= $markdown->toHtml($entry->get('notes')) /* sanitized */ ?></div></dd>
        <?php endif; ?>

        <dt class="col-sm-3">Updated</dt>
        <dd class="col-sm-9"><small class="text-muted"><?= e($entry->updatedAt) ?></small></dd>
    </dl>
</div></div>

<?php if ($entry->get('body') !== ''): ?>
    <div class="card mb-3">
        <div class="card-header py-2 small text-muted">Details / documentation</div>
        <div class="card-body markdown-body"><?= $markdown->toHtml($entry->get('body')) /* sanitized */ ?></div>
    </div>
<?php endif; ?>

<div class="d-flex gap-2">
    <form method="post" action="/entries/<?= e($entry->uuid) ?>/archive">
        <?= Csrf::field() ?>
        <?php if ($entry->archived): ?>
            <input type="hidden" name="unarchive" value="1">
            <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-archive me-1"></i>Archive</button>
        <?php endif; ?>
    </form>
    <form method="post" action="/entries/<?= e($entry->uuid) ?>/delete" data-confirm="Permanently delete this entry? This cannot be undone.">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Delete</button>
    </form>
</div>
