<?php
echo $this->Form->create(null, [
    "url" => ["controller" => "Roles", "action" => "addPermission"],
    "role-add-permission-target" => "form",
    "data-controller" => "role-add-permission",
]);

echo $this->Modal->create("Add Permission to Role", [
    "id" => "addPermissionModal",
    "close" => true,
    "form" => true,
    "size" => "modal-lg",
]); ?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-shield-plus text-primary me-1" aria-hidden="true"></i>
        <?= __("Role Permission") ?>
    </legend>
    <?php
    echo $this->KMP->comboBoxControl(
        $this->Form,
        'permission_name',
        'permission_id',
        $permissions->toArray(),
        "Permission",
        true,
        false,
        [
            'data-role-add-permission-target' => 'permission',
            'data-action' => 'change->role-add-permission#checkSubmitEnable',
        ]
    );
    echo $this->Form->control("role_id", [
        "type" => "hidden",
        "value" => $role->id
    ]);
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "disabled" => "disabled",
        "data-role-add-permission-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>