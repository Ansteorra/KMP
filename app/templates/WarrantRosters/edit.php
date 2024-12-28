<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WarrantRoster $warrantRoster
 * @var \App\Model\Entity\WarrantRosterApproval[]|\Cake\Collection\CollectionInterface $warrantRosterApprovals
 * @var \App\Model\Entity\Warrant[]|\Cake\Collection\CollectionInterface $warrants
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $warrantRoster->id], ['confirm' => __('Are you sure you want to delete # {0}?', $warrantRoster->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Warrant Approval Sets'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Warrant Approvals'), ['controller' => 'WarrantRosterApprovals', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Warrant Approval'), ['controller' => 'WarrantRosterApprovals', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
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