<?php echo $this->Modal->create("Edit", [
    "id" => "editModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->create($role, [
        "id" => "edit_entity",
        "url" => ["controller" => "Roles", "action" => "edit", $role->id],
    ]);
    echo $this->Form->control("name");
    echo $this->Form->end();
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
        "onclick" => '$("#edit_entity").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);
?>