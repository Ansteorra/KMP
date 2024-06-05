<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission $permission
 * @var \App\Model\Entity\Activity[]|\Cake\Collection\CollectionInterface $authorizationTypes
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");
$user = $this->request->getAttribute("identity");
?>

<?php $this->start("tb_actions"); ?>
<li><?= $this->Html->link(
        __("List Permissions"),
        ["action" => "index"],
        ["class" => "nav-link"],
    ) ?></li>
<li><?= $this->Html->link(
        __("List Activities"),
        ["controller" => "Activities", "action" => "index"],
        ["class" => "nav-link"],
    ) ?></li>
<li><?= $this->Html->link(
        __("New Activity"),
        ["controller" => "Activities", "action" => "add"],
        ["class" => "nav-link"],
    ) ?></li>
<li><?= $this->Html->link(
        __("List Roles"),
        ["controller" => "Roles", "action" => "index"],
        ["class" => "nav-link"],
    ) ?></li>
<li><?= $this->Html->link(
        __("New Role"),
        ["controller" => "Roles", "action" => "add"],
        ["class" => "nav-link"],
    ) ?></li>
<?php $this->end(); ?>
<?php $this->assign(
    "tb_sidebar",
    '<ul class="nav flex-column">' . $this->fetch("tb_actions") . "</ul>",
); ?>

<div class="permissions form content">
    <?= $this->Form->create($permission) ?>
    <fieldset>
        <legend><?= __("Add Permission") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("activity_id", [
            "options" => $authorizationTypes,
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