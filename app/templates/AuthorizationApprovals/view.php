<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

$user = $this->request->getAttribute('identity');
//filter pending approvals
$pending = [];
$approved = [];
$denied = [];

foreach ($authorizationApprovals as $authorizationApproval) {
    if ($authorizationApproval->responded_on == null) {
        $pending[] = $authorizationApproval;
    } elseif ($authorizationApproval->approved == 1) {
        $approved[] = $authorizationApproval;
    } else {
        $denied[] = $authorizationApproval;
    }
}
//sort by requested_on
usort($pending, function ($a, $b) {
    return $a->requested_on <=> $b->requested_on;
});
//sort decending on responded_on
usort($approved, function ($a, $b) {
    return $b->responded_on <=> $a->responded_on;
});
//sort decending on responded_on
usort($denied, function ($a, $b) {
    return $b->responded_on <=> $a->responded_on;
});
?>
<h3>
    <?= h($user->sca_name) ?>'s' Auth Request Queue
</h3>

<nav>
    <div class="nav nav-tabs" id="nav-tab" role="tablist">
        <button class="nav-link active" id="nav-pending-approvals-tab" data-bs-toggle="tab"
            data-bs-target="#nav-pending-approvals" type="button" role="tab" aria-controls="nav-pending-approvals"
            aria-selected="true">Pending</button>
        <button class="nav-link" id="nav-approved-approvals-tab" data-bs-toggle="tab"
            data-bs-target="#nav-approved-approvals" type="button" role="tab" aria-controls="nav-approved-approvals"
            aria-selected="false">Approved</button>
        <button class="nav-link" id="nav-denied-approvals-tab" data-bs-toggle="tab"
            data-bs-target="#nav-denied-approvals" type="button" role="tab" aria-controls="nav-denied-approvals"
            aria-selected="false">Denied</button>
    </div>
</nav>
<div class="tab-content" id="nav-tabContent">
    <div class="tab-pane fade show active" id="nav-pending-approvals" role="tabpanel"
        aria-labelledby="nav-pending-approvals-tab" tabindex="0">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">Requester</th>
                        <th scope="col">Request Date</th>
                        <th scope="col">Authorization</th>
                        <th scope="col" class="actions"><?= __('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $request): ?>
                        <tr>
                            <td><?= h($request->authorization->member->sca_name) ?></td>
                            <td><?= h($request->requested_on) ?></td>
                            <td><?= h($request->authorization->authorization_type->name) ?></td>
                            <td class="actions">
                                <?= $this->Form->postLink(__('Approve'), ['action' => 'approve', $request->id], ['confirm' => __('Are you sure you want to approve {0} for {1}?', $request->authorization->member->sca_name, $request->authorization->authorization_type->name), 'title' => __('Approve'), 'class' => 'btn btn-primary']) ?>
                                <button type="button" class="btn btn-secondary " data-bs-toggle="modal"
                                    data-bs-target="#denyModal" onclick="$('#deny_auth__id').val('<?=$request->id?>')" >Deny</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="tab-pane fade" id="nav-approved-approvals" role="tabpanel"
        aria-labelledby="nav-approved-approvals-tab" tabindex="0">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">Requester</th>
                        <th scope="col">Request Date</th>
                        <th scope="col">Response Date</th>
                        <th scope="col">Authorization</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approved as $request): ?>
                        <tr>
                            <td><?= h($request->authorization->member->sca_name) ?></td>
                            <td><?= h($request->requested_on) ?></td>
                            <td><?= h($request->responded_on) ?></td>
                            <td><?= h($request->authorization->authorization_type->name) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="tab-pane fade" id="nav-denied-approvals" role="tabpanel"
        aria-labelledby="nav-denied-approvals-tab" tabindex="0">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">Requester</th>
                        <th scope="col">Request Date</th>
                        <th scope="col">Response Date</th>
                        <th scope="col">Authorization</th>
                        <th scope="col">Denial Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($denied as $request): ?>
                        <tr>
                            <td><?= h($request->authorization->member->sca_name) ?></td>
                            <td><?= h($request->requested_on) ?></td>
                            <td><?= h($request->responded_on) ?></td>
                            <td><?= h($request->authorization->authorization_type->name) ?></td>
                            <td><?= h($request->approver_notes) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<?php
$this->start('modals');
echo $this->Modal->create("Add App Setting", ['id' => 'denyModal', 'close' => true]);
?>
<fieldset>
    <?php
    echo $this->Form->create(null,['url' => ['controller'=>'AuthorizationApprovals', 'action' => 'deny'], 'id' => 'deny_auth']);
    echo $this->Form->control('id', ['type' => 'hidden', 'id'=> 'deny_auth__id']);
    echo $this->Form->control('approver_notes', ['label' => 'Reason for Denial', 'onkeypress'=>'$("#deny_auth__submit").removeAttr("disabled");']);
    echo $this->Form->end()
        ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button('Submit', ['class' => 'btn btn-primary', 'id' => 'deny_auth__submit', 'onclick' => '$("#deny_auth").submit();', 'disabled' => 'disabled']),
    $this->Form->button('Close', ['data-bs-dismiss' => 'modal'])
]);
?>

<?php
//finish writing to modal block in layout
$this->end(); ?>