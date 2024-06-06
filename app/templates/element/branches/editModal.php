<?php
echo $this->Modal->create("Edit Branch", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create($branch, [
        "id" => "edit_entity",
        "url" => [
            "controller" => "Branches",
            "action" => "edit",
            $branch->id,
        ],
    ]);
    echo $this->Form->control("name");
    echo $this->Form->control("location");
    echo $this->Form->control("parent_id", [
        "options" => $treeList,
        "empty" => true,
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
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