<?php
/** @var \SimpleVault\Models\Note $note @var string $renderedHtml */
use SimpleVault\Core\Csrf;
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><?= $note->favorite ? '★ ' : '' ?><?= e($note->title()) ?></h1>
        <div class="text-muted">
            <?php if ($note->client() !== ''): ?>Client: <strong><?= e($note->client()) ?></strong><?php endif; ?>
            <?php if ($note->project() !== ''): ?> &middot; Project: <strong><?= e($note->project()) ?></strong><?php endif; ?>
        </div>
        <div class="mt-1">
            <?php foreach ($note->tags() as $tag): ?>
                <span class="badge bg-light text-dark tag-badge"><?= e($tag) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="/notes/<?= e($note->uuid) ?>/edit">Edit</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes/<?= e($note->uuid) ?>/export-md">Export .md</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes">Back</a>
    </div>
</div>

<div class="card mb-3"><div class="card-body markdown-body">
    <?= $renderedHtml /* already sanitized by MarkdownPreviewService */ ?>
</div></div>

<div class="d-flex gap-2">
    <form method="post" action="/notes/<?= e($note->uuid) ?>/archive">
        <?= Csrf::field() ?>
        <?php if ($note->archived): ?>
            <input type="hidden" name="unarchive" value="1">
            <button class="btn btn-sm btn-outline-success" type="submit">Restore</button>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-warning" type="submit">Archive</button>
        <?php endif; ?>
    </form>
    <form method="post" action="/notes/<?= e($note->uuid) ?>/delete" data-confirm="Permanently delete this note? This cannot be undone.">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
    </form>
</div>
