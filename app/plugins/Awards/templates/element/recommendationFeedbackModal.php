<?php
$formUrl = $this->Url->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'requestFeedback']);
$memberLookupUrl = $this->Url->build(['plugin' => null, 'controller' => 'Members', 'action' => 'AutoComplete']);
$pageContextUrl = $pageContextUrl ?? $this->request->getRequestTarget();
$staticPageContext = $staticPageContext ?? false;
$selectedRecommendationIds = $selectedRecommendationIds ?? '';
$feedbackOrigin = $feedbackOrigin ?? null;
if (is_array($selectedRecommendationIds)) {
    $selectedRecommendationIds = implode(',', array_map('intval', $selectedRecommendationIds));
}
?>
<div id="recommendation_feedback_root" data-controller="recommendation-feedback-modal">
    <?= $this->Form->create(null, [
        'url' => $formUrl,
        'data-turbo' => 'false',
        'data-controller' => 'turbo-modal',
        'data-action' => implode(' ', [
            'submit->turbo-modal#submitAsTurboStream',
            'input->recommendation-feedback-modal#updateSubmitState',
        ]),
    ]) ?>
    <?php
    $this->Form->unlockField('ids');
    $this->Form->unlockField('page_context_url');
    $this->Form->unlockField('recipient_ids');
    $this->Form->unlockField('recipient_member');
    $this->Form->unlockField('recipient_member-Disp');
    $this->Form->unlockField('recipient_member_id');
    ?>
    <?= $this->Form->hidden('page_context_url', [
        'value' => $pageContextUrl,
        'data-page-context-static' => $staticPageContext ? 'true' : null,
    ]) ?>
    <?= $this->Form->hidden('ids', [
        'value' => $selectedRecommendationIds,
        'data-recommendation-feedback-modal-target' => 'ids',
    ]) ?>
    <?php if (is_string($feedbackOrigin) && $feedbackOrigin !== '') : ?>
        <?= $this->Form->hidden('feedback_origin', ['value' => $feedbackOrigin]) ?>
    <?php endif; ?>
    <?= $this->Modal->create(__('Request Feedback'), ['id' => $modalId, 'close' => true, 'form' => true]) ?>
    <div class="alert alert-info border-start border-info border-4" data-recommendation-feedback-modal-target="selectionSummary">
        <?= __('Select recommendations from the grid before requesting feedback.') ?>
    </div>
    <?= $this->Form->hidden('recipient_ids', [
        'data-recommendation-feedback-modal-target' => 'recipientIds',
    ]) ?>
    <fieldset class="border rounded-3 bg-white shadow-sm p-3 mb-3">
        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
            <i class="bi bi-people text-primary me-1" aria-hidden="true"></i>
            <?= __('Recipients') ?>
        </legend>
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
    <fieldset class="border rounded-3 bg-white shadow-sm p-3">
        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
            <i class="bi bi-envelope text-success me-1" aria-hidden="true"></i>
            <?= __('Request Details') ?>
        </legend>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('message', [
                    'type' => 'textarea',
                    'label' => __('Message to recipients'),
                    'rows' => 4,
                ]) ?>
            </div>
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('deadline', [
                    'type' => 'datetime-local',
                    'label' => __('Deadline'),
                    'empty' => true,
                    'required' => false,
                ]) ?>
            </div>
        </div>
    </fieldset>
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
