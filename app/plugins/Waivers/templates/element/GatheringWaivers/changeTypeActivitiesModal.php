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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTypeActivitiesModalLabel">
                    <kmp-icon name="edit"  aria-hidden="true"></kmp-icon> <?= __('Change Waiver Type') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <?= $this->Form->create($gatheringWaiver, [
                'url' => ['action' => 'changeTypeActivities', $gatheringWaiver->id],
                'id' => 'changeTypeActivitiesForm'
            ]) ?>

            <div class="modal-body bg-light-subtle">
                <div class="alert alert-warning border-start border-warning border-4" role="alert">
                    <kmp-icon name="alert-triangle"  aria-hidden="true"></kmp-icon>
                    <strong><?= __('Warning:') ?></strong>
                    <?= __('Changing the waiver type or activity associations should be done carefully. This action affects which activities this waiver fulfills requirements for.') ?>
                </div>

                <!-- Waiver Type Selection -->
                <div class="border rounded-3 bg-white shadow-sm p-3">
                    <label for="waiver-type-select" class="form-label">
                        <kmp-icon name="file-text"  aria-hidden="true"></kmp-icon> <?= __('Waiver Type') ?>
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
                    <kmp-icon name="circle-x"  aria-hidden="true"></kmp-icon> <?= __('Cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <kmp-icon name="circle-check"  aria-hidden="true"></kmp-icon> <?= __('Save Changes') ?>
                </button>
            </div>

            <?= $this->Form->end() ?>
        </div>
    </div>
</div>