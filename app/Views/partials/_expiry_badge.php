<?php
/**
 * Expiry/due-date badge: red when past, amber within two weeks, otherwise
 * a quiet date chip. Renders nothing without a valid date.
 *
 * @var string|null $date  yyyy-mm-dd
 */
if (empty($date)) {
    return;
}
$ts = strtotime($date . ' 23:59:59');
if ($ts === false) {
    return;
}
$days = (int) floor(($ts - time()) / 86400);
if ($days < 0) {
    $cls = 'danger';
    $txt = 'expired ' . $date;
} elseif ($days <= 14) {
    $cls = 'warning';
    $txt = 'due in ' . $days . 'd';
} else {
    $cls = 'light';
    $txt = 'due ' . $date;
}
?>
<span class="badge text-bg-<?= $cls ?><?= $cls === 'light' ? ' text-muted' : '' ?>"><i class="bi bi-clock me-1"></i><?= e($txt) ?></span>
