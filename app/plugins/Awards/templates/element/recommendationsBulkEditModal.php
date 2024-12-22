<?php
$formUrl = $this->URL->build(["controller" => "Recommendations", "action" => "updateStates", "plugin" => "Awards",]);
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'TurboBulkEditForm']);
echo $this->Form->create(null, [

    "id" => "recommendation_bulk_form",
    "url" => [
        "controller" => "Recommendations",
        "action" => "updateStates",
    ],
    'data-awards-rec-bulk-edit-public-profile-url-value' => $this->URL->build([
        'controller' => 'Members',
        'action' => 'PublicProfile',
        'plugin' => null
    ]),
    'data-action' => 'submit->awards-rec-bulk-edit#submit',
    'data-controller' => 'awards-rec-bulk-edit awards-rec-table',
    'data-awards-rec-bulk-edit-outlet-btn-outlet' => '.bulk-edit-btn',
    'data-awards-rec-bulk-edit-award-list-url-value' => $this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => "Awards"]),
    'data-awards-rec-bulk-edit-form-url-value' => $formUrl,
    'data-awards-rec-bulk-edit-turbo-frame-url-value' => $turboFrameUrl,


]);
echo $this->Form->control(
    "current_page",
    [
        "type" => "hidden",
        "id" => "recommendation__current_page",
        "value" => $this->request->getRequestTarget()
    ]

);
echo $this->Form->control(
    "bulkIds",
    [

        "type" => "hidden",
        "id" => "recommendation__ids",
        "value" => [],
        'data-controller' => 'awards-rec-bulk-edit',
        'data-awards-rec-bulk-edit-target' => 'bulkIds',
    ]

);
echo $this->Modal->create("Bulk Edit Recommendations", [
    "id" => $modalId,
    "close" => true,
]);
?>
<script type="application/json" data-awards-rec-bulk-edit-target="stateRulesBlock" class="d-none">
<?= json_encode($rules) ?>
</script>
<fieldset>

    <?php
    echo $this->Form->hidden('ids', ['value' => [], 'data-awards-rec-bulk-edit-target' => 'bulkIds']);
    ?>
    <?php

    echo $this->Form->control(
        'newState',
        [
            'options' => $statusList,
            'value' => "",
            'data-awards-rec-bulk-edit-target' => 'state',
            'data-action' => 'change->awards-rec-bulk-edit#setFieldRules',
        ]
    );
    echo $this->Form->control(
        'close_reason',
        [
            'label' => 'Reason for No Action',
            'value' => "",
            'data-awards-rec-bulk-edit-target' => 'closeReason',
            'container' => ['data-awards-rec-bulk-edit-target' => 'closeReasonBlock'],
        ]
    );
    echo $this->Form->control('event_id', [
        'label' => 'Plan to Give At',
        "type" => "select",
        'options' => $eventList,
        'empty' => true,
        'value' => "",
        'data-awards-rec-bulk-edit-target' => 'planToGiveEvent',
        'container' => ['data-awards-rec-bulk-edit-target' => 'planToGiveBlock'],
    ]);
    echo $this->Form->control(
        'given',
        [
            'type' => 'date',
            'label' => 'Given On',
            'value' => "",
            'data-awards-rec-bulk-edit-target' => 'givenDate',
            'container' => ['data-awards-rec-bulk-edit-target' => 'givenBlock'],
        ]
    );
    echo $this->Form->control('note', [
        'type' => 'textarea',
        'label' => 'Note',
        'id' => 'recommendation__notes',
    ]);
    ?>
</fieldset>
<!--<turbo-frame id="bulkEditRecommendation" data-awards-rec-bulk-edit-target="turboFrame">
    loading
</turbo-frame>-->
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "recommendation_bulk_submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
        "id" => "recommendation_bulk_edit_close",

    ]),
]);

echo $this->Form->end();
?>