<?php

/**
 * @var \App\View\AppView $this
 * @var string $queueFor
 * @var bool $isMyQueue
 * @var int|string $member_id
 * @var string|null $token
 */

// Use Kmp helper for common utilities

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': My Authorization Queue';
$this->KMP->endBlock();

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("pageTitle") ?>
<?= $this->Kmp->makePossessive($queueFor) ?> Auth Request Queue
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock("recordActions") ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("recordDetails") ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<div class="tab-pane fade show active m-3" role="tabpanel" tabindex="0">
    <?php
    $dataUrl = $this->Url->build(['action' => 'myQueueGridData']);
    if ($token) {
        $dataUrl .= '?token=' . urlencode($token);
    }
    ?>
    <?= $this->element('dv_grid', [
        'gridKey' => 'Activities.AuthorizationApprovals.myQueue',
        'frameId' => 'my-queue-grid',
        'dataUrl' => $dataUrl,
    ]) ?>
</div>
<?php $this->KMP->endBlock() ?>

<?php
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