<?php
echo $this->Modal->create("Renew Authorization", [
    "id" => "renewalModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->create(null, [
        "url" => ["controller" => "Authorizations", "action" => "renew"],
        "id" => "renew_auth__form",
    ]);
    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "renew_auth__id",
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "value" => $member->id,
        "id" => "renew_auth__member_id",
    ]);
    echo $this->Form->control("activity", [
        "type" => "hidden",
        "id" => "renew_auth__auth_type_id"
    ]);
    echo $this->Form->control("approver_id", [
        "type" => "select",
        "options" => [],
        "id" => "renew_auth__approver_id",
        "label" => "Send Request To",
        "disabled" => "disabled",
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "renew_auth__submit",
        "onclick" => '$("#renew_auth__form").submit();',
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>