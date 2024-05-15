<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Participant $participant
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('Edit Participant'), ['action' => 'edit', $participant->id], ['class' => 'nav-link']) ?></li>
<li><?= $this->Form->postLink(__('Delete Participant'), ['action' => 'delete', $participant->id], ['confirm' => __('Are you sure you want to delete # {0}?', $participant->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Participants'), ['action' => 'index'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('New Participant'), ['action' => 'add'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('List Participant Authorization Types'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Participant Authorization Type'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Pending Authorizations'), ['controller' => 'PendingAuthorizations', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Pending Authorization'), ['controller' => 'PendingAuthorizations', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Roles'), ['controller' => 'Roles', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Role'), ['controller' => 'Roles', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="participants view large-9 medium-8 columns content">
    <h3><?= h($participant->sca_name) ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Sca Name') ?></th>
                <td><?= h($participant->sca_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('First Name') ?></th>
                <td><?= h($participant->first_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Middle Name') ?></th>
                <td><?= h($participant->middle_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Last Name') ?></th>
                <td><?= h($participant->last_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Street Address') ?></th>
                <td><?= h($participant->street_address) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('City') ?></th>
                <td><?= h($participant->city) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('State') ?></th>
                <td><?= h($participant->state) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Zip') ?></th>
                <td><?= h($participant->zip) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Phone Number') ?></th>
                <td><?= h($participant->phone_number) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Email Address') ?></th>
                <td><?= h($participant->email_address) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Branch Name') ?></th>
                <td><?= h($participant->branch_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Parent Name') ?></th>
                <td><?= h($participant->parent_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Password Token') ?></th>
                <td><?= h($participant->password_token) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Id') ?></th>
                <td><?= $this->Number->format($participant->id) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Membership Number') ?></th>
                <td><?= $participant->membership_number === null ? '' : $this->Number->format($participant->membership_number) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Failed Login Attempts') ?></th>
                <td><?= $participant->failed_login_attempts === null ? '' : $this->Number->format($participant->failed_login_attempts) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Birth Month') ?></th>
                <td><?= $participant->birth_month === null ? '' : $this->Number->format($participant->birth_month) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Birth Year') ?></th>
                <td><?= $participant->birth_year === null ? '' : $this->Number->format($participant->birth_year) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Last Updated') ?></th>
                <td><?= h($participant->last_updated) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Membership Expires On') ?></th>
                <td><?= h($participant->membership_expires_on) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Background Check Expires On') ?></th>
                <td><?= h($participant->background_check_expires_on) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Password Token Expires On') ?></th>
                <td><?= h($participant->password_token_expires_on) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Last Login') ?></th>
                <td><?= h($participant->last_login) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Last Failed Login') ?></th>
                <td><?= h($participant->last_failed_login) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Deleted Date') ?></th>
                <td><?= h($participant->deleted_date) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Hidden') ?></th>
                <td><?= $participant->hidden ? __('Yes') : __('No'); ?></td>
            </tr>
        </table>
    </div>
    <div class="text">
        <h4><?= __('Notes') ?></h4>
        <?= $this->Text->autoParagraph(h($participant->notes)); ?>
    </div>
    <div class="related">
        <h4><?= __('Related Roles') ?></h4>
        <?php if (!empty($participant->roles)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($participant->roles as $roles): ?>
                <tr>
                    <td><?= h($roles->id) ?></td>
                    <td><?= h($roles->name) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'Roles', 'action' => 'view', $roles->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Html->link(__('Edit'), ['controller' => 'Roles', 'action' => 'edit', $roles->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink( __('Delete'), ['controller' => 'Roles', 'action' => 'delete', $roles->id], ['confirm' => __('Are you sure you want to delete # {0}?', $roles->id), 'class' => 'btn btn-danger']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related">
        <h4><?= __('Related Participant Authorization Types') ?></h4>
        <?php if (!empty($participant->participant_authorization_types)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Participant Id') ?></th>
                    <th scope="col"><?= __('Authorization Type Id') ?></th>
                    <th scope="col"><?= __('Authorized By Id') ?></th>
                    <th scope="col"><?= __('Expires On') ?></th>
                    <th scope="col"><?= __('Start On') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($participant->participant_authorization_types as $participantAuthorizationTypes): ?>
                <tr>
                    <td><?= h($participantAuthorizationTypes->id) ?></td>
                    <td><?= h($participantAuthorizationTypes->participant_id) ?></td>
                    <td><?= h($participantAuthorizationTypes->authorization_type_id) ?></td>
                    <td><?= h($participantAuthorizationTypes->authorized_by_id) ?></td>
                    <td><?= h($participantAuthorizationTypes->expires_on) ?></td>
                    <td><?= h($participantAuthorizationTypes->start_on) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'view', $participantAuthorizationTypes->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Html->link(__('Edit'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'edit', $participantAuthorizationTypes->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink( __('Delete'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'delete', $participantAuthorizationTypes->id], ['confirm' => __('Are you sure you want to delete # {0}?', $participantAuthorizationTypes->id), 'class' => 'btn btn-danger']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related">
        <h4><?= __('Related Pending Authorizations') ?></h4>
        <?php if (!empty($participant->pending_authorizations)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Participant Id') ?></th>
                    <th scope="col"><?= __('Participant Marshal Id') ?></th>
                    <th scope="col"><?= __('Authorization Type Id') ?></th>
                    <th scope="col"><?= __('Authorization Token') ?></th>
                    <th scope="col"><?= __('Requested On') ?></th>
                    <th scope="col"><?= __('Responded On') ?></th>
                    <th scope="col"><?= __('Authorization Result') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($participant->pending_authorizations as $pendingAuthorizations): ?>
                <tr>
                    <td><?= h($pendingAuthorizations->id) ?></td>
                    <td><?= h($pendingAuthorizations->participant_id) ?></td>
                    <td><?= h($pendingAuthorizations->participant_marshal_id) ?></td>
                    <td><?= h($pendingAuthorizations->authorization_type_id) ?></td>
                    <td><?= h($pendingAuthorizations->authorization_token) ?></td>
                    <td><?= h($pendingAuthorizations->requested_on) ?></td>
                    <td><?= h($pendingAuthorizations->responded_on) ?></td>
                    <td><?= h($pendingAuthorizations->authorization_result) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'PendingAuthorizations', 'action' => 'view', $pendingAuthorizations->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Html->link(__('Edit'), ['controller' => 'PendingAuthorizations', 'action' => 'edit', $pendingAuthorizations->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink( __('Delete'), ['controller' => 'PendingAuthorizations', 'action' => 'delete', $pendingAuthorizations->id], ['confirm' => __('Are you sure you want to delete # {0}?', $pendingAuthorizations->id), 'class' => 'btn btn-danger']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
