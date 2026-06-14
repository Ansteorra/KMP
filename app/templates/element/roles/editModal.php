<?php
echo $this->Form->create($role, [
    "id" => "edit_entity",
    "url" => ["controller" => "Roles", "action" => "edit", $role->id],
]);

echo $this->Modal->create("Edit", [
    "id" => "editModal",
    "close" => true,
    "form" => true,
    "size" => "modal-lg",
]); ?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-person-lock text-primary me-1" aria-hidden="true"></i>
        <?= __("Role Details") ?>
    </legend>
    <?php
    echo $this->Form->control("name");
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "role-edit-submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>