<?php
$formUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'updateStates']);
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'TurboBulkEditForm']);
?>
<div id="bestowal_bulk_edit_root"
    data-controller="awards-bestowal-bulk-edit page-context"
    data-awards-bestowal-bulk-edit-outlet-btn-outlet=".bulk-edit-btn"
    data-awards-bestowal-bulk-edit-form-url-value="<?= h($formUrl) ?>"
    data-awards-bestowal-bulk-edit-turbo-frame-url-value="<?= h($turboFrameUrl) ?>"
    data-awards-bestowal-bulk-edit-gatherings-lookup-url-value="<?= h($this->URL->build([
        'plugin' => 'Awards',
        'controller' => 'Bestowals',
        'action' => 'gatheringsForBestowalBulkAutoComplete',
    ])) ?>">
<?php
echo $this->Form->create(null, [
    'id' => 'bestowal_bulk_form',
    'url' => [
        'plugin' => 'Awards',
        'controller' => 'Bestowals',
        'action' => 'updateStates',
    ],
    'data-turbo' => 'true',
    'data-controller' => 'turbo-modal',
    'data-action' => 'submit->awards-bestowal-bulk-edit#submit turbo:submit-start->turbo-modal#closeModalBeforeSubmit input->awards-bestowal-bulk-edit#updateSubmitState change->awards-bestowal-bulk-edit#updateSubmitState autocomplete.change->awards-bestowal-bulk-edit#updateSubmitState',
]);
echo $this->Form->hidden('page_context_url', [
    'value' => $this->request->getRequestTarget(),
]);
echo $this->Modal->create(__('Bulk Edit Bestowals'), [
    'id' => $modalId,
    'close' => true,
    'form' => true,
]);
?>
<script type="application/json" data-awards-bestowal-bulk-edit-target="stateRulesBlock" class="d-none">
<?= json_encode($rules ?? []) ?>
</script>
<?php
echo $this->Form->hidden('ids', ['value' => [], 'data-awards-bestowal-bulk-edit-target' => 'bulkIds']);
?>
<div class="alert alert-info border-start border-info border-4 py-2" role="note">
    <?= __('Apply the same state, court planning, and note fields to all selected bestowals.') ?>
</div>
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                <i class="bi bi-arrow-repeat text-primary me-1" aria-hidden="true"></i>
                <?= __('State Transition') ?>
            </legend>
            <?php
            echo $this->Form->control('newState', [
                'label' => __('State'),
                'options' => $statusList ?? [],
                'empty' => __('Select a state'),
                'data-awards-bestowal-bulk-edit-target' => 'state',
                'data-action' => implode(' ', [
                    'change->awards-bestowal-bulk-edit#setFieldRules',
                    'change->awards-bestowal-bulk-edit#updateSubmitState',
                ]),
            ]);
            echo $this->Form->control('close_reason', [
                'label' => __('Reason for Cancellation'),
                'data-awards-bestowal-bulk-edit-target' => 'closeReason',
                'container' => ['data-awards-bestowal-bulk-edit-target' => 'closeReasonBlock'],
            ]);
            ?>
        </fieldset>
    </div>
    <div class="col-12 col-lg-6">
        <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                <i class="bi bi-calendar-event text-success me-1" aria-hidden="true"></i>
                <?= __('Court & Notes') ?>
            </legend>
            <div data-awards-bestowal-bulk-edit-target="planToGiveBlock">
                <?php
                $bulkGatheringLookupUrl = $this->URL->build([
                    'plugin' => 'Awards',
                    'controller' => 'Bestowals',
                    'action' => 'gatheringsForBestowalBulkAutoComplete',
                ]);
                echo $this->KMP->autoCompleteControl(
                    $this->Form,
                    'gathering_name',
                    'gathering_id',
                    $bulkGatheringLookupUrl,
                    __('Gathering'),
                    false,
                    false,
                    2,
                    ['data-awards-bestowal-bulk-edit-target' => 'planToGiveGathering'],
                );
                ?>
            </div>
            <?php
            echo $this->Form->control('bestowed_at', [
                'type' => 'date',
                'label' => __('Bestowed On'),
                'data-awards-bestowal-bulk-edit-target' => 'givenDate',
                'container' => ['data-awards-bestowal-bulk-edit-target' => 'givenBlock'],
            ]);
            echo $this->Form->control('note', [
                'type' => 'textarea',
                'label' => __('Note'),
                'rows' => 4,
            ]);
            ?>
        </fieldset>
    </div>
</div>
<?php
echo $this->Modal->end([
    $this->Form->button(__('Submit'), [
        'class' => 'btn btn-primary',
        'id' => 'bestowal_bulk_submit',
        'disabled' => true,
        'data-awards-bestowal-bulk-edit-target' => 'submitButton',
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'id' => 'bestowal_bulk_edit_close',
    ]),
]);
echo $this->Form->end();
?>
</div>
