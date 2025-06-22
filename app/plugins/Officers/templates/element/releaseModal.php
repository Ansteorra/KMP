<?php
echo $this->Form->create(null, [
    "url" => ["controller" => "Officers", "action" => "release"],
    "data-controller" => "revoke-form",
    "data-revoke-form-outlet-btn-outlet" => ".revoke-btn",
]);
echo $this->Modal->create("Release Office", [
    "id" => "releaseModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->control("id", [
        "type" => "hidden",
        "data-revoke-form-target" => "id",
    ]);
    echo $this->Form->control("revoked_reason", [
        "label" => "Reason for Release",
        "data-revoke-form-target" => "reason",
        "data-action" => "input->revoke-form#checkReadyToSubmit",
        "help" => "This message will be visible to the office holder."
    ]);

    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-revoke-form-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();

?>