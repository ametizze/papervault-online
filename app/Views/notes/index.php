<?php
/** @var array $notes @var bool $includeArchived */
use SimpleVault\Models\Note;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Notes</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="/notes/import">Import</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes/export">Export</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes<?= $includeArchived ? '' : '?archived=1' ?>">
            <?= $includeArchived ? 'Hide archived' : 'Show archived' ?>
        </a>
        <a class="btn btn-sm btn-primary" href="/notes/create">New note</a>
    </div>
</div>

<?php if ($notes === []): ?>
    <p class="text-muted">No notes yet.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white">
            <thead>
                <tr>
                    <th></th>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Project</th>
                    <th>Tags</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($notes as $note): /** @var Note $note */ ?>
                <tr<?= $note->archived ? ' class="text-muted"' : '' ?>>
                    <td><?= $note->favorite ? '★' : '' ?></td>
                    <td><a href="/notes/<?= e($note->uuid) ?>"><?= e($note->title()) ?></a>
                        <?= $note->archived ? '<span class="badge bg-secondary">archived</span>' : '' ?></td>
                    <td><?= e($note->client()) ?></td>
                    <td><?= e($note->project()) ?></td>
                    <td>
                        <?php foreach ($note->tags() as $tag): ?>
                            <span class="badge bg-light text-dark tag-badge"><?= e($tag) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><small><?= e(substr($note->updatedAt, 0, 10)) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
