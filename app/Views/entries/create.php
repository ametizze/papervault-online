<?php
/** @var array $errors @var array $old @var string $suggestedPassword */
use SimpleVault\Core\View;
?>
<h1 class="h3 mb-4">New password</h1>
<div class="card"><div class="card-body">
<?= View::renderPartial('entries/_form', [
    'old' => $old,
    'errors' => $errors,
    'action' => '/entries',
    'suggestedPassword' => $suggestedPassword,
]) ?>
</div></div>
