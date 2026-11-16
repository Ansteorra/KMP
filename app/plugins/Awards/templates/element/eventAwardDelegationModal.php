<?php
declare(strict_types=1);
/** @var \App\View\AppView $this */
?>
<div class="modal fade" id="eventAwardDelegationModal" tabindex="-1"
    aria-labelledby="eventAwardDelegationTitle" aria-hidden="true"
    data-controller="event-award-delegation"
    data-event-award-delegation-url-value="<?= h($this->Url->build([
        'plugin' => 'Awards', 'controller' => 'BestowalDelegations', 'action' => 'gathering', $gatheringId,
    ])) ?>" data-action="
        shown.bs.modal->event-award-delegation#open
        hidden.bs.modal->event-award-delegation#close
        turbo:frame-load->event-award-delegation#loaded
        turbo:frame-missing->event-award-delegation#unavailable">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="eventAwardDelegationTitle"><?= __('Event Award Delegation') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= h(__('Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <turbo-frame id="eventAwardDelegationFrame" data-turbo="true"
                    data-event-award-delegation-target="frame">
                    <p role="status"><?= __('Loading event award delegations…') ?></p>
                </turbo-frame>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= __('Close') ?>
                </button>
            </div>
        </div>
    </div>
</div>
