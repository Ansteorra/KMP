<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WarrantRoster $warrantRoster
 * @var \App\Model\Entity\Warrant[]|\Cake\Collection\CollectionInterface $warrants
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li>
    <?= $this->Form->create(null, [
        'url' => ['action' => 'delete', $warrantRoster->id],
        'data-turbo' => 'false',
        'style' => 'display:inline;',
    ]) ?>
    <?= $this->Form->button(__('Delete'), [
        'type' => 'submit',
        'class' => 'nav-link border-0 bg-transparent',
        'data-controller' => 'confirmation',
        'data-action' => 'confirmation#confirm',
        'data-confirmation-message-value' => __('Are you sure you want to delete # {0}?', $warrantRoster->id),
        'data-confirmation-title-value' => __('Delete roster'),
        'data-confirmation-confirm-label-value' => __('Delete'),
    ]) ?>
    <?= $this->Form->end() ?>
</li>
<li><?= $this->Html->link(__('List Warrant Approval Sets'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Warrants'), ['controller' => 'Warrants', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Warrant'), ['controller' => 'Warrants', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="warrantRosters form content">
    <?= $this->Form->create($warrantRoster) ?>
    <fieldset>
        <legend><?= __('Edit Warrant Approval Set') ?></legend>
        <?php
        echo $this->Form->control('name');
        echo $this->Form->control('description');
        echo $this->Form->control('approvals_required');
        echo $this->Form->control('approval_count');
        echo $this->Form->control('created_by');
        echo $this->Form->control('modified_by');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
