<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WarrantRoster $warrantRoster
 */

use App\Model\Entity\WarrantRoster;
use App\Model\Entity\Warrant;
?>
<?php $this->extend('/layout/TwitterBootstrap/view_record');

$userApprovedAlready = false;
foreach ($warrantRoster->warrant_roster_approvals as $approval) {
    if ($approval->approver_id == $user->id) {
        $userApprovedAlready = true;
    }
}

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Warrant Approval Set - ' . $warrantRoster->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($warrantRoster->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($warrantRoster->status == Warrant::PENDING_STATUS && $user->checkCan("approve", $warrantRoster)) :
    if (!$userApprovedAlready): ?>
<?= $this->Form->postLink(__('Approve'), ['controller' => 'WarrantRosters', 'action' => 'approve', $warrantRoster->id], ['confirm' => __('Are you sure you want to approve # {0}?', $warrantRoster->name), 'class' => 'btn btn-primary']) ?>
<?php endif ?>
<?php endif ?>
<?php if ($warrantRoster->status == Warrant::PENDING_STATUS && $user->checkCan("decline", $warrantRoster)) :
    if (!$userApprovedAlready): ?>
<?= $this->Form->postLink(__('Decline'), ['controller' => 'WarrantRosters', 'action' => 'decline', $warrantRoster->id], ['confirm' => __('Are you sure you want to decline # {0}?', $warrantRoster->name), 'class' => 'btn btn-danger']) ?>
<?php endif ?>
<?php endif ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __('Status') ?></th>
    <td><?= h($warrantRoster->status) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Approvals Required') ?></th>
    <td><?= $this->Number->format($warrantRoster->approvals_required) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Approval Count') ?></th>
    <td><?= $warrantRoster->approval_count === null ? '' : $this->Number->format($warrantRoster->approval_count) ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Created By') ?></th>
    <td><?= $warrantRoster->created_by === null ? '' : $warrantRoster->created_by_member->sca_name ?>
    </td>
</tr>
<?= $this->element('pluginDetailBodies', [
    'pluginViewCells' => $pluginViewCells,
    'id' => $warrantRoster->id
]) ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-warrants-tab" data-bs-toggle="tab" data-bs-target="#nav-warrants" type="button"
    role="tab" aria-controls="nav-warrants" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Warrants") ?>
</button>
<button class="nav-link" id="nav-approvals-tab" data-bs-toggle="tab" data-bs-target="#nav-approvals" type="button"
    role="tab" aria-controls="nav-approvals" aria-selected="false"
    data-detail-tabs-target='tabBtn'><?= __("Approval Log") ?>
</button>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Notes") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="tab-content" id="nav-tabContent">
    <div class="related tab-pane fade m-3" id="nav-warrants" role="tabpanel" aria-labelledby="nav-warrants-tab"
        data-detail-tabs-target="tabContent">
        <?php if (!empty($warrantRoster->warrants)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col"><?= __('Member') ?></th>
                    <th scope="col"><?= __('Start On') ?></th>
                    <th scope="col"><?= __('Expires On') ?></th>
                    <th scope="col"><?= __('Status') ?></th>
                    <th scope="col" class="actions"></th>
                </tr>
                <?php foreach ($warrantRoster->warrants as $warrant): ?>
                <tr>
                    <td><?= h($warrant->name) ?></td>
                    <td>
                        <?= $this->Html->link(
                                    h($warrant->member->sca_name),
                                    ['controller' => 'Members', 'action' => 'view', $warrant->member->id],
                                    ['class' => 'text-decoration-none', 'target' => '_blank']
                                ) ?>
                    </td>
                    <td><?= $this->Timezone->format($warrant->start_on, null, null, \IntlDateFormatter::SHORT) ?></td>
                    <td><?= $this->Timezone->format($warrant->expires_on, null, null, \IntlDateFormatter::SHORT) ?></td>
                    <td><?= h($warrant->status) ?></td>
                    <td class="actions text-end text-nowrap">
                        <?php if ($warrant->status == Warrant::PENDING_STATUS && $user->checkCan("decline", "WarrantRosters")) :
                                    if (!$userApprovedAlready): ?>
                        <?= $this->Form->postLink(__('Release from Office'), ['controller' => 'WarrantRosters', 'action' => 'declineWarrantInRoster', $warrantRoster->id, $warrant->id], ['confirm' => __('This will decline this individual warrant and release {0} from their office.', $warrant->member->sca_name), 'class' => 'btn-sm btn btn-danger']) ?>
                        <?php endif ?>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related tab-pane fade m-3" id="nav-approvals" role="tabpanel" aria-labelledby="nav-approvals-tab"
        data-detail-tabs-target="tabContent">

        <?php if (!empty($warrantRoster->warrant_roster_approvals)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Approver') ?></th>
                    <th scope="col"><?= __('Responded On') ?></th>
                </tr>
                <?php foreach ($warrantRoster->warrant_roster_approvals as $warrantRosterApprovals): ?>
                <tr>
                    <td><?= h($warrantRosterApprovals->member->sca_name) ?></td>
                    <td><?= $this->Timezone->format($warrantRosterApprovals->approved_on, null, null, \IntlDateFormatter::SHORT) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab"
        data-detail-tabs-target="tabContent">
        <?= $this->cell('Notes', [
            'entity_id' => $warrantRoster->id,
            'entity_type' => 'WarrantRosters',
            'viewPrivate' => $user->checkCan("viewPrivateNotes", "WarrantRosters"),
        ]) ?>
    </div>
</div>
<?php $this->KMP->endBlock() ?>