<?php

/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $warrantPeriod
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Warrant Period';
$this->KMP->endBlock();

?>
<div class="warrantPeriods form content">
    <?= $this->Form->create($warrantPeriod) ?>
    <fieldset>
        <legend><?= __('Add Warrant Period') ?></legend>
        <?php
        echo $this->Form->control("start_date", [
            "type" => "date",
            "label" => __("Start Date"),
        ]);
        echo $this->Form->control("end_date", [
            "type" => "date",
            "label" => __("End Date"),
        ]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>