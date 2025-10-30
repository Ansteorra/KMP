<?php

/**
 * Edit Activity Description Modal Element
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<?= $this->Form->create(null, [
    'id' => 'edit_activity_description_form',
    'type' => 'post',
    'url' => [
        'controller' => 'Gatherings',
        'action' => 'edit-activity-description',
        $gathering->id,
    ],
    'data-controller' => 'edit-activity-description'
]) ?>

<?php echo $this->Modal->create(__('Edit Activity Description'), [
    'id' => 'editActivityDescriptionModal',
    'close' => true,
    'size' => 'lg'
]); ?>

<div class="mb-3">
    <input type="hidden"
        name="activity_id"
        id="edit-activity-id"
        data-edit-activity-description-target="activityId">

    <div class="mb-3">
        <label class="form-label">
            <strong><?= __('Activity Name') ?></strong>
        </label>
        <div id="edit-activity-name"
            class="form-control-plaintext"
            data-edit-activity-description-target="activityName">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label text-muted small">
            <i class="bi bi-info-circle"></i> <?= __('Default Description') ?>
        </label>
        <div id="edit-default-description"
            class="form-control-plaintext text-muted small"
            data-edit-activity-description-target="defaultDescription">
        </div>
    </div>

    <div class="mb-3">
        <label for="custom-description-input" class="form-label">
            <?= __('Custom Description for This Gathering') ?>
        </label>
        <?= $this->Form->textarea('custom_description', [
            'id' => 'custom-description-input',
            'class' => 'form-control',
            'rows' => 4,
            'placeholder' => __('Enter a custom description to override the default for this specific gathering. Leave blank to use the default description.'),
            'data-edit-activity-description-target' => 'customDescription'
        ]) ?>
        <small class="form-text text-muted">
            <?= __('Example: Change "Open practice" to "Baronial Championship" for this specific gathering.') ?>
        </small>
    </div>
</div>

<?php echo $this->Modal->end([
    $this->Form->button(__('Save Description'), [
        'class' => 'btn btn-primary',
        'id' => 'save_description_button'
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'class' => 'btn btn-secondary'
    ]),
]);
?>
<?= $this->Form->end() ?>