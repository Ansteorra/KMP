<?php

echo $this->Form->create(null, [
    "id" => "edit_officer__form",
    "url" => [
        "controller" => "Officers",
        "action" => "edit",
    ],
    "data-turbo" => "true",
    "data-controller" => "turbo-modal officers-edit-officer page-context",
    "data-action" => implode(" ", [
        "submit->turbo-modal#submitAsTurboStream",
        "turbo:submit-start->turbo-modal#closeModalBeforeSubmit",
    ]),
    "data-officers-edit-officer-outlet-btn-outlet" => ".edit-btn",
]);
echo $this->Form->hidden('page_context_url', ['value' => '']);
echo $this->Modal->create("Edit Officer", [
    "id" => "editOfficerModal",
    "close" => true,
    "form" => true,
    "size" => "modal-lg",
]);
// get the id and name from the offices to use for dropdown options


?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-person-badge text-primary me-1" aria-hidden="true"></i>
        <?= __("Officer Details") ?>
    </legend>
    <?php
    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "edit-officer-id",
        "data-officers-edit-officer-target" => "id",
    ]); ?>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="mb-3 form-group text" data-officers-edit-officer-target="deputyDescBlock">
                <label class="form-label" for="edit_officer__deputy_description">
                    <?= __("Deputy Description") ?>
                </label>
                <input type="text" name="deputy_description" id="edit_officer__deputy_description" class="form-control" maxlength="255"
                    data-officers-edit-officer-target="deputyDesc">
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="mb-3 form-group date" data-officers-edit-officer-target="emailAddressBlock">
                <label class="form-label" for="edit_officer__email_address">
                    <?= __("Email Address") ?>
                </label>
                <input type="email" name="email_address" id="edit_officer__email_address" class="form-control" value=""
                    data-officers-edit-officer-target="emailAddress">
            </div>
        </div>
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