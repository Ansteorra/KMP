<?php
$safeQuery = preg_quote((string)$q, '/');
foreach ($gatherings as $gatheringId => $displayName) :
    $isCancelled = in_array((int)$gatheringId, $cancelledGatheringIds ?? [], true);
    $isSelectedCancelled = $isCancelled && ((int)$selectedId === (int)$gatheringId);
    $classes = $isCancelled && !$isSelectedCancelled ? 'disabled text-danger' : '';
    $rendered = h((string)$displayName);
    if (!empty($q)) {
        $rendered = preg_replace('/(' . $safeQuery . ')/i', '<span class="text-primary">$1</span>', $rendered);
    }
?>
    <li class="list-group-item <?= $classes ?>" role="option"
        aria-disabled="<?= $isCancelled && !$isSelectedCancelled ? 'true' : 'false' ?>"
        data-ac-value="<?= h((string)$gatheringId) ?>">
        <?= $rendered ?>
    </li>
<?php endforeach; ?>
