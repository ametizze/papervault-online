<?php
/**
 * Ticket status badge. Renders nothing for an empty/unknown status.
 *
 * @var string $status  one of SimpleVault\Models\Note::STATUSES keys
 */
use SimpleVault\Models\Note;

$colors = ['open' => 'primary', 'in-progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'];
if (!isset(Note::STATUSES[$status])) {
    return;
}
?>
<span class="badge text-bg-<?= $colors[$status] ?? 'secondary' ?>"><?= e(Note::STATUSES[$status]) ?></span>
