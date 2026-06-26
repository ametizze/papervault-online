<?php
/** @var array $entries @var bool $includeArchived */
use SimpleVault\Core\Csrf;
use SimpleVault\Models\Entry;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
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
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <input type="search" class="form-control" placeholder="Filter by title, username, client, project or tag…"
                   data-filter-target="#entries-tbody" aria-label="Filter entries">
        </div>
    </div>

    <form method="post" action="/entries/bulk" id="entries-form">
        <?= Csrf::field() ?>

        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
            <span class="text-muted small me-1"><span data-selected-count>0</span> selected</span>
            <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="archive">Archive selected</button>
            <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="unarchive">Restore selected</button>
            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete"
                    data-confirm="Permanently delete the selected entries? This cannot be undone.">Delete selected</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
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
                        <td><?= $entry->favorite ? '★' : '' ?></td>
                        <td>
                            <a href="/entries/<?= e($entry->uuid) ?>"><?= e($entry->title()) ?></a>
                            <?= $entry->archived ? '<span class="badge bg-secondary">archived</span>' : '' ?>
                        </td>
                        <td><?= e($entry->get('username')) ?></td>
                        <td><?= e(trim($entry->get('client') . ' / ' . $entry->get('project'), ' /')) ?></td>
                        <td>
                            <?php foreach ($entry->tags() as $tag): ?>
                                <span class="badge bg-light text-dark tag-badge"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><small><?= e(substr($entry->updatedAt, 0, 10)) ?></small></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-link p-0 me-2" href="/entries/<?= e($entry->uuid) ?>/edit">Edit</a>
                            <button class="btn btn-sm btn-link p-0" type="submit"
                                    formaction="/entries/<?= e($entry->uuid) ?>/duplicate" formmethod="post">Duplicate</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>
