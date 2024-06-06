<?php
echo $this->Modal->create("Assign Officer", [
    "id" => "assignOfficerModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create($newOfficer, [
        "id" => "assign_officer__form",
        "url" => [
            "controller" => "Officers",
            "action" => "add",
        ],
    ]);
    echo $this->Form->control("branch_id", [
        "type" => "hidden",
        "value" => $branch->id,
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "id" => "assign_officer__member_id",
    ]);
    echo $this->Form->control("office_id", [
        "options" => $offices,
    ]);
    echo $this->Form->control("sca_name", [
        "type" => "text",
        "label" => "SCA Name",
        "id" => "assign_officer__sca_name",
    ]);
    echo $this->Form->control("start_on", [
        "type" => "date",
        "label" => __("Start Date"),
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "assign_officer__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>