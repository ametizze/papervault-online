<?php
use SimpleVault\Core\Csrf;
?>
<h1 class="h3 mb-3">Import notes from Markdown</h1>
<p class="text-muted">Imported notes are encrypted before saving. Optional front matter
(<code>title</code>, <code>client</code>, <code>project</code>, <code>tags</code>) is parsed when present.</p>

<div class="row g-4">
    <div class="col-md-6">
        <form method="post" action="/notes/import" enctype="multipart/form-data" class="card h-100">
            <div class="card-body">
                <?= Csrf::field() ?>
                <h2 class="h6">Markdown files</h2>
                <p class="text-muted small">Select one or more <code>.md</code> files.</p>
                <input type="file" name="md_files[]" class="form-control mb-3" accept=".md,.markdown,.txt" multiple required>
                <button class="btn btn-primary" type="submit">Import files</button>
            </div>
        </form>
    </div>
    <div class="col-md-6">
        <form method="post" action="/notes/import" enctype="multipart/form-data" class="card h-100">
            <div class="card-body">
                <?= Csrf::field() ?>
                <h2 class="h6">ZIP archive</h2>
                <p class="text-muted small">A ZIP containing <code>.md</code> files.
                    Max <?= (int) config('max_import_files') ?> files.</p>
                <input type="file" name="zip_file" class="form-control mb-3" accept=".zip,application/zip" required>
                <button class="btn btn-primary" type="submit">Import ZIP</button>
            </div>
        </form>
    </div>
</div>
