<?php
echo $this->Form->create(null, [
    "url" => ["controller" => "Authorizations", "action" => "renew"],
    'data-controller' => 'activities-renew-auth',
    'data-activities-renew-auth-url-value' => $this->Url->build(['controller' => 'Activities', 'action' => 'ApproversList', "plugin" => "Activities"]),
    'data-activities-renew-auth-outlet-btn-outlet' => '.renew-btn',
]);
echo $this->Modal->create("Renew Authorization", [
    "id" => "renewalModal",
    "close" => true,
]); ?>
<fieldset>
    <?php

    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "renew-auth-id",
        "data-activities-renew-auth-target" => "id",
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "id" => "renew-auth-member-id",
        "value" => $id,
        "data-activities-renew-auth-target" => "memberId",
    ]);
    echo $this->Form->control("activity", [
        "type" => "hidden",
        "id" => "renew-auth-activity",
        "data-activities-renew-auth-target" => "activity",
    ]);
    echo $this->KMP->comboBoxControl(
        $this->Form,
        'approver_name',
        'approver_id',
        [],
        "Send Request To",
        true,
        false,
        [
            'idPrefix' => 'renew-auth',
            'data-activities-renew-auth-target' => 'approvers',
            'data-action' => 'change->activities-renew-auth#checkReadyToSubmit'
        ]
    );
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-activities-renew-auth-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>