<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 * @var \App\Model\Entity\MemberAuthorizationType[]|\Cake\Collection\CollectionInterface $MemberAuthorizationTypes
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('List Members'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Member Authorization Types'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Member Authorization Type'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Pending Authorizations'), ['controller' => 'PendingAuthorizations', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Pending Authorization'), ['controller' => 'PendingAuthorizations', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Roles'), ['controller' => 'Roles', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Role'), ['controller' => 'Roles', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="Members form content">
    <?= $this->Form->create($member ) ?>
    <fieldset>
        <legend><?= __('Add Member') ?></legend>
        <?php
   
            echo $this->Form->control('sca_name');
            echo $this->Form->control('branch_id', ['options' => $treeList, 'required' => true]);
            echo $this->Form->control('first_name', ['required' => true]);
            echo $this->Form->control('middle_name');
            echo $this->Form->control('last_name', ['required' => true]);
            echo $this->Form->control('street_address');
            echo $this->Form->control('city');
            echo $this->Form->control('state');
            echo $this->Form->control('zip');
            echo $this->Form->control('phone_number');
            echo $this->Form->control('email_address', ['required' => true, 'type' => 'email', 'nestedInput' => true, 'labelOptions' => ['class' => 'input-group-text']]);
            echo $this->Form->control('membership_number');
            echo $this->Form->control('membership_expires_on', ['empty' => true]);
            echo $this->Form->control('parent_name');
            echo $this->Form->control('birthdate',['type'=>'date','empty'=>true,'minYear'=>1901, 'day'=> false, '']);
            echo $this->Form->control('background_check_expires_on', ['empty' => true]);
            echo $this->Form->control('password', ['required' => true]);
                ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
