<?php

/**
 * Cancel Gathering Modal
 *
 * Allows users to cancel a gathering with an optional reason.
 * Cancelling preserves the gathering and all associated data.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>

<div class="modal fade" id="cancelGatheringModal" tabindex="-1" aria-labelledby="cancelGatheringModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="cancelGatheringModalLabel">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= __('Cancel Gathering') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'Gatherings', 'action' => 'cancel', $gathering->id],
            ]) ?>
            <div class="modal-body bg-light-subtle">
                <div class="alert alert-warning border-start border-warning border-4">
                    <i class="bi bi-info-circle"></i>
                    <?= __('Cancelling a gathering will mark it as cancelled but preserve all data (attendances, waivers, etc.). This action can be undone.') ?>
                </div>

                <p class="fw-bold">
                    <?= __('Are you sure you want to cancel "{0}"?', h($gathering->name)) ?>
                </p>

                <div class="border rounded-3 bg-white shadow-sm p-3">
                    <?= $this->Form->control('cancellation_reason', [
                        'type' => 'textarea',
                        'label' => __('Cancellation Reason (optional)'),
                        'placeholder' => __('e.g., Weather conditions, venue unavailable, etc.'),
                        'rows' => 3,
                        'class' => 'form-control',
                    ]) ?>
                    <small class="form-text text-muted">
                        <?= __('This reason will be displayed on the gathering page.') ?>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= __('Keep Active') ?>
                </button>
                <?= $this->Form->button(
                    __(' Cancel Gathering'),
                    [
                        'type' => 'submit',
                        'class' => 'btn btn-warning bi bi-x-circle',
                        'escape' => false,
                    ]
                ) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>