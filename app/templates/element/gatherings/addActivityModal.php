<?php

/**
 * Add Activity Modal Element
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\GatheringActivity[] $availableActivities
 */
?>
<?= $this->Form->create(null, [
    'id' => 'add_activity_form',
    'type' => 'post',
    'url' => [
        'controller' => 'Gatherings',
        'action' => 'add-activity',
        $gathering->id,
    ],
]) ?>

<?php echo $this->Modal->create(__('Add Activities to {0}', $gathering->name), [
    'id' => 'addActivityModal',
    'close' => true,
    'size' => 'lg'
]); ?>

<div class="mb-3">
    <p class="text-muted">
        <?= __('Select one or more activities to add to this gathering.') ?>
    </p>

    <?php if (!empty($availableActivities)): ?>
        <div class="list-group">
            <?php foreach ($availableActivities as $activity): ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-start">
                        <div class="form-check">
                            <?= $this->Form->checkbox('activity_ids[]', [
                                'value' => $activity->id,
                                'id' => 'modal-activity-' . $activity->id,
                                'class' => 'form-check-input',
                                'hiddenField' => false,
                                'data-controller' => 'activity-toggle',
                                'data-action' => 'change->activity-toggle#toggleDescription',
                                'data-activity-toggle-target' => 'checkbox'
                            ]) ?>
                        </div>
                        <div class="ms-2 flex-grow-1">
                            <label for="modal-activity-<?= $activity->id ?>">
                                <strong><?= h($activity->name) ?></strong>
                            </label>
                            <?php if (!empty($activity->description)): ?>
                                <div class="text-muted small mb-2">
                                    <i class="bi bi-info-circle"></i> <?= __('Default:') ?> <?= h($activity->description) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Custom description field -->
                            <div class="mt-2" data-controller="activity-toggle" data-activity-toggle-target="descriptionContainer">
                                <label for="custom-description-<?= $activity->id ?>" class="form-label small">
                                    <?= __('Custom Description (optional)') ?>
                                </label>
                                <?= $this->Form->textarea('custom_descriptions[' . $activity->id . ']', [
                                    'id' => 'custom-description-' . $activity->id,
                                    'class' => 'form-control form-control-sm',
                                    'rows' => 2,
                                    'placeholder' => __('Override the default description for this gathering'),
                                    'disabled' => true,
                                    'data-activity-toggle-target' => 'descriptionField'
                                ]) ?>
                                <small class="form-text text-muted">
                                    <?= __('Example: "Baronial Championship" instead of "Open practice"') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <?= __('All available activities have already been added to this gathering.') ?>
        </div>
    <?php endif; ?>
</div>

<?php echo $this->Modal->end([
    $this->Form->button(__('Add Selected Activities'), [
        'class' => 'btn btn-primary',
        'id' => 'add_activity_submit',
        'disabled' => empty($availableActivities)
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'class' => 'btn btn-secondary'
    ]),
]);
?>
<?= $this->Form->end() ?>