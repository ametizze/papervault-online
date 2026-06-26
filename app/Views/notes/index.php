<?php
/** @var array $notes @var bool $includeArchived */
use SimpleVault\Core\Csrf;
use SimpleVault\Models\Note;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
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
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <input type="search" class="form-control" placeholder="Filter by title, client, project or tag…"
                   data-filter-target="#notes-tbody" aria-label="Filter notes">
        </div>
    </div>

    <form method="post" action="/notes/bulk" id="notes-form">
        <?= Csrf::field() ?>

        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
            <span class="text-muted small me-1"><span data-selected-count>0</span> selected</span>
            <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="archive">Archive selected</button>
            <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="unarchive">Restore selected</button>
            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete"
                    data-confirm="Permanently delete the selected notes? This cannot be undone.">Delete selected</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
                <thead>
                    <tr>
                        <th style="width: 2.5rem;">
                            <input type="checkbox" class="form-check-input" data-check-all="#notes-form" aria-label="Select all">
                        </th>
                        <th></th>
                        <th>Title</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Tags</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="notes-tbody">
                <?php foreach ($notes as $note): /** @var Note $note */
                    $search = strtolower(implode(' ', array_filter([
                        $note->title(),
                        $note->client(),
                        $note->project(),
                        implode(' ', $note->tags()),
                    ])));
                ?>
                    <tr data-search="<?= e($search) ?>"<?= $note->archived ? ' class="text-muted"' : '' ?>>
                        <td>
                            <input type="checkbox" class="form-check-input" name="uuids[]"
                                   value="<?= e($note->uuid) ?>" data-row-check aria-label="Select note">
                        </td>
                        <td><?= $note->favorite ? '★' : '' ?></td>
                        <td>
                            <a href="/notes/<?= e($note->uuid) ?>"><?= e($note->title()) ?></a>
                            <?= $note->archived ? '<span class="badge bg-secondary">archived</span>' : '' ?>
                        </td>
                        <td><?= e($note->client()) ?></td>
                        <td><?= e($note->project()) ?></td>
                        <td>
                            <?php foreach ($note->tags() as $tag): ?>
                                <span class="badge bg-light text-dark tag-badge"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><small><?= e(substr($note->updatedAt, 0, 10)) ?></small></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-link p-0 me-2" href="/notes/<?= e($note->uuid) ?>/edit">Edit</a>
                            <button class="btn btn-sm btn-link p-0" type="submit"
                                    formaction="/notes/<?= e($note->uuid) ?>/duplicate" formmethod="post">Duplicate</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>
