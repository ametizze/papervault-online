<?php
/** @var array $notes */
use SimpleVault\Core\Csrf;
use SimpleVault\Models\Note;

$clients = [];
$projects = [];
foreach ($notes as $n) { /** @var Note $n */
    if ($n->client() !== '') { $clients[$n->client()] = true; }
    if ($n->project() !== '') { $projects[$n->project()] = true; }
}
?>
<h1 class="h3 mb-3">Export notes as Markdown</h1>

<div class="card warning-banner mb-4"><div class="card-body">
    <strong>Plaintext warning.</strong>
    Markdown export creates plaintext files. Anyone with access to these files can read your notes.
    For a secure backup, use the <a href="/export">encrypted full-vault backup</a> instead.
</div></div>

<form method="post" action="/notes/export" class="card mb-4"><div class="card-body">
    <?= Csrf::field() ?>
    <fieldset class="mb-3">
        <legend class="h6">What to export</legend>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="scope_all" value="all" checked>
            <label class="form-check-label" for="scope_all">All notes (ZIP)</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="scope_client" value="client">
            <label class="form-check-label" for="scope_client">By client</label>
            <select name="client" class="form-select form-select-sm mt-1" style="max-width: 320px;">
                <?php foreach (array_keys($clients) as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="scope_project" value="project">
            <label class="form-check-label" for="scope_project">By project</label>
            <select name="project" class="form-select form-select-sm mt-1" style="max-width: 320px;">
                <?php foreach (array_keys($projects) as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="scope_single" value="single">
            <label class="form-check-label" for="scope_single">A single note</label>
            <select name="uuid" class="form-select form-select-sm mt-1" style="max-width: 320px;">
                <?php foreach ($notes as $n): ?><option value="<?= e($n->uuid) ?>"><?= e($n->title()) ?></option><?php endforeach; ?>
            </select>
        </div>
    </fieldset>
    <button class="btn btn-primary" type="submit" data-confirm="Export plaintext Markdown? These files are NOT encrypted.">Export</button>
</div></form>
