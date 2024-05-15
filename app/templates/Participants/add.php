<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Participant $participant
 * @var \App\Model\Entity\ParticipantAuthorizationType[]|\Cake\Collection\CollectionInterface $participantAuthorizationTypes
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('List Participants'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Participant Authorization Types'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Participant Authorization Type'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Pending Authorizations'), ['controller' => 'PendingAuthorizations', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Pending Authorization'), ['controller' => 'PendingAuthorizations', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Roles'), ['controller' => 'Roles', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Role'), ['controller' => 'Roles', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="participants form content">
    <?= $this->Form->create($participant) ?>
    <fieldset>
        <legend><?= __('Add Participant') ?></legend>
        <?php
            echo $this->Form->control('last_updated');
            echo $this->Form->control('password');
            echo $this->Form->control('sca_name');
            echo $this->Form->control('first_name');
            echo $this->Form->control('middle_name');
            echo $this->Form->control('last_name');
            echo $this->Form->control('street_address');
            echo $this->Form->control('city');
            echo $this->Form->control('state');
            echo $this->Form->control('zip');
            echo $this->Form->control('phone_number');
            echo $this->Form->control('email_address');
            echo $this->Form->control('membership_number');
            echo $this->Form->control('membership_expires_on', ['empty' => true]);
            echo $this->Form->control('branch_name');
            echo $this->Form->control('notes');
            echo $this->Form->control('parent_name');
            echo $this->Form->control('background_check_expires_on', ['empty' => true]);
            echo $this->Form->control('hidden');
            echo $this->Form->control('password_token');
            echo $this->Form->control('password_token_expires_on', ['empty' => true]);
            echo $this->Form->control('last_login', ['empty' => true]);
            echo $this->Form->control('last_failed_login', ['empty' => true]);
            echo $this->Form->control('failed_login_attempts');
            echo $this->Form->control('birth_month');
            echo $this->Form->control('birth_year');
            echo $this->Form->control('deleted_date', ['empty' => true]);
            echo $this->Form->control('roles._ids', ['options' => $roles]);
                ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
