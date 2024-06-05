<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Activity $authorizationType
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $ActivityGroups
 * @var \App\Model\Entity\MemberActivity[]|\Cake\Collection\CollectionInterface $MemberActivities
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>

<div class="activities form content">
    <?= $this->Form->create($authorizationType) ?>
    <fieldset>
        <legend><?= __("Add Activity") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("activity_group_id", [
            "options" => $authorizationGroups,
        ]);
        echo $this->Form->control("grants_role_id", [
            "options" => $authAssignableRoles,
            "empty" => true
        ]);
        echo $this->Form->control("length", [
            "label" => "Duration (years)",
            "type" => "number",
        ]);
        echo $this->Form->control("minimum_age", ["type" => "number"]);
        echo $this->Form->control("maximum_age", ["type" => "number"]);
        echo $this->Form->control("num_required_authorizors", [
            "label" => "# for Approval",
            "type" => "number",
        ]);
        echo $this->Form->control("num_required_renewers", [
            "label" => "# for Renewal",
            "type" => "number",
        ]);
        ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>