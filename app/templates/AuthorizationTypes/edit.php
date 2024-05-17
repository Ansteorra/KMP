<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationType $authorizationType
 * @var \App\Model\Entity\MartialGroup[]|\Cake\Collection\CollectionInterface $martialGroups
 * @var \App\Model\Entity\MemberAuthorizationType[]|\Cake\Collection\CollectionInterface $MemberAuthorizationTypes
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $authorizationType->id], ['confirm' => __('Are you sure you want to delete # {0}?', $authorizationType->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Authorization Types'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Martial Groups'), ['controller' => 'MartialGroups', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Martial Group'), ['controller' => 'MartialGroups', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Member Authorization Types'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Member Authorization Type'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Pending Authorizations'), ['controller' => 'PendingAuthorizations', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Pending Authorization'), ['controller' => 'PendingAuthorizations', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Permissions'), ['controller' => 'Permissions', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Permission'), ['controller' => 'Permissions', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="authorizationTypes form content">
    <?= $this->Form->create($authorizationType) ?>
    <fieldset>
        <legend><?= __('Edit Authorization Type') ?></legend>
        <?php
            echo $this->Form->control('name');
            echo $this->Form->control('length');
            echo $this->Form->control('martial_groups_id', ['options' => $martialGroups]);
            echo $this->Form->control('minimum_age');
            echo $this->Form->control('maximum_age');
            echo $this->Form->control('num_required_authorizors');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
