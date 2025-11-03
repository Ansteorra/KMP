<?php

echo $this->Form->create(null, [
    "id" => "edit_officer__form",
    "url" => [
        "controller" => "Officers",
        "action" => "edit",
    ],
    "data-controller" => "officers-edit-officer",
    "data-officers-edit-officer-outlet-btn-outlet" => ".edit-btn",
]);
echo $this->Modal->create("Edit Officer", [
    "id" => "editOfficerModal",
    "close" => true,
]);
// get the id and name from the offices to use for dropdown options


?>
<fieldset>
    <?php
    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "edit-officer-id",
        "data-officers-edit-officer-target" => "id",
    ]); ?>
    <div class="mb-3 form-group text" data-officers-edit-officer-target="deputyDescBlock">
        <label class="form-label" for="assign_officer__deputy_description">
            Deputy Description
        </label>
        <input type="text" name="deputy_description" class=" form-control" maxlength="255"
            data-officers-edit-officer-target="deputyDesc">
    </div>
    <div class="mb-3 form-group date" data-officers-edit-officer-target="emailAddressBlock">
        <label class="form-label" for="assign_officer__end_date">
            Email Address
        </label>
        <input type="email" name="email_address" id="assign_officer__email_address" class="form-control" value=""
            data-officers-edit-officer-target="emailAddress">
    </div>
</fieldset>
<?php

echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-officers-assign-officer-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>