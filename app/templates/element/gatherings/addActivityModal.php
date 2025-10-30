<?php

/**
 * Add Activity Modal Element
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\GatheringActivity[] $availableActivities
 */

// Build activities list for dropdown
$activitiesForDropdown = [];
foreach ($availableActivities as $activity) {
    $activitiesForDropdown[$activity->id] = $activity->name;
}
?>
<?= $this->Form->create(null, [
    'id' => 'add_activity_form',
    'type' => 'post',
    'url' => [
        'controller' => 'Gatherings',
        'action' => 'add-activity',
        $gathering->id,
    ],
    'data-controller' => 'add-activity-modal'
]) ?>

<?php echo $this->Modal->create(__('Add Activity to {0}', $gathering->name), [
    'id' => 'addActivityModal',
    'close' => true,
    'size' => 'lg'
]); ?>

<div class="mb-3">
    <?php if (!empty($availableActivities)): ?>
        <div class="mb-3">
            <label for="activity-id" class="form-label">
                <?= __('Select Activity') ?>
            </label>
            <?= $this->Form->select('activity_id', $activitiesForDropdown, [
                'id' => 'activity-id',
                'class' => 'form-select',
                'empty' => __('-- Choose an activity --'),
                'required' => true,
                'data-add-activity-modal-target' => 'activitySelect',
                'data-action' => 'change->add-activity-modal#updateDefaultDescription'
            ]) ?>
        </div>

        <div class="mb-3">
            <label class="form-label text-muted small">
                <i class="bi bi-info-circle"></i> <?= __('Default Description') ?>
            </label>
            <div id="default-description-display"
                class="form-control-plaintext text-muted small"
                data-add-activity-modal-target="defaultDescription">
                <?= __('Select an activity to see its default description') ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="custom-description" class="form-label">
                <?= __('Custom Description for This Gathering') ?>
            </label>
            <?= $this->Form->textarea('custom_description', [
                'id' => 'custom-description',
                'class' => 'form-control',
                'rows' => 4,
                'placeholder' => __('Enter a custom description to override the default for this specific gathering. Leave blank to use the default description.'),
                'data-add-activity-modal-target' => 'customDescription'
            ]) ?>
            <small class="form-text text-muted">
                <?= __('Example: Change "Open practice" to "Baronial Championship" for this specific gathering.') ?>
            </small>
        </div>

        <!-- Hidden data attributes for activity descriptions -->
        <?php foreach ($availableActivities as $activity): ?>
            <input type="hidden"
                data-activity-id="<?= $activity->id ?>"
                data-activity-description="<?= h($activity->description ?? '') ?>"
                data-add-activity-modal-target="activityData">
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <?= __('All available activities have already been added to this gathering.') ?>
        </div>
    <?php endif; ?>
</div>

<?php echo $this->Modal->end([
    $this->Form->button(__('Add Activity'), [
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