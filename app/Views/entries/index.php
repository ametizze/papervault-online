<?php
/** @var array $entries @var bool $includeArchived */
use SimpleVault\Core\Csrf;
use SimpleVault\Models\Entry;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-key me-2"></i>Passwords</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="/entries<?= $includeArchived ? '' : '?archived=1' ?>">
            <i class="bi bi-archive me-1"></i><?= $includeArchived ? 'Hide archived' : 'Show archived' ?>
        </a>
        <a class="btn btn-sm btn-primary" href="/entries/create"><i class="bi bi-plus-lg me-1"></i>New password</a>
    </div>
</div>

<?php if ($entries === []): ?>
    <p class="text-muted">No password entries yet.</p>
<?php else: ?>
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" placeholder="Filter by title, username, client, project or tag…"
                       data-filter-target="#entries-tbody" aria-label="Filter entries">
            </div>
        </div>
    </div>

    <form method="post" action="/entries/bulk" id="entries-form">
        <?= Csrf::field() ?>

        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
            <span class="text-muted small me-1"><span data-selected-count>0</span> selected</span>
            <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="archive"><i class="bi bi-archive me-1"></i>Archive selected</button>
            <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="unarchive"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore selected</button>
            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete"
                    data-confirm="Permanently delete the selected entries? This cannot be undone."><i class="bi bi-trash me-1"></i>Delete selected</button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 2.5rem;">
                            <input type="checkbox" class="form-check-input" data-check-all="#entries-form" aria-label="Select all">
                        </th>
                        <th></th>
                        <th>Title</th>
                        <th>Username</th>
                        <th>Client / Project</th>
                        <th>Tags</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="entries-tbody">
                <?php foreach ($entries as $entry): /** @var Entry $entry */
                    $search = strtolower(implode(' ', array_filter([
                        $entry->title(),
                        $entry->get('username'),
                        $entry->get('client'),
                        $entry->get('project'),
                        implode(' ', $entry->tags()),
                    ])));
                ?>
                    <tr data-search="<?= e($search) ?>"<?= $entry->archived ? ' class="text-muted"' : '' ?>>
                        <td>
                            <input type="checkbox" class="form-check-input" name="uuids[]"
                                   value="<?= e($entry->uuid) ?>" data-row-check aria-label="Select entry">
                        </td>
                        <td><?= $entry->favorite ? '<i class="bi bi-star-fill text-warning"></i>' : '' ?></td>
                        <td>
                            <a href="/entries/<?= e($entry->uuid) ?>"><?= e($entry->title()) ?></a>
                            <?= $entry->archived ? '<span class="badge text-bg-secondary">archived</span>' : '' ?>
                        </td>
                        <td><?= e($entry->get('username')) ?></td>
                        <td><?= e(trim($entry->get('client') . ' / ' . $entry->get('project'), ' /')) ?></td>
                        <td>
                            <?php foreach ($entry->tags() as $tag): ?>
                                <span class="badge tag-badge"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><small><?= e(substr($entry->updatedAt, 0, 10)) ?></small></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-link p-1" type="button"
                                    data-copy-fetch="/entries/<?= e($entry->uuid) ?>/copy"
                                    title="Copy password to clipboard"><i class="bi bi-clipboard"></i></button>
                            <a class="btn btn-sm btn-link p-1" href="/entries/<?= e($entry->uuid) ?>/edit" title="Edit"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-sm btn-link p-1" type="submit"
                                    formaction="/entries/<?= e($entry->uuid) ?>/duplicate" formmethod="post" title="Duplicate"><i class="bi bi-files"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>
