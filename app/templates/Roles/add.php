<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $Members
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('List Roles'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Members'), ['controller' => 'Members', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Member'), ['controller' => 'Members', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Permissions'), ['controller' => 'Permissions', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Permission'), ['controller' => 'Permissions', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="roles form content">
    <?= $this->Form->create($role) ?>
    <fieldset>
        <legend><?= __('Add Role') ?></legend>
        <?php
            echo $this->Form->control('name');
            echo $this->Form->control('permissions._ids', ['type' => 'multicheckbox','options' => $permissions]);
                ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
