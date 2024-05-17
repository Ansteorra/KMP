<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationType $authorizationType
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('Edit Authorization Type'), ['action' => 'edit', $authorizationType->id], ['class' => 'nav-link']) ?></li>
<li><?= $this->Form->postLink(__('Delete Authorization Type'), ['action' => 'delete', $authorizationType->id], ['confirm' => __('Are you sure you want to delete # {0}?', $authorizationType->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Authorization Types'), ['action' => 'index'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('New Authorization Type'), ['action' => 'add'], ['class' => 'nav-link']) ?> </li>
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

<div class="authorizationTypes view large-9 medium-8 columns content">
    <h3><?= h($authorizationType->name) ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Name') ?></th>
                <td><?= h($authorizationType->name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Martial Group') ?></th>
                <td><?= $authorizationType->hasValue('martial_group') ? $this->Html->link($authorizationType->martial_group->name, ['controller' => 'MartialGroups', 'action' => 'view', $authorizationType->martial_group->id]) : '' ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Id') ?></th>
                <td><?= $this->Number->format($authorizationType->id) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Length') ?></th>
                <td><?= $this->Number->format($authorizationType->length) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Minimum Age') ?></th>
                <td><?= $authorizationType->minimum_age === null ? '' : $this->Number->format($authorizationType->minimum_age) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Maximum Age') ?></th>
                <td><?= $authorizationType->maximum_age === null ? '' : $this->Number->format($authorizationType->maximum_age) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Num Required Authorizors') ?></th>
                <td><?= $this->Number->format($authorizationType->num_required_authorizors) ?></td>
            </tr>
        </table>
    </div>
    <div class="related">
        <h4><?= __('Related Member Authorization Types') ?></h4>
        <?php if (!empty($authorizationType->Member_authorization_types)): ?>
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
                <?php foreach ($authorizationType->Member_authorization_types as $MemberAuthorizationTypes): ?>
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
        <?php if (!empty($authorizationType->pending_authorizations)): ?>
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
                <?php foreach ($authorizationType->pending_authorizations as $pendingAuthorizations): ?>
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
    <div class="related">
        <h4><?= __('Related Permissions') ?></h4>
        <?php if (!empty($authorizationType->permissions)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col"><?= __('Authorization Type Id') ?></th>
                    <th scope="col"><?= __('System') ?></th>
                    <th scope="col"><?= __('Is Super User') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($authorizationType->permissions as $permissions): ?>
                <tr>
                    <td><?= h($permissions->id) ?></td>
                    <td><?= h($permissions->name) ?></td>
                    <td><?= h($permissions->authorization_type_id) ?></td>
                    <td><?= h($permissions->system) ?></td>
                    <td><?= h($permissions->is_super_user) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'Permissions', 'action' => 'view', $permissions->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Html->link(__('Edit'), ['controller' => 'Permissions', 'action' => 'edit', $permissions->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink( __('Delete'), ['controller' => 'Permissions', 'action' => 'delete', $permissions->id], ['confirm' => __('Are you sure you want to delete # {0}?', $permissions->id), 'class' => 'btn btn-danger']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
