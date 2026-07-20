<?php
echo $this->Form->create(null, [
    "url" => ["controller" => "Authorizations", "action" => "add"],
    'data-controller' => 'activities-request-auth',
    'data-activities-request-auth-url-value' => $this->Url->build(['controller' => 'Activities', 'action' => 'ApproversList', "plugin" => "Activities"]),
]);

echo $this->Modal->create("Request Authorization", [
    "id" => "requestAuthModal",
    "close" => true,
    "form" => true,
    "size" => "modal-lg",
]); ?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-shield-check text-primary me-1" aria-hidden="true"></i>
        <?= __("Authorization Request") ?>
    </legend>
    <?php
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "id" => "request-auth-member-id",
        "value" => $id,
        "data-activities-request-auth-target" => "memberId",
    ]);
    ?>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <?php
            echo $this->KMP->comboBoxControl(
                $this->Form,
                'activity_name',
                'activity',
                $activities->toArray(),
                "Activity",
                true,
                false,
                [
                    'idPrefix' => 'request-auth',
                    'data-activities-request-auth-target' => 'activity',
                    'data-action' => 'change->activities-request-auth#getApprovers'
                ]
            );
            ?>
        </div>
        <div class="col-12 col-md-6">
            <?php
            echo $this->KMP->comboBoxControl(
                $this->Form,
                'approver_name',
                'approver_id',
                [],
                "Send Request To",
                true,
                false,
                [
                    'idPrefix' => 'request-auth',
                    'data-activities-request-auth-target' => 'approvers',
                    'data-action' => 'ready->activities-request-auth#acConnected change->activities-request-auth#checkReadyToSubmit'
                ]
            );
            ?>
        </div>
    </div>
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