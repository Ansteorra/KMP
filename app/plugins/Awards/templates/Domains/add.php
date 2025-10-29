<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Award Domain';
$this->KMP->endBlock(); ?>

<div class="activityGroup form content">
    <?= $this->Form->create($domain) ?>
    <fieldset>
        <legend><a href="#" onclick="window.history.back(); return false;" class="bi bi-arrow-left-circle"></a>
            <?= __("Add Award Domain") ?></legend>
        <?php echo $this->Form->control("name"); ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>