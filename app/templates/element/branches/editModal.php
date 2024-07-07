<?php
echo $this->Form->create($branch, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Branches",
        "action" => "edit",
        $branch->id,
    ],
]);
echo $this->Modal->create("Edit Branch", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("location");
    echo $this->Form->control("parent_id", [
        "options" => $treeList,
        "empty" => true,
    ]);
    ?>
</fieldset>
<?php echo $this->Modal->end([
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