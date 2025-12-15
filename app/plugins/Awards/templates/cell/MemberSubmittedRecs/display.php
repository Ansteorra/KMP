<?php

/**
 * Member Submitted Recommendations Cell Display Template
 *
 * Displays recommendations submitted by a member using the dv_grid element.
 *
 * @var \App\View\AppView $this
 * @var bool $isEmpty Whether the member has submitted any recommendations
 * @var int $id The member ID
 */

$frameId = 'member-submitted-recs-grid-' . $id;
?>

<div class="mb-3">
    <?= $this->Html->link(
        '<i class="bi bi-plus-circle"></i> ' . __("Submit Award Rec."),
        ['controller' => 'Recommendations', 'action' => 'add', 'plugin' => 'Awards'],
        ['class' => 'btn btn-primary btn-sm', 'escape' => false]
    ) ?>
</div>

<?php if (!$isEmpty): ?>
    <?= $this->element('dv_grid', [
        'gridKey' => 'Awards.Recommendations.memberSubmitted.' . $id,
        'frameId' => $frameId,
        'dataUrl' => $this->Url->build([
            'plugin' => 'Awards',
            'controller' => 'Recommendations',
            'action' => 'memberSubmittedRecsGridData',
            $id,
        ]),
        'compactMode' => true,
    ]) ?>
<?php else: ?>
    <p class="text-muted"><?= __('No Award Recommendations submitted') ?></p>
<?php endif; ?>