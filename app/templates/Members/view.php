<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<div class="Members view large-9 medium-8 columns content">
    <h3><?= h($Member->sca_name) ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Sca Name') ?></th>
                <td><?= h($Member->sca_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('First Name') ?></th>
                <td><?= h($Member->first_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Middle Name') ?></th>
                <td><?= h($Member->middle_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Last Name') ?></th>
                <td><?= h($Member->last_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Street Address') ?></th>
                <td><?= h($Member->street_address) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('City') ?></th>
                <td><?= h($Member->city) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('State') ?></th>
                <td><?= h($Member->state) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Zip') ?></th>
                <td><?= h($Member->zip) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Phone Number') ?></th>
                <td><?= h($Member->phone_number) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Email Address') ?></th>
                <td><?= h($Member->email_address) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Branch') ?></th>
                <td><?= h($Member->branch->name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Parent Name') ?></th>
                <td><?= h($Member->parent_name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Membership Number') ?></th>
                <td><?= h($Member->membership_number) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Birth Date') ?></th>
                <td><?=  h($Member->birth_month) - h($Member->birth_year) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Membership Expires On') ?></th>
                <td><?= h($Member->membership_expires_on) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Background Check Expires On') ?></th>
                <td><?= h($Member->background_check_expires_on) ?></td>
            </tr>
        </table>
    </div>
    <div class="text">
        <h4><?= __('Notes') ?></h4>
        <?= $this->Text->autoParagraph(h($Member->notes)); ?>
    </div>
    <div class="related">
        <h4><?= __('Related Roles') ?></h4>
        <?php if (!empty($Member->roles)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($Member->roles as $roles): ?>
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
        <h4><?= __('Related Member Authorization Types') ?></h4>
        <?php if (!empty($Member->Member_authorization_types)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Member Id') ?></th>
                    <th scope="col"><?= __('Authorization Type Id') ?></th>
                    <th scope="col"><?= __('Authorized By Id') ?></th>
                    <th scope="col"><?= __('Expires On') ?></th>
                    <th scope="col"><?= __('Start On') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($Member->Member_authorization_types as $MemberAuthorizationTypes): ?>
                <tr>
                    <td><?= h($MemberAuthorizationTypes->id) ?></td>
                    <td><?= h($MemberAuthorizationTypes->Member_id) ?></td>
                    <td><?= h($MemberAuthorizationTypes->authorization_type_id) ?></td>
                    <td><?= h($MemberAuthorizationTypes->authorized_by_id) ?></td>
                    <td><?= h($MemberAuthorizationTypes->expires_on) ?></td>
                    <td><?= h($MemberAuthorizationTypes->start_on) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'view', $MemberAuthorizationTypes->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Html->link(__('Edit'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'edit', $MemberAuthorizationTypes->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink( __('Delete'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'delete', $MemberAuthorizationTypes->id], ['confirm' => __('Are you sure you want to delete # {0}?', $MemberAuthorizationTypes->id), 'class' => 'btn btn-danger']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related">
        <h4><?= __('Related Pending Authorizations') ?></h4>
        <?php if (!empty($Member->pending_authorizations)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Member Id') ?></th>
                    <th scope="col"><?= __('Member Marshal Id') ?></th>
                    <th scope="col"><?= __('Authorization Type Id') ?></th>
                    <th scope="col"><?= __('Authorization Token') ?></th>
                    <th scope="col"><?= __('Requested On') ?></th>
                    <th scope="col"><?= __('Responded On') ?></th>
                    <th scope="col"><?= __('Authorization Result') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($Member->pending_authorizations as $pendingAuthorizations): ?>
                <tr>
                    <td><?= h($pendingAuthorizations->id) ?></td>
                    <td><?= h($pendingAuthorizations->Member_id) ?></td>
                    <td><?= h($pendingAuthorizations->Member_marshal_id) ?></td>
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
