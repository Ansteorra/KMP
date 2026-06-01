<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\BestowalStatus $status
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Edit Bestowal Status';
$this->KMP->endBlock(); ?>

<div class="bestowalStatuses form content">
    <?= $this->Form->create($status, [
        'url' => ['action' => 'edit', $status->id],
    ]) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __("Edit Bestowal Status") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("sort_order", ['type' => 'number']);
        ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>
