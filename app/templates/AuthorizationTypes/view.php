<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationType $authorizationType
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); 
$user = $this->request->getAttribute('identity');
?>

<div class="authorizationTypes view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <?= $this->Html->link('', ['action' => 'index'], ['class' => 'bi bi-arrow-left-circle']) ?>
                <?= h($authorizationType->name) ?> 
            </h3>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
            <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $authorizationType->id], ['confirm' => __('Are you sure you want to delete {0}?', $authorizationType->name), 'title' => __('Delete'), 'class' => 'btn btn-danger btn-sm']) ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Name') ?></th>
                <td><?= h($authorizationType->name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Authorization Group') ?></th>
                <td><?= $authorizationType->hasValue('authorization_group') ? $this->Html->link($authorizationType->authorization_group->name, ['controller' => 'AuthorizationGroups', 'action' => 'view', $authorizationType->authorization_group->id]) : '' ?></td>
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
                <th scope="row"><?= __('# of Approvers') ?></th>
                <td><?= $this->Number->format($authorizationType->num_required_authorizors) ?></td>
            </tr>
        </table>
    </div>
    <div class="related">
        <h4><?= __('Authorizing Roles') ?></h4>
        <?php if (!empty($roles)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= h($role->name) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'Roles', 'action' => 'view', $role->id], ['class' => 'btn btn-secondary']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related">
        <h4><?= __('Pending Authorizations') ?></h4>
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
        <h4><?= __('Authorized Members') ?></h4>
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
                    <td><?= h($MemberAuthorizationTypes->approver_id) ?></td>
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
</div>

<?php 
    $this->start('modals');
    echo $this->Modal->create("Edit Authoriztion Type", ['id' => 'editModal', 'close' => true]) ;
?>
    <fieldset>
        <?php
         echo $this->Form->create($authorizationType, ['id' => 'edit_entity', 'url' => ['controller' => 'AuthorizationTypes', 'action' => 'edit', $authorizationType->id]]);
         echo $this->Form->control('name');
         echo $this->Form->control('authorization_groups_id', ['options' => $AuthorizationGroups]);
         echo $this->Form->control('length', ['label'=> 'Duration (years)', 'type' => 'number']);
         echo $this->Form->control('minimum_age', ['type' => 'number']);
         echo $this->Form->control('maximum_age', ['type' => 'number']);
         echo $this->Form->control('num_required_authorizors', ['label' => '# of Approvers', 'type' => 'number']);
         echo $this->Form->end()
                ?>
    </fieldset>
<?php
    echo $this->Modal->end([
        $this->Form->button('Submit',['class' => 'btn btn-primary', 'id' => 'edit_entity__submit', 'onclick' => '$("#edit_entity").submit();']),
        $this->Form->button('Close', ['data-bs-dismiss' => 'modal'])
    ]);
?>

<?php    
//finish writing to modal block in layout
    $this->end(); ?>
