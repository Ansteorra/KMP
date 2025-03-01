<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Department';
$this->KMP->endBlock(); ?>

<div class="activityGroup form content">
    <?= $this->Form->create($department) ?>
    <fieldset>
        <legend><?= __("Add Department") ?></legend>
        <?php echo $this->Form->control("name"); ?>
        <?php echo $this->Form->control("domain"); ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>