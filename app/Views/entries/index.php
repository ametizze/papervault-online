<?php
/** @var array $entries @var bool $includeArchived */
use SimpleVault\Models\Entry;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Passwords</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="/entries<?= $includeArchived ? '' : '?archived=1' ?>">
            <?= $includeArchived ? 'Hide archived' : 'Show archived' ?>
        </a>
        <a class="btn btn-sm btn-primary" href="/entries/create">New password</a>
    </div>
</div>

<?php if ($entries === []): ?>
    <p class="text-muted">No password entries yet.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white">
            <thead>
                <tr>
                    <th></th>
                    <th>Title</th>
                    <th>Username</th>
                    <th>Client / Project</th>
                    <th>Tags</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): /** @var Entry $entry */ ?>
                <tr<?= $entry->archived ? ' class="text-muted"' : '' ?>>
                    <td><?= $entry->favorite ? '★' : '' ?></td>
                    <td><a href="/entries/<?= e($entry->uuid) ?>"><?= e($entry->title()) ?></a>
                        <?= $entry->archived ? '<span class="badge bg-secondary">archived</span>' : '' ?></td>
                    <td><?= e($entry->get('username')) ?></td>
                    <td><?= e(trim($entry->get('client') . ' / ' . $entry->get('project'), ' /')) ?></td>
                    <td>
                        <?php foreach ($entry->tags() as $tag): ?>
                            <span class="badge bg-light text-dark tag-badge"><?= e($tag) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><small><?= e(substr($entry->updatedAt, 0, 10)) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
