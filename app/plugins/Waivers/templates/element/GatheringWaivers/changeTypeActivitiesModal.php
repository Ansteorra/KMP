<?php

/**
 * Change Waiver Type Modal
 *
 * Allows authorized users to change the waiver type for an already uploaded waiver.
 *
 * @var \App\View\AppView $this
 * @var \Waivers\Model\Entity\GatheringWaiver $gatheringWaiver
 * @var array $waiverTypes List of waiver types (id => name)
 */
?>

<!-- Change Type/Activities Modal -->
<div class="modal fade" id="changeTypeActivitiesModal" tabindex="-1" aria-labelledby="changeTypeActivitiesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTypeActivitiesModalLabel">
                    <i class="bi bi-pencil-square"></i> <?= __('Change Waiver Type') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <?= $this->Form->create($gatheringWaiver, [
                'url' => ['action' => 'changeTypeActivities', $gatheringWaiver->id],
                'id' => 'changeTypeActivitiesForm'
            ]) ?>

            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong><?= __('Warning:') ?></strong>
                    <?= __('Changing the waiver type or activity associations should be done carefully. This action affects which activities this waiver fulfills requirements for.') ?>
                </div>

                <!-- Waiver Type Selection -->
                <div class="mb-3">
                    <label for="waiver-type-select" class="form-label">
                        <i class="bi bi-file-earmark-text"></i> <?= __('Waiver Type') ?>
                        <span class="text-danger">*</span>
                    </label>
                    <?= $this->Form->control('waiver_type_id', [
                        'type' => 'select',
                        'options' => $waiverTypes,
                        'class' => 'form-select',
                        'id' => 'waiver-type-select',
                        'label' => false,
                        'required' => true,
                        'empty' => __('-- Select Waiver Type --')
                    ]) ?>
                    <div class="form-text">
                        <?= __('Select the type of waiver that this document represents.') ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> <?= __('Cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> <?= __('Save Changes') ?>
                </button>
            </div>

            <?= $this->Form->end() ?>
        </div>
    </div>
</div>