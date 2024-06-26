<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Add Award Req Event';
$this->KMP->endBlock(); ?>

<div class="activityGroup form content">
    <?= $this->Form->create($event) ?>
    <fieldset>
        <legend><?= __("Add Award Req Event") ?></legend>
        <?php echo $this->Form->control("name"); ?>
        <?php echo $this->Form->control("description"); ?>
        <?php echo $this->Form->control('branch_id', ['options' => $branches]); ?>
        <?php echo $this->Form->control("start_date", [
            "type" => "date",
            "label" => __("Start Date"),
        ]); ?>
        <?php echo $this->Form->control("end_date", [
            "type" => "date",
            "label" => __("End Date"),
        ]); ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>