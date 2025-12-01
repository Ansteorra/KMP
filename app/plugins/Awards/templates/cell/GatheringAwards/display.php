<?php

/**
 * Gathering Awards Cell Display Template
 *
 * Displays recommendations scheduled for a gathering using the dv_grid element.
 *
 * @var \App\View\AppView $this
 * @var int|null $gatheringId The ID of the gathering (may be null if permission denied)
 * @var bool $isEmpty Whether the gathering has any recommendations
 */

// If we don't have a gathering ID, permission was denied or gathering not found
if (!isset($gatheringId)) {
    echo '<p class="text-muted">' . __('Unable to load award recommendations for this gathering.') . '</p>';
    return;
}

$frameId = 'gathering-awards-grid-' . $gatheringId;
?>

<?php if (!$isEmpty): ?>
    <?= $this->element('dv_grid', [
        'gridKey' => 'Awards.Recommendations.gathering.' . $gatheringId,
        'frameId' => $frameId,
        'dataUrl' => $this->Url->build([
            'plugin' => 'Awards',
            'controller' => 'Recommendations',
            'action' => 'gatheringAwardsGridData',
            $gatheringId,
        ]),
        'compactMode' => true,
    ]) ?>
<?php else: ?>
    <p class="text-muted"><?= __('No Award Recommendations for this gathering') ?></p>
<?php endif; ?>