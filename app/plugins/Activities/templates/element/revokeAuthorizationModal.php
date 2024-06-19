<?php if ($user->can("revoke", "Activities.Authorizations")) {
    echo $this->Modal->create("Revoke Authorization", [
        "id" => "revokeModal",
        "close" => true,
    ]); ?>
<fieldset>
    <?php
        echo $this->Form->create(null, [
            "url" => ["controller" => "Authorizations", "action" => "revoke"],
            "id" => "revoke_auth",
        ]);
        echo $this->Form->control("id", [
            "type" => "hidden",
            "id" => "revoke_auth__id",
        ]);
        echo $this->Form->control("revoked_reason", [
            "label" => "Reason for Revocation",
            "onkeypress" => '$("#revoke_auth__submit").removeAttr("disabled");',
            "id" => "revoke_auth__revoked_reason",
        ]);
        echo $this->Form->end();
        ?>
</fieldset>
<?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "revoke_auth__submit",
            "onclick" => '$("#revoke_auth").submit();',
            "disabled" => "disabled",
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]);
} ?>