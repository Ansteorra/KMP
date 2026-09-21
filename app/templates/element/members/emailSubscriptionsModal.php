<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
$autoOpen = $this->getRequest()->getQuery('emailSubscriptions') === '1';
$subscriptionsUrl = $this->Url->build(['controller' => 'GridSubscriptions', 'action' => 'index']);
?>
<div class="modal fade" id="emailSubscriptionsModal" tabindex="-1" aria-labelledby="emailSubscriptionsTitle"
    data-controller="grid-subscriptions-dialog"
    data-grid-subscriptions-dialog-auto-open-value="<?= $autoOpen ? 'true' : 'false' ?>"
    data-grid-subscriptions-dialog-url-value="<?= h($subscriptionsUrl) ?>"
    data-action="shown.bs.modal->grid-subscriptions-dialog#opened hidden.bs.modal->grid-subscriptions-dialog#closed">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="emailSubscriptionsTitle" tabindex="-1"
                    data-grid-subscriptions-dialog-target="heading"><?= __('Email subscriptions') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close email subscriptions') ?>"></button>
            </div>
            <div class="modal-body">
                <turbo-frame id="email-subscriptions" data-grid-subscriptions-dialog-target="frame"
                    data-action="turbo:frame-load->grid-subscriptions-dialog#loaded
                        turbo:fetch-request-error->grid-subscriptions-dialog#failed
                        turbo:frame-missing->grid-subscriptions-dialog#failed">
                </turbo-frame>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Close') ?></button>
            </div>
        </div>
    </div>
</div>
