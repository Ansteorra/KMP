<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Activity $activity
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $ActivityGroups
 * @var \App\Model\Entity\MemberActivity[]|\Cake\Collection\CollectionInterface $MemberActivities
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Activity';
$this->KMP->endBlock(); ?>

<div class="activities form content">
    <?= $this->Form->create($activity) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __("Add Activity") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("activity_group_id", [
            "options" => $activityGroup,
        ]);
        echo $this->Form->control("permission_id", [
            "label" => "Authorized By",
            "options" => $authByPermissions,
            "empty" => true
        ]);
        echo $this->Form->control("grants_role_id", [
            "options" => $authAssignableRoles,
            "empty" => true
        ]);
        echo $this->Form->control("term_length", [
            "label" => "Duration (Months)",
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