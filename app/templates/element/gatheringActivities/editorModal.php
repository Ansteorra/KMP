<?php
declare(strict_types=1);

$addUrl = $this->Url->build(['controller' => 'GatheringActivities', 'action' => 'add']);
$editUrl = $this->Url->build(['controller' => 'GatheringActivities', 'action' => 'edit']);
?>
<div class="modal fade" id="gatheringActivityModal" tabindex="-1"
    aria-labelledby="gatheringActivityModalLabel" aria-hidden="true"
    data-controller="gathering-activity-modal"

        data-gathering-activity-modal-add-url-value="<?= h($addUrl) ?>"

        data-gathering-activity-modal-edit-url-value="<?= h($editUrl) ?>"
    data-gathering-activity-modal-add-title-value="<?= h(__('Add Gathering Activity')) ?>"
    data-gathering-activity-modal-edit-title-value="<?= h(__('Edit Gathering Activity')) ?>"

        data-action="show.bs.modal->gathering-activity-modal#load
        shown.bs.modal->gathering-activity-modal#focusForm
        turbo:frame-load->gathering-activity-modal#focusForm
        turbo:frame-missing->gathering-activity-modal#missing
        turbo:fetch-request-error->gathering-activity-modal#failed">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2
                    class="modal-title fs-5"
                    id="gatheringActivityModalLabel"
                    data-gathering-activity-modal-target="title">
                    <?= __('Gathering activity') ?>
                </h2>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="<?= h(__('Close')) ?>"></button>
            </div>
            <turbo-frame id="gatheringActivityFormFrame" data-gathering-activity-modal-target="frame"></turbo-frame>
        </div>
    </div>
    <template data-gathering-activity-modal-target="loading">
        <div class="modal-body"><p role="status"><?= __('Loading activity…') ?></p></div>
    </template>
    <template data-gathering-activity-modal-target="error">
        <div
            class="modal-body"><p
            role="alert"><?= __('The activity could not be loaded. Close this dialog and try again.') ?></p></div>
    </template>
</div>
