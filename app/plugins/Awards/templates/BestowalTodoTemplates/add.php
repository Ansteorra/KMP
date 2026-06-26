<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\BestowalTodoTemplate $template
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Add Bestowal To-Do Template';
$this->KMP->endBlock();
?>

<div class="bestowalTodoTemplates form content">
    <?= $this->Form->create($template) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __('Add Bestowal To-Do Template') ?></legend>
        <?= $this->Form->control('name') ?>
        <?= $this->Form->control('description') ?>
        <?= $this->Form->control('is_active', [
            'type' => 'checkbox',
            'switch' => true,
            'label' => __('Active'),
            'checked' => true,
        ]) ?>
    </fieldset>
    <div class="text-end">
        <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
