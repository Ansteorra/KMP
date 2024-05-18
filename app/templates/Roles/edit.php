<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $Members
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>
<div class="roles form content">
    <?= $this->Form->create($role) ?>
    <fieldset>
        <legend><?= __('Edit Role') ?> : <?= $this->Html->link(__('View'), ['action' => 'view', $role->id], ['class' => 'btn btn-primary btn-sm']) ?></h3></legend>
        <?php
            echo $this->Form->control('name');
            echo $this->Form->control('permissions._ids', ['type' => 'multicheckbox','options' => $permissions]);
                ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
