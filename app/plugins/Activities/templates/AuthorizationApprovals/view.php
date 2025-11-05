<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php

function makePossessive($name)
{
    // Trim whitespace and ensure proper formatting
    $name = trim($name);

    // Check if the name ends with 's'
    if (strtolower(substr($name, -1)) === 's') {
        return $name . "'";
    } else {
        return $name . "'s";
    }
}

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Authorization Queue for ' . $queueFor;
$this->KMP->endBlock();

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

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("pageTitle") ?>

<?= makePossessive($queueFor) ?> Auth Request Queue
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-pending-approvals-tab" data-bs-toggle="tab" data-bs-target="#nav-pending-approvals"
    type="button" role="tab" aria-controls="nav-pending-approvals" aria-selected="false"
    data-detail-tabs-target='tabBtn'>Pending</button>
<button class="nav-link" id="nav-approved-approvals-tab" data-bs-toggle="tab" data-bs-target="#nav-approved-approvals"
    type="button" role="tab" aria-controls="nav-approved-approvals" aria-selected="false"
    data-detail-tabs-target='tabBtn'>Approved</button>
<button class="nav-link" id="nav-denied-approvals-tab" data-bs-toggle="tab" data-bs-target="#nav-denied-approvals"
    type="button" role="tab" aria-controls="nav-denied-approvals" aria-selected="false"
    data-detail-tabs-target='tabBtn'>Denied</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="tab-pane fade m-3" id="nav-pending-approvals" role="tabpanel" aria-labelledby="nav-pending-approvals-tab"
    tabindex="0" data-detail-tabs-target="tabContent">
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col">Requester</th>
                    <th scope="col">Request Date</th>
                    <th scope="col">Authorization</th>
                    <th scope="col">Member Number</th>
                    <th scope="col">Member Exp</th>
                    <th scope="col">Background Check Exp</th>
                    <th scope="col" class="actions"></th>
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
                        <td><?= $this->Timezone->format($request->requested_on, null, null, \IntlDateFormatter::SHORT) ?></td>
                        <td><?= h(
                                $request->authorization->activity
                                    ->name,
                            ) ?></td>
                        <td><?= h(
                                $request->authorization->member->membership_number,
                            ) ?></td>
                        <td><?= $this->Timezone->format(
                                $request->authorization->member->membership_expires_on,
                                null,
                                null,
                                \IntlDateFormatter::SHORT
                            ) ?></td>
                        <td><?= $request->authorization->member->background_check_expires_on ? $this->Timezone->format(
                                $request->authorization->member->background_check_expires_on,
                                null,
                                null,
                                \IntlDateFormatter::SHORT
                            ) : '' ?></td>
                        <td class="actions text-end text-nowrap">
                            <?php if ($hasMoreApprovalsToGo) : ?>
                                <button type="button" class="btn btn-primary approve-btn" data-bs-toggle="modal"
                                    data-bs-target="#approveAndAssignModal" data-bs-target="#denyModal"
                                    data-controller="outlet-btn" data-action="click->outlet-btn#fireNotice"
                                    data-outlet-btn-btn-data-value='{"id":<?= $request->id ?>}'>Approve</button>
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
                                        "class" => "btn-sm btn btn-primary",
                                    ],
                                ) ?>
                            <?php endif; ?>
                            <button type="button" class="btn-sm btn btn-secondary deny-btn" data-bs-toggle="modal"
                                data-bs-target="#denyModal" data-controller="outlet-btn"
                                data-action="click->outlet-btn#fireNotice"
                                data-outlet-btn-btn-data-value='{"id":<?= $request->id ?>}'>
                                Deny</button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<div class="tab-pane fade m-3" id="nav-approved-approvals" role="tabpanel" aria-labelledby="nav-approved-approvals-tab"
    tabindex="0" data-detail-tabs-target="tabContent">
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
                        <td><?= $this->Timezone->format($request->requested_on, null, null, \IntlDateFormatter::SHORT) ?></td>
                        <td><?= $this->Timezone->format($request->responded_on, null, null, \IntlDateFormatter::SHORT) ?></td>
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
<div class="tab-pane fade m-3" id="nav-denied-approvals" role="tabpanel" aria-labelledby="nav-denied-approvals-tab"
    tabindex="0" data-detail-tabs-target="tabContent">
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
                        <td><?= $this->Timezone->format($request->requested_on, null, null, \IntlDateFormatter::SHORT) ?></td>
                        <td><?= $this->Timezone->format($request->responded_on, null, null, \IntlDateFormatter::SHORT) ?></td>
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
$this->KMP->endBlock();
echo $this->KMP->startBlock("modals");
echo $this->Form->create(null, [
    "url" => ["controller" => "AuthorizationApprovals", "action" => "deny"],
    "data-controller" => "revoke-form",
    "data-revoke-form-outlet-btn-outlet" => ".deny-btn",
]);
echo $this->Modal->create("Deny Authorization", [
    "id" => "denyModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("id", [
        "type" => "hidden",
        "data-revoke-form-target" => "id",
    ]);
    echo $this->Form->control("approver_notes", [
        "label" => "Reason for Denial",
        "data-revoke-form-target" => "reason",
        "data-action" => "input->revoke-form#checkReadyToSubmit",
        "help" => "This message will be visible to the requester"
    ]);
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-revoke-form-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
echo $this->Form->create(null, [
    "url" => [
        "controller" => "AuthorizationApprovals",
        "action" => "Approve",
    ],
    "data-controller" => "activities-approve-and-assign-auth",
    "data-activities-approve-and-assign-auth-outlet-btn-outlet" => ".approve-btn",
    "data-activities-approve-and-assign-auth-url-value" => $this->Url->build(['plugin' => 'activities', 'controller' => 'AuthorizationApprovals', 'action' => 'AvailableApproversList']),
]);
echo $this->Modal->create("Approve and Assign to next", [
    "id" => "approveAndAssignModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("id", [
        "type" => "hidden",
        "data-activities-approve-and-assign-auth-target" => 'id'
    ]);
    echo $this->KMP->comboBoxControl(
        $this->Form,
        'next_approver_name',
        'next_approver_id',
        [],
        "Forward to",
        true,
        false,
        [
            'data-activities-approve-and-assign-auth-target' => 'approvers',
            'data-action' => 'change->activities-approve-and-assign-auth#checkReadyToSubmit'
        ]
    );
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-activities-approve-and-assign-auth-target" => 'submitBtn'
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
$this->KMP->endBlock(); ?>