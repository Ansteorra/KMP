<?php if ($user->checkCan("revoke", "Activities.Authorizations")) {
    echo $this->Form->create(null, [
        "url" => ["controller" => "Authorizations", "action" => "revoke"],
        'data-controller' => 'revoke-form',
        'data-revoke-form-outlet-btn-outlet' => '.revoke-btn',
    ]);
    echo $this->Modal->create("Revoke Authorization", [
        "id" => "revokeModal",
        "close" => true,
        "form" => true,
        "size" => "modal-lg",
    ]); ?>
    <div class="alert alert-warning border-start border-warning border-4 py-2" role="note">
        <?= __("Record why this authorization is being revoked.") ?>
    </div>
    <fieldset class="border rounded-3 bg-white shadow-sm p-3">
        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
            <i class="bi bi-exclamation-triangle text-warning me-1" aria-hidden="true"></i>
            <?= __("Revocation Details") ?>
        </legend>
        <?php
        echo $this->Form->control("id", [
            "type" => "hidden",
            "id" => "revoke-auth-id",
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