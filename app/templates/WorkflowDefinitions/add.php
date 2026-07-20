<?php

/**
 * Add Workflow Definition
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WorkflowDefinition $workflow
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Workflow';
$this->KMP->endBlock();
?>

<div class="workflows form content">
    <?= $this->Form->create($workflow) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __('New Workflow Definition') ?></legend>
        <?php
        echo $this->Form->control('name', ['label' => __('Name')]);
        echo $this->Form->control('slug', [
            'label' => __('Slug'),
            'placeholder' => 'e.g. member-approval',
        ]);
        echo $this->Form->control('description', [
            'type' => 'textarea',
            'label' => __('Description'),
            'rows' => 3,
        ]);
        echo $this->Form->control('trigger_type', [
            'type' => 'select',
            'label' => __('Trigger Type'),
            'options' => [
                'event' => __('Event'),
                'manual' => __('Manual'),
                'scheduled' => __('Scheduled'),
            ],
            'empty' => __('-- Select --'),
        ]);
        echo $this->Form->control('entity_type', [
            'label' => __('Entity Type'),
            'placeholder' => 'e.g. Members',
            'required' => false,
        ]);
        echo $this->Form->control('execution_mode', [
            'type' => 'select',
            'label' => __('Execution Mode'),
            'options' => [
                'durable' => __('Durable — Full persistence, supports async nodes (approvals, delays)'),
                'ephemeral' => __('Ephemeral — In-memory only, no history, synchronous nodes only'),
            ],
            'default' => 'durable',
        ]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Create & Open Designer'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Form->end() ?>
</div>
