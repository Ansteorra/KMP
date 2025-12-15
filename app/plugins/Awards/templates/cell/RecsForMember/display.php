<?php

/**
 * Recommendations For Member Cell Display Template
 *
 * Displays recommendations received by a member using the dv_grid element.
 *
 * @var \App\View\AppView $this
 * @var bool $isEmpty Whether the member has received any recommendations
 * @var int $id The member ID
 */

$frameId = 'recs-for-member-grid-' . $id;
?>

<?php if (!$isEmpty): ?>
    <?= $this->element('dv_grid', [
        'gridKey' => 'Awards.Recommendations.forMember.' . $id,
        'frameId' => $frameId,
        'dataUrl' => $this->Url->build([
            'plugin' => 'Awards',
            'controller' => 'Recommendations',
            'action' => 'recsForMemberGridData',
            $id,
        ]),
        'compactMode' => true,
    ]) ?>
<?php else: ?>
    <p class="text-muted"><?= __('No Award Recommendations for this member') ?></p>
<?php endif; ?>