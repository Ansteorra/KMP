<?php
echo $this->Form->create(null, [
    "url" => ["controller" => "Authorizations", "action" => "add"],
    'data-controller' => 'activities-request-auth',
    'data-activities-request-auth-url-value' => $this->Url->build(['controller' => 'Activities', 'action' => 'ApproversList', "plugin" => "Activities"]),
]);

echo $this->Modal->create("Request Authorization", [
    "id" => "requestAuthModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "value" => $id,
        "data-activities-request-auth-target" => "memberId",
    ]);
    echo $this->KMP->comboBoxControl(
        $this->Form,
        'activity_name',
        'activity',
        $activities,
        "Activity",
        true,
        false,
        [
            'data-activities-request-auth-target' => 'activity',
            'data-action' => 'change->activities-request-auth#getApprovers'
        ]
    );
    echo $this->KMP->comboBoxControl(
        $this->Form,
        'approver_name',
        'approver_id',
        [],
        "Send Request To",
        true,
        false,
        [
            'data-activities-request-auth-target' => 'approvers',
            'data-action' => 'ready->activities-request-auth#acConnected change->activities-request-auth#checkReadyToSubmit'
        ]
    );
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-activities-request-auth-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>