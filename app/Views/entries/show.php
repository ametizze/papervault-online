<?php
/** @var \SimpleVault\Models\Entry $entry */
use SimpleVault\Core\Csrf;

$url = $entry->get('url');
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $entry->favorite ? '★ ' : '' ?><?= e($entry->title()) ?></h1>
        <?php foreach ($entry->tags() as $tag): ?>
            <span class="badge bg-light text-dark tag-badge"><?= e($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="/entries/<?= e($entry->uuid) ?>/edit">Edit</a>
        <a class="btn btn-sm btn-outline-secondary" href="/entries">Back</a>
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

        <?php if ($entry->get('client') !== '' || $entry->get('project') !== ''): ?>
            <dt class="col-sm-3">Client / Project</dt>
            <dd class="col-sm-9"><?= e(trim($entry->get('client') . ' / ' . $entry->get('project'), ' /')) ?></dd>
        <?php endif; ?>

        <?php if ($entry->get('notes') !== ''): ?>
            <dt class="col-sm-3">Notes</dt>
            <dd class="col-sm-9"><pre class="mb-0" style="white-space: pre-wrap;"><?= e($entry->get('notes')) ?></pre></dd>
        <?php endif; ?>

        <dt class="col-sm-3">Updated</dt>
        <dd class="col-sm-9"><small class="text-muted"><?= e($entry->updatedAt) ?></small></dd>
    </dl>
</div></div>

<div class="d-flex gap-2">
    <form method="post" action="/entries/<?= e($entry->uuid) ?>/archive">
        <?= Csrf::field() ?>
        <?php if ($entry->archived): ?>
            <input type="hidden" name="unarchive" value="1">
            <button class="btn btn-sm btn-outline-success" type="submit">Restore</button>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-warning" type="submit">Archive</button>
        <?php endif; ?>
    </form>
    <form method="post" action="/entries/<?= e($entry->uuid) ?>/delete" data-confirm="Permanently delete this entry? This cannot be undone.">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
    </form>
</div>
