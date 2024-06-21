<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");


echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Authorization Queue for ' . $queueFor;
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
<?= h($queueFor) ?>'s' Auth Request Queue
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-pending-approvals-tab" data-bs-toggle="tab" data-bs-target="#nav-pending-approvals"
    type="button" role="tab" aria-controls="nav-pending-approvals" aria-selected="false">Pending</button>
<button class="nav-link" id="nav-approved-approvals-tab" data-bs-toggle="tab" data-bs-target="#nav-approved-approvals"
    type="button" role="tab" aria-controls="nav-approved-approvals" aria-selected="false">Approved</button>
<button class="nav-link" id="nav-denied-approvals-tab" data-bs-toggle="tab" data-bs-target="#nav-denied-approvals"
    type="button" role="tab" aria-controls="nav-denied-approvals" aria-selected="false">Denied</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="tab-pane fade m-3" id="nav-pending-approvals" role="tabpanel" aria-labelledby="nav-pending-approvals-tab"
    tabindex="0">
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
<div class="tab-pane fade m-3" id="nav-approved-approvals" role="tabpanel" aria-labelledby="nav-approved-approvals-tab"
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
<div class="tab-pane fade m-3" id="nav-denied-approvals" role="tabpanel" aria-labelledby="nav-denied-approvals-tab"
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
$this->KMP->endBlock();
echo $this->KMP->startBlock("modals");
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

$this->KMP->endBlock();
echo $this->KMP->startBlock("script"); ?>
<script>
class authorizationApprovalViewAndMyQueue {
    constructor() {};
    //onInput for Autocomplete

    run() {
        var me = this;
        $("#approve_and_assign_auth_id").change(function() {
            var auth_id = this.value;
            if (auth_id > 0) {
                var approversUrl =
                    '<?= $this->Url->build(['plugin' => 'activities', 'controller' => 'AuthorizationApprovals', 'action' => 'AvailableApproversList']) ?>';
                //query GET: members/approvers_list/{auth_type_id}
                $.get(approversUrl + '/' + auth_id, function(
                    data) {
                    //remove all options
                    $('#approve_and_assign_auth_approver_id').find('option').remove();
                    //add new options
                    $('#approve_and_assign_auth_approver_id').append('<option value="0"></option>');
                    $.each(data, function(key, value) {
                        $('#approve_and_assign_auth_approver_id').append('<option value="' +
                            value.id + '">' + value.sca_name + '</option>');
                    });
                });
                $('#approve_and_assign_auth_approver_id').prop('disabled', false);
            } else {
                //remove all options
                $('#approve_and_assign_auth_approver_id').find('option').remove();
                $('#approve_and_assign_auth_approver_id').prop('disabled', true);
            }
        });
        $("#approve_and_assign_auth_approver_id").change(function() {
            var end = this.value;
            if (end > 0) {
                $('#approve_and_assign_auth__submit').prop('disabled', false);
            } else {
                $('#approve_and_assign_auth__submit').prop('disabled', true);
            }
        });
        $('#approve_and_assign_auth__submit').on('click', function() {
            if ($('#approve_and_assign_auth_approver_id').val() > 0) {
                $('#approve_and_assign_auth').submit();
            }
        });
    }
}
window.addEventListener('DOMContentLoaded', function() {
    var pageControl = new authorizationApprovalViewAndMyQueue();
    pageControl.run();
});
</script>

<?php $this->KMP->endBlock(); ?>