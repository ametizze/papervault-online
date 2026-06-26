<?php
/** @var \SimpleVault\Models\Note $note @var array $errors @var array $old */
use SimpleVault\Core\View;
?>
<h1 class="h3 mb-4">Edit note</h1>
<div class="card"><div class="card-body">
<?= View::renderPartial('notes/_form', [
    'old' => $old,
    'errors' => $errors,
    'action' => '/notes/' . $note->uuid . '/update',
]) ?>
</div></div>
