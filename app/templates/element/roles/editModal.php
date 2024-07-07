<?php
echo $this->Form->create($role, [
    "id" => "edit_entity",
    "url" => ["controller" => "Roles", "action" => "edit", $role->id],
]);

echo $this->Modal->create("Edit", [
    "id" => "editModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);
echo $this->Form->end();
?>