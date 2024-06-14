<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");
$user = $this->request->getAttribute("identity"); //filter pending approvals
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
    <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
    <?= h($queueFor) ?>'s' Auth Request Queue
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
                        <th scope="col" class="actions"><?= __(
                                                            "Actions",
                                                        ) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $request) {
                        $hasMoreApprovalsToGo = false;
                        $authsNeeded = $request->authorization->is_renewal ? $request->authorization->activity->num_required_renewers : $request->authorization->activity->num_required_authorizors;
                        $hasMoreApprovalsToGo = ($authsNeeded - $request->authorization->approval_count) > 1;
                    ?>
                    <tr>
                        <td><?= h(
                                    $request->authorization->member->sca_name,
                                ) ?></td>
                        <td><?= h($request->requested_on) ?></td>
                        <td><?= h(
                                    $request->authorization->activity
                                        ->name,
                                ) ?></td>
                        <td class="actions">
                            <?php if ($hasMoreApprovalsToGo) : ?>
                            <button type="button" class="btn btn-primary " data-bs-toggle="modal"
                                data-bs-target="#approveAndAssignModal"
                                onclick="$('#approve_and_assign_auth_id').val('<?= $request->id ?>'); $('#approve_and_assign_auth_id').trigger('change');">Approve</button>
                            <?php else : ?>
                            <?= $this->Form->postLink(
                                        __("Approve"),
                                        ["action" => "approve", $request->id],
                                        [
                                            "confirm" => __(
                                                "Are you sure you want to approve {0} for {1}?",
                                                $request->authorization->member
                                                    ->sca_name,
                                                $request->authorization
                                                    ->activity->name,
                                            ),
                                            "title" => __("Approve"),
                                            "class" => "btn btn-primary",
                                        ],
                                    ) ?>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary " data-bs-toggle="modal"
                                data-bs-target="#denyModal"
                                onclick="$('#deny_auth__id').val('<?= $request->id ?>')">Deny</button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="tab-pane fade" id="nav-approved-approvals" role="tabpanel" aria-labelledby="nav-approved-approvals-tab"
        tabindex="0">
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
                    <?php foreach ($approved as $request) : ?>
                    <tr>
                        <td><?= h(
                                    $request->authorization->member->sca_name,
                                ) ?></td>
                        <td><?= h($request->requested_on) ?></td>
                        <td><?= h($request->responded_on) ?></td>
                        <td><?= h(
                                    $request->authorization->activity
                                        ->name,
                                ) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="tab-pane fade" id="nav-denied-approvals" role="tabpanel" aria-labelledby="nav-denied-approvals-tab"
        tabindex="0">
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
                    <?php foreach ($denied as $request) : ?>
                    <tr>
                        <td><?= h(
                                    $request->authorization->member->sca_name,
                                ) ?></td>
                        <td><?= h($request->requested_on) ?></td>
                        <td><?= h($request->responded_on) ?></td>
                        <td><?= h(
                                    $request->authorization->activity
                                        ->name,
                                ) ?></td>
                        <td><?= h($request->approver_notes) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<?php
$this->start("modals");
echo $this->Modal->create("Deny Authorization", [
    "id" => "denyModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create(null, [
        "url" => ["controller" => "AuthorizationApprovals", "action" => "deny"],
        "id" => "deny_auth",
    ]);
    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "deny_auth__id",
    ]);
    echo $this->Form->control("approver_notes", [
        "label" => "Reason for Denial",
        "onkeypress" => '$("#deny_auth__submit").removeAttr("disabled");',
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "deny_auth__submit",
        "onclick" => '$("#deny_auth").submit();',
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);

echo $this->Modal->create("Approve and Assign to next", [
    "id" => "approveAndAssignModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create(null, [
        "url" => [
            "controller" => "AuthorizationApprovals",
            "action" => "Approve",
        ],
        "id" => "approve_and_assign_auth",
    ]);
    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "approve_and_assign_auth_id",
    ]);
    echo $this->Form->control("next_approver_id", [
        "label" => "Forward to",
        "id" => "approve_and_assign_auth_approver_id",
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "approve_and_assign_auth__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>

<?php //finish writing to modal block in layout

$this->end(); ?>

<?php $this->append(
    "script",
    $this->Html->script(["app/authorization_approvals/view_and_my_queue.js"]),
);

$this->append("script", $this->Html->scriptBlock("
var pageControl = new authorizationApprovalViewAndMyQueue();
pageControl.run('" . $this->Url->webroot("") . "');
"));
?>