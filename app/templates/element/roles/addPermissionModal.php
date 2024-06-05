<?php echo $this->Modal->create("Add Permission to Role", [
    "id" => "addPermissionModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->create(null, [
        "id" => "add_permission__form",
        "url" => ["controller" => "Roles", "action" => "addPermission"],
    ]);
    echo $this->Form->control("permission_id", [
        "options" => $permissions,
        "empty" => true,
        "id" => "add_permission__permission_id",
    ]);
    echo $this->Form->control("role_id", [
        "type" => "hidden",
        "value" => $role->id,
        "id" => "add_permission__role_id",
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "add_permission__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);
?>