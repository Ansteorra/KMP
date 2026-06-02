<?php
$formUrl = $this->Url->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'requestFeedback']);
$memberLookupUrl = $this->Url->build(['plugin' => null, 'controller' => 'Members', 'action' => 'AutoComplete']);
?>
<div id="recommendation_feedback_root" data-controller="recommendation-feedback-modal">
    <?= $this->Form->create(null, [
        'url' => $formUrl,
        'data-turbo' => 'true',
        'data-controller' => 'turbo-modal',
        'data-action' => implode(' ', [
            'submit->turbo-modal#submitAsTurboStream',
            'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
            'input->recommendation-feedback-modal#updateSubmitState',
        ]),
    ]) ?>
    <?= $this->Form->hidden('page_context_url', ['value' => $this->request->getRequestTarget()]) ?>
    <?= $this->Form->hidden('ids', ['data-recommendation-feedback-modal-target' => 'ids']) ?>
    <?= $this->Modal->create(__('Request Feedback'), ['id' => $modalId, 'close' => true]) ?>
    <div class="alert alert-info" data-recommendation-feedback-modal-target="selectionSummary">
        <?= __('Select recommendations from the grid before requesting feedback.') ?>
    </div>
    <?= $this->Form->hidden('recipient_ids', [
        'data-recommendation-feedback-modal-target' => 'recipientIds',
    ]) ?>
    <fieldset class="mb-3">
        <legend class="form-label fs-6 mb-2"><?= __('Recipients') ?></legend>
        <div class="row g-2 align-items-end">
            <div class="col-md">
                <?= $this->KMP->autoCompleteControl(
                    $this->Form,
                    'recipient_member',
                    'recipient_member_id',
                    $memberLookupUrl,
                    __('Find recipient member'),
                    false,
                    false,
                    3,
                    [
                        'data-recommendation-feedback-modal-target' => 'recipientLookup',
                        'data-action' => implode(' ', [
                            'autocomplete.change->recommendation-feedback-modal#recipientSelected',
                            'change->recommendation-feedback-modal#recipientSelected',
                        ]),
                    ],
                ) ?>
            </div>
            <div class="col-md-auto">
                <?= $this->Form->button(__('Add Recipient'), [
                    'type' => 'button',
                    'class' => 'btn btn-outline-primary mb-3',
                    'data-recommendation-feedback-modal-target' => 'addRecipientButton',
                    'data-action' => 'recommendation-feedback-modal#addRecipient',
                    'disabled' => true,
                ]) ?>
            </div>
        </div>
        <div class="form-text" id="recommendation-feedback-recipient-help">
            <?= __('Search by name and add each person who should provide feedback.') ?>
        </div>
        <div class="mt-2 d-flex flex-wrap gap-2" role="list" aria-describedby="recommendation-feedback-recipient-help"
            data-recommendation-feedback-modal-target="recipientList"></div>
        <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"
            data-recommendation-feedback-modal-target="recipientStatus"></div>
    </fieldset>
    <?= $this->Form->control('message', [
        'type' => 'textarea',
        'label' => __('Message to recipients'),
        'rows' => 3,
    ]) ?>
    <?= $this->Form->control('deadline', [
        'type' => 'datetime-local',
        'label' => __('Deadline'),
        'empty' => true,
        'required' => false,
    ]) ?>
    <?= $this->Modal->end([
        $this->Form->button(__('Send Feedback Request'), [
            'class' => 'btn btn-primary',
            'data-recommendation-feedback-modal-target' => 'submitButton',
            'disabled' => true,
        ]),
        $this->Form->button(__('Close'), [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'data-bs-dismiss' => 'modal',
        ]),
    ]) ?>
    <?= $this->Form->end() ?>
</div>
