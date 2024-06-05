<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission $permission
 * @var \App\Model\Entity\Activity[]|\Cake\Collection\CollectionInterface $activities
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");
$user = $this->request->getAttribute("identity");
?>

<div class="permissions form content">
    <?= $this->Form->create($permission) ?>
    <fieldset>
        <legend><?= __("Add Permission") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("activity_id", [
            "options" => $activities,
            "empty" => true,
        ]);
        echo $this->Form->control("require_active_membership", [
            "switch" => true,
            "label" => "Require Membership",
        ]);
        echo $this->Form->control("require_active_background_check", [
            "switch" => true,
            "label" => "Require Background Check",
        ]);
        echo $this->Form->control("require_min_age", [
            "label" => "Minimum Age",
            "type" => "number",
        ]);
        if ($user->isSuperUser()) {
            echo $this->Form->control("is_super_user", ["switch" => true]);
        } else {
            echo $this->Form->control("is_super_user", [
                "switch" => true,
                "disabled" => "disabled",
            ]);
        }
        echo $this->Form->control("requires_warrant", ["switch" => true]);
        ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>