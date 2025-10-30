<?php

/**
 * Add Waiver Requirement Modal
 * 
 * Modal interface for adding waiver requirements to gathering activities.
 * Uses Stimulus controller for dynamic waiver type population.
 * 
 * @var \App\View\AppView $this
 * @var int $gatheringActivityId The gathering activity ID
 */
echo $this->Form->create(null, [
    "url" => ["controller" => "GatheringActivityWaivers", "action" => "add", "plugin" => "Waivers"],
    'data-controller' => 'waivers-add-requirement',
    'data-waivers-add-requirement-url-value' => $this->Url->build([
        'controller' => 'GatheringActivityWaivers',
        'action' => 'availableWaiverTypes',
        'plugin' => 'Waivers'
    ]),
]);

echo $this->Modal->create("Add Waiver Requirement", [
    "id" => "addWaiverRequirementModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->control("gathering_activity_id", [
        "type" => "hidden",
        "value" => $gatheringActivityId,
        "data-waivers-add-requirement-target" => "activityId",
    ]);
    echo $this->KMP->comboBoxControl(
        $this->Form,
        'waiver_type_name',
        'waiver_type_id',
        [],
        "Waiver Type",
        true,
        false,
        [
            'data-waivers-add-requirement-target' => 'waiverType',
            'data-action' => 'ready->waivers-add-requirement#loadWaiverTypes change->waivers-add-requirement#checkReadyToSubmit'
        ]
    );
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Add Requirement", [
        "class" => "btn btn-primary",
        "data-waivers-add-requirement-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>