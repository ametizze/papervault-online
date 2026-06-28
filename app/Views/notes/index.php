<?php
/** @var array $notes @var bool $includeArchived */
use SimpleVault\Core\Csrf;
use SimpleVault\Core\View;
use SimpleVault\Models\Note;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-text me-2"></i>Notes</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="/notes/import"><i class="bi bi-upload me-1"></i>Import</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes/export"><i class="bi bi-download me-1"></i>Export</a>
        <a class="btn btn-sm btn-outline-secondary" href="/notes<?= $includeArchived ? '' : '?archived=1' ?>">
            <i class="bi bi-archive me-1"></i><?= $includeArchived ? 'Hide archived' : 'Show archived' ?>
        </a>
        <a class="btn btn-sm btn-primary" href="/notes/create"><i class="bi bi-plus-lg me-1"></i>New note</a>
    </div>
</div>

<?php if ($notes === []): ?>
    <p class="text-muted">No notes yet.</p>
<?php else: ?>
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" placeholder="Filter by title, ticket, client, status or tag…"
                       data-filter-target="#notes-tbody" aria-label="Filter notes">
            </div>
        </div>
    </div>

    <form method="post" action="/notes/bulk" id="notes-form">
        <?= Csrf::field() ?>

        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
            <span class="text-muted small me-1"><span data-selected-count>0</span> selected</span>
            <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="archive"><i class="bi bi-archive me-1"></i>Archive selected</button>
            <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="unarchive"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore selected</button>
            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete"
                    data-confirm="Permanently delete the selected notes? This cannot be undone."><i class="bi bi-trash me-1"></i>Delete selected</button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 2.5rem;">
                            <input type="checkbox" class="form-check-input" data-check-all="#notes-form" aria-label="Select all">
                        </th>
                        <th></th>
                        <th>Title</th>
                        <th>Ticket</th>
                        <th>Client / Project</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th>Tags</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="notes-tbody">
                <?php foreach ($notes as $note): /** @var Note $note */
                    $search = strtolower(implode(' ', array_filter([
                        $note->title(),
                        $note->ticket(),
                        $note->client(),
                        $note->project(),
                        $note->statusLabel(),
                        implode(' ', $note->tags()),
                    ])));
                ?>
                    <tr data-search="<?= e($search) ?>"<?= $note->archived ? ' class="text-muted"' : '' ?>>
                        <td>
                            <input type="checkbox" class="form-check-input" name="uuids[]"
                                   value="<?= e($note->uuid) ?>" data-row-check aria-label="Select note">
                        </td>
                        <td><?= $note->favorite ? '<i class="bi bi-star-fill text-warning"></i>' : '' ?></td>
                        <td>
                            <a href="/notes/<?= e($note->uuid) ?>"><?= e($note->title()) ?></a>
                            <?= $note->archived ? '<span class="badge text-bg-secondary">archived</span>' : '' ?>
                        </td>
                        <td class="text-nowrap"><?= $note->ticket() !== '' ? '<span class="badge tag-badge"><i class="bi bi-ticket-perforated me-1"></i>' . e($note->ticket()) . '</span>' : '' ?></td>
                        <td><?= e(trim($note->client() . ' / ' . $note->project(), ' /')) ?></td>
                        <td><?= View::renderPartial('partials/_status_badge', ['status' => $note->status()]) ?></td>
                        <td><?= View::renderPartial('partials/_expiry_badge', ['date' => $note->expiresAt()]) ?></td>
                        <td>
                            <?php foreach ($note->tags() as $tag): ?>
                                <span class="badge tag-badge"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><small><?= e(substr($note->updatedAt, 0, 10)) ?></small></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-link p-1" type="button"
                                    data-copy-fetch="/notes/<?= e($note->uuid) ?>/copy"
                                    title="Copy Markdown to clipboard"><i class="bi bi-clipboard"></i></button>
                            <a class="btn btn-sm btn-link p-1" href="/notes/<?= e($note->uuid) ?>/edit" title="Edit"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-sm btn-link p-1" type="submit"
                                    formaction="/notes/<?= e($note->uuid) ?>/duplicate" formmethod="post" title="Duplicate"><i class="bi bi-files"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>
