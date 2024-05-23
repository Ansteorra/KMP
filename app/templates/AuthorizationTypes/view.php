<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationType $authorizationType
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');
use Cake\I18n\Date;

$user = $this->request->getAttribute('identity');

$pending = [];
$approved = [];
$expired = [];
$exp_date = Date::now();
foreach ($authorizationType->authorizations as $auth) {
    if ($auth->expires_on === null) {
        $pending[] = $auth;
    } elseif ($auth->expires_on < $exp_date) {
        $expired[] = $auth;
    } else {
        $approved[] = $auth;
    }
}

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
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#editModal">Edit</button>
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
                <td><?= $authorizationType->hasValue('authorization_group') ? $this->Html->link($authorizationType->authorization_group->name, ['controller' => 'AuthorizationGroups', 'action' => 'view', $authorizationType->authorization_group->id]) : '' ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Length') ?></th>
                <td><?= $this->Number->format($authorizationType->length) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Minimum Age') ?></th>
                <td><?= $authorizationType->minimum_age === null ? '' : $this->Number->format($authorizationType->minimum_age) ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Maximum Age') ?></th>
                <td><?= $authorizationType->maximum_age === null ? '' : $this->Number->format($authorizationType->maximum_age) ?>
                </td>
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
        <h4><?= __('Authorizations') ?></h4>
        <?php if (!empty($authorizationType->authorizations)) {
            $pending = [];
            $approved = [];
            $expired = [];
            $exp_date = Date::now();
            foreach ($authorizationType->authorizations as $auth) {
                if ($auth->expires_on === null) {
                    $pending[] = $auth;
                } elseif ($auth->expires_on < $exp_date) {
                    $expired[] = $auth;
                } else {
                    $approved[] = $auth;
                }
            }

            ?>
            <nav>
                <div class="nav nav-tabs" id="nav-tab" role="tablist">
                    <button class="nav-link active" id="nav-active-approvals-tab" data-bs-toggle="tab"
                        data-bs-target="#nav-active-approvals" type="button" role="tab" aria-controls="nav-active-approvals"
                        aria-selected="true">Approved</button>
                    <button class="nav-link" id="nav-expired-approvals-tab" data-bs-toggle="tab"
                        data-bs-target="#nav-expired-approvals" type="button" role="tab"
                        aria-controls="nav-expired-approvals" aria-selected="false">Expired</button>
                    <button class="nav-link" id="nav-pending-approvals-tab" data-bs-toggle="tab"
                        data-bs-target="#nav-pending-approvals" type="button" role="tab"
                        aria-controls="nav-pending-approvals" aria-selected="false">Pending
                        <span class="badge bg-danger"><?= count($pending) ?></span>
                    </button>
                </div>
            </nav>
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade show active" id="nav-active-approvals" role="tabpanel"
                    aria-labelledby="nav-active-approvals-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <tr>
                                <th scope="col"><?= __('Member') ?></th>
                                <th scope="col"><?= __('Start On') ?></th>
                                <th scope="col"><?= __('Expires On') ?></th>
                                <th scope="col"><?= __('Approved By') ?></th>
                            </tr>
                            <?php foreach ($approved as $auth): ?>
                                <tr>
                                    <td><?= h($auth->member->sca_name) ?></td>
                                    <td><?= h($auth->start_on) ?></td>
                                    <td><?= h($auth->expires_on) ?></td>
                                    <td>
                                        <?php // if not empty make a list of approvers with their ids and sca_names, then link them to their view page
                                        if (!empty($auth->authorization_approvals)): ?>
                                            <ul>
                                                <?php foreach ($auth->authorization_approvals as $approval): ?>
                                                    <li>
                                                        <?= $this->Html->link($approval->approver->sca_name, ['controller' => 'Members', 'action' => 'view', $approval->approver->id]) ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="nav-expired-approvals" role="tabpanel"
                    aria-labelledby="nav-expired-approvals-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <tr>
                                <th scope="col"><?= __('Member') ?></th>
                                <th scope="col"><?= __('Start On') ?></th>
                                <th scope="col"><?= __('Expires On') ?></th>
                            </tr>
                            <?php foreach ($expired as $auth) {
                                $assigned_to = '';
                                ?>
                                <tr>
                                    <td><?= h($auth->member->sca_name) ?></td>
                                    <td><?= h($auth->start_on) ?></td>
                                    <td><?= h($auth->expires_on) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="nav-pending-approvals" role="tabpanel"
                    aria-labelledby="nav-pending-approvals-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <tr>
                                <th scope="col"><?= __('Member') ?></th>
                                <th scope="col"><?= __('Requested On') ?></th>
                                <th scope="col"><?= __('Assigned To') ?></th>
                            </tr>
                            <?php foreach ($pending as $auth): ?>
                                <tr>
                                    <td><?= h($auth->member->sca_name) ?></td>
                                    <td><?= h($auth->requested_on) ?></td>
                                    <td>
                                        <?php if (!empty($auth->authorization_approvals)): ?>
                                            <?= h($auth->authorization_approvals[0]->approver->sca_name) ?>
                                        </td>
                                    <?php else: ?>
                                        Unassigned
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<?php
$this->start('modals');
echo $this->Modal->create("Edit Authoriztion Type", ['id' => 'editModal', 'close' => true]);
?>
<fieldset>
    <?php
    echo $this->Form->create($authorizationType, ['id' => 'edit_entity', 'url' => ['controller' => 'AuthorizationTypes', 'action' => 'edit', $authorizationType->id]]);
    echo $this->Form->control('name');
    echo $this->Form->control('authorization_groups_id', ['options' => $AuthorizationGroups]);
    echo $this->Form->control('length', ['label' => 'Duration (years)', 'type' => 'number']);
    echo $this->Form->control('minimum_age', ['type' => 'number']);
    echo $this->Form->control('maximum_age', ['type' => 'number']);
    echo $this->Form->control('num_required_authorizors', ['label' => '# of Approvers', 'type' => 'number']);
    echo $this->Form->end()
        ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button('Submit', ['class' => 'btn btn-primary', 'id' => 'edit_entity__submit', 'onclick' => '$("#edit_entity").submit();']),
    $this->Form->button('Close', ['data-bs-dismiss' => 'modal'])
]);
?>

<?php
//finish writing to modal block in layout
$this->end(); ?>