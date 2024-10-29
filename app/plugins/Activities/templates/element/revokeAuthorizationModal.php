<?php if ($user->checkCan("revoke", "Activities.Authorizations")) {
    echo $this->Form->create(null, [
        "url" => ["controller" => "Authorizations", "action" => "revoke"],
        'data-controller' => 'revoke-form',
        'data-revoke-form-grid-btn-outlet' => '.revoke-btn',
    ]);
    echo $this->Modal->create("Revoke Authorization", [
        "id" => "revokeModal",
        "close" => true,
    ]); ?>
    <fieldset>
        <?php
        echo $this->Form->control("id", [
            "type" => "hidden",
            "data-revoke-form-target" => "id",
        ]);
        echo $this->Form->control("revoked_reason", [
            "label" => "Reason for Revocation",
            "data-revoke-form-target" => "reason",
            "data-action" => "input->revoke-form#checkReadyToSubmit",
            "help" => "This message will be visible to the member."
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
}
echo $this->Form->end();
?>