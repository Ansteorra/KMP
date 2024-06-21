<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 * @var \App\Model\Entity\MemberActivity[]|\Cake\Collection\CollectionInterface $MemberActivities
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */

use App\KMP\StaticHelpers;
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Add Member';
$this->KMP->endBlock(); ?>
<div class="Members form content">
    <?= $this->Form->create($member) ?>
    <fieldset>
        <legend><?= __("Add Member") ?></legend>
        <?php
        echo $this->Form->control("sca_name");
        echo $this->Form->control("branch_id", [
            "options" => $treeList,
            "required" => true,
        ]);
        echo $this->Form->control("first_name", ["required" => true]);
        echo $this->Form->control("middle_name");
        echo $this->Form->control("last_name", ["required" => true]);
        echo $this->Form->control("street_address");
        echo $this->Form->control("city");
        echo $this->Form->control("state");
        echo $this->Form->control("zip");
        echo $this->Form->control("phone_number");
        echo $this->Form->control("email_address", [
            "required" => true,
            "type" => "email",
            "nestedInput" => true,
            "labelOptions" => ["class" => "input-group-text"],
        ]);
        echo $this->Form->control("membership_number");
        echo $this->Form->control("membership_expires_on", ["empty" => true]); ?>
        <div class="mb-3 form-group select row">
            <label class="form-label" for="birth-month">Birth Date</label>
            <div class="col-2">
                <select name="birth_month" id="birth-month" class="form-select" required="required">
                    <option value=""></option>
                    <?php foreach ($months as $index => $value) : ?>
                        <option value="<?= $index ?>"><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-2">
                <select name="birth_year" id="birth-year" class="form-select" required="required">
                    <option value=""></option>
                    <?php foreach ($years as $index => $value) : ?>
                        <option value="<?= $index ?>"><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
        echo $this->Form->control("background_check_expires_on", [
            "empty" => true,
        ]); ?>
    </fieldset>
    <?= $this->Form->button(__("Submit")) ?>
    <?= $this->Form->end() ?>
</div>