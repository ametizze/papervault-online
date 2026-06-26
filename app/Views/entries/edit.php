<?php
/** @var \SimpleVault\Models\Entry $entry @var array $errors @var array $old */
use SimpleVault\Core\View;
?>
<h1 class="h3 mb-4">Edit password</h1>
<div class="card"><div class="card-body">
<?= View::renderPartial('entries/_form', [
    'old' => $old,
    'errors' => $errors,
    'action' => '/entries/' . $entry->uuid . '/update',
    'suggestedPassword' => null,
]) ?>
</div></div>
