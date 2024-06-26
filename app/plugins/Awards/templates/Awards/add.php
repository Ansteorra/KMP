<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Add Award';
$this->KMP->endBlock(); ?>

<div class="activityGroup form content">
    <?= $this->Form->create($award) ?>
    <fieldset>
        <legend><?= __('Add Award') ?></legend>
        <?php
        echo $this->Form->control('name');
        echo $this->Form->control('description');
        echo $this->Form->control('insignia');
        echo $this->Form->control('badge');
        echo $this->Form->control('charter');
        echo $this->Form->control('domain_id', ['options' => $awardsDomains]);
        echo $this->Form->control('level_id', ['options' => $awardsLevels]);
        echo $this->Form->control('branch_id', ['options' => $branches]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>