<?php
$formUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'updateStates']);
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'TurboBulkEditForm']);
?>
<div id="bestowal_bulk_edit_root"
    data-controller="awards-bestowal-bulk-edit"
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
    'data-action' => 'submit->awards-bestowal-bulk-edit#submit input->awards-bestowal-bulk-edit#updateSubmitState change->awards-bestowal-bulk-edit#updateSubmitState autocomplete.change->awards-bestowal-bulk-edit#updateSubmitState',
]);
echo $this->Form->control('current_page', [
    'type' => 'hidden',
    'id' => 'bestowal_bulk__current_page',
    'value' => $this->request->getRequestTarget(),
]);
echo $this->Modal->create(__('Bulk Edit Bestowals'), [
    'id' => $modalId,
    'close' => true,
]);
?>
<script type="application/json" data-awards-bestowal-bulk-edit-target="stateRulesBlock" class="d-none">
<?= json_encode($rules ?? []) ?>
</script>
<?php
echo $this->Form->hidden('ids', ['value' => [], 'data-awards-bestowal-bulk-edit-target' => 'bulkIds']);
echo $this->Form->control('newState', [
    'label' => __('State'),
    'options' => $statusList ?? [],
    'empty' => __('Select a state'),
    'data-awards-bestowal-bulk-edit-target' => 'state',
    'data-action' => 'change->awards-bestowal-bulk-edit#setFieldRules change->awards-bestowal-bulk-edit#updateSubmitState',
]);
echo $this->Form->control('close_reason', [
    'label' => __('Reason for Cancellation'),
    'data-awards-bestowal-bulk-edit-target' => 'closeReason',
    'container' => ['data-awards-bestowal-bulk-edit-target' => 'closeReasonBlock'],
]);
?>
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
        ['data-awards-bestowal-bulk-edit-target' => 'planToGiveGathering']
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
]);
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
