<?php
echo $this->Modal->create("Add Member to Role", [
    "id" => "addMemberModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create(null, [
        "id" => "add_member__form",
        "url" => ["controller" => "MemberRoles", "action" => "add"],
    ]);
    echo $this->Form->control("sca_name", [
        "type" => "text",
        "label" => "SCA Name",
        "id" => "add_member__sca_name",
    ]);
    echo $this->Form->control("role_id", [
        "type" => "hidden",
        "value" => $role->id,
        "id" => "add_member__role_id",
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "id" => "add_member__member_id",
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "add_member__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>