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
        "autocomplete" => "new-password",
        'help' => "Password must have a minimum of 12 characters"
    ]);
    echo $this->Form->control("confirm_password", [
        "type" => "password",
        "autocomplete" => "new-password",
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
        "type" => "button",
    ]),
]);
?>
<?= $this->Form->end() ?>
<?php if ($passwordReset->getErrors()) : ?>
    <div data-controller="modal-opener" data-modal-opener-modal-btn-value="passwordModalBtn"></div>
<?php endif; ?>