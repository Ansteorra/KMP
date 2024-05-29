<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $Members
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<div class="roles form content">
    <?= $this->Form->create($role) ?>
    <fieldset>
        <legend><?= __("Add Role") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("permissions._ids", [
            "type" => "select",
            "multiple" => "checkbox",
            "options" => $permissions,
            "switch" => true,
        ]);
        ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>