<?php
/** @var \SimpleVault\Models\Note $note @var string $renderedHtml */
use SimpleVault\Core\Csrf;
use SimpleVault\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h4 mb-1">
            <?= $note->favorite ? '<i class="bi bi-star-fill text-warning me-1"></i>' : '' ?><?= e($note->title()) ?>
            <?= View::renderPartial('partials/_status_badge', ['status' => $note->status()]) ?>
        </h1>
        <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
            <?php if ($note->ticket() !== ''): ?><span><i class="bi bi-ticket-perforated me-1"></i><strong id="note-ticket"><?= e($note->ticket()) ?></strong><button class="btn btn-sm btn-link p-0 ms-1 align-baseline" type="button" data-copy-target="#note-ticket" title="Copy ticket reference"><i class="bi bi-clipboard"></i></button></span><?php endif; ?>
            <?php if ($note->client() !== ''): ?><span><i class="bi bi-person me-1"></i><?= e($note->client()) ?></span><?php endif; ?>
            <?php if ($note->project() !== ''): ?><span><i class="bi bi-folder me-1"></i><?= e($note->project()) ?></span><?php endif; ?>
            <?= View::renderPartial('partials/_expiry_badge', ['date' => $note->expiresAt()]) ?>
        </div>
        <div class="mt-1">
            <?php foreach ($note->tags() as $tag): ?>
                <span class="badge tag-badge"><i class="bi bi-tag me-1"></i><?= e($tag) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="/notes/<?= e($note->uuid) ?>/edit"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes/<?= e($note->uuid) ?>/export-md"><i class="bi bi-download me-1"></i>Export .md</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes"><i class="bi bi-arrow-left me-1"></i>Back</a>
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
