<?= $this->Form->create($passwordReset, [
    "id" => "change_password",
    "url" => [
        "controller" => "Members",
        "action" => "changePassword",
        $member->id,
    ],
]) ?>
<?php echo $this->Modal->create("Edit " . $member->sca_name, [
    "id" => "passwordModal",
    "close" => true,
]); ?>
<fieldset>
    <legend><?= __("Change Password") ?></legend>
    <?php
    echo $this->Form->control("new_password", [
        "type" => "password",
    ]);
    echo $this->Form->control("confirm_password", [
        "type" => "password",
    ]);
    ?>
</fieldset>

<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "change_password__submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);
?>
<?= $this->Form->end() ?>