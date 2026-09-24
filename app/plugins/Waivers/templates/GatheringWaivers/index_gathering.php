<?php

/**
 * Gathering Waivers for a Specific Gathering
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\GatheringWaiver[]|\Cake\Collection\CollectionInterface $gatheringWaivers
 * @var array $countsMap
 * @var array $requiredWaiverTypes
 * @var bool $waiverCollectionClosed
 * @var bool $waiverReadyToClose
 * @var bool $canCloseWaivers
 * @var \Waivers\Model\Entity\GatheringWaiverClosure|null $waiverClosure
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': ' . $gathering->name . ' - Waivers';
$this->KMP->endBlock();
?>

<div class="row align-items-start">
    <div class="col">
        <h3>
            <?= $this->element('backButton') ?>
            <?= __('Waivers for {0}', h($gathering->name)) ?>
            <small class="text-muted d-block fs-6">
                <?= h($gathering->branch->name) ?>
                &mdash;
                <?= $this->Timezone->format($gathering->start_date, $gathering, 'M d, Y') ?>
                <?php if (!empty($gathering->gathering_activities)): ?>
                    <br><?= __('Activities: {0}', implode(', ', collection($gathering->gathering_activities)->extract('name')->toArray())) ?>
                <?php endif; ?>
            </small>
        </h3>
    </div>
    <div class="col text-end">
        <?= $this->Html->link(
            '<i class="bi bi-binoculars-fill"></i> ' . __('View Gathering'),
            ['plugin' => null, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id],
            ['class' => 'btn btn-outline-secondary me-2', 'escape' => false]
        ) ?>
        <?php if (!$waiverCollectionClosed): ?>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle"></i> ' . __('Upload Waiver'),
                ['action' => 'upload', '?' => ['gathering_id' => $gathering->id]],
                ['class' => 'btn btn-primary me-2', 'escape' => false]
            ) ?>
            <?php if ($canCloseWaivers): ?>
                <?= $this->Form->postLink(
                    '<i class="bi bi-lock-fill"></i> ' . __('Close Waivers'),
                    ['action' => 'close', $gathering->id],
                    [
                        'class' => 'btn btn-warning',
                        'escapeTitle' => false,
                        'confirm' => __('Close waiver collection for "{0}"? No further uploads will be allowed.', $gathering->name),
                    ]
                ) ?>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($canCloseWaivers): ?>
                <?= $this->Form->postLink(
                    '<i class="bi bi-unlock-fill"></i> ' . __('Reopen Waivers'),
                    ['action' => 'reopen', $gathering->id],
                    [
                        'class' => 'btn btn-outline-warning',
                        'escapeTitle' => false,
                        'confirm' => __('Reopen waiver collection for "{0}"? This will allow further uploads.', $gathering->name),
                    ]
                ) ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12">
        <hr>
    </div>
</div>

<?php if ($waiverCollectionClosed): ?>
    <div class="alert alert-dark" role="alert">
        <i class="bi bi-lock-fill"></i>
        <?= __('Waiver collection is closed for this gathering.') ?>
        <?php if ($waiverClosure): ?>
            <div class="small text-muted mt-1">
                <?= __('Closed {0} by {1}', $this->Timezone->format($waiverClosure->closed_at, $gathering, 'M d, Y g:i A'), h($waiverClosure->closed_by_member?->sca_name ?? __('Unknown'))) ?>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($waiverReadyToClose): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <?= __('This gathering has been marked as ready to close by event staff.') ?>
        <?php if ($waiverClosure): ?>
            <div class="small text-muted mt-1">
                <?= __('Marked ready {0} by {1}', $this->Timezone->format($waiverClosure->ready_to_close_at, $gathering, 'M d, Y g:i A'), h($waiverClosure->ready_to_close_by_member?->sca_name ?? __('Unknown'))) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($gatheringWaivers->isEmpty()): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle"></i>
        <?= __('No waivers have been uploaded for this gathering yet.') ?>
    </div>
<?php endif; ?>
    <!-- Waiver Count Summary -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> <?= __('Waiver Upload Summary') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (empty($requiredWaiverTypes)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <?= __('No waiver requirements configured for this gathering\'s activities.') ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($requiredWaiverTypes as $activityWaiver): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h6 class="card-title"><?= h($activityWaiver->waiver_type->name) ?></h6>
                                            <p class="display-4 mb-0">
                                                <?= isset($countsMap[$activityWaiver->waiver_type_id]) ? $countsMap[$activityWaiver->waiver_type_id] : 0 ?>
                                            </p>
                                            <p class="text-muted small"><?= __('waivers uploaded') ?></p>
                                            <?php if (!empty($exemptionCounts[$activityWaiver->waiver_type_id])): ?>
                                                <span class="badge bg-info text-dark"><?= __('Exempted') ?></span>
                                            <?php elseif (empty($countsMap[$activityWaiver->waiver_type_id])): ?>
                                                <span class="badge bg-warning text-dark"><?= __('Pending') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Waiver List Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('waiver_type_id', __('Waiver Type')) ?></th>
                    <th scope="col"><?= __('Status') ?></th>
                    <th scope="col"><?= __('Reason') ?></th>
                    <th scope="col"><?= __('Uploaded By') ?></th>
                    <th><?= $this->Paginator->sort('created', __('Uploaded')) ?></th>
                    <th class="actions text-end"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gatheringWaivers as $waiver): ?>
                    <tr>
                        <td>
                            <strong><?= h($waiver->waiver_type->name) ?></strong>
                        </td>
                        <td>
                            <?php if ($waiver->is_declined): ?>
                                <span class="badge bg-danger"><?= __('Declined') ?></span>
                            <?php elseif ($waiver->is_exemption): ?>
                                <span class="badge bg-info text-dark"><?= __('Exempted') ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= __('Uploaded') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($waiver->is_declined ? $waiver->decline_reason : ($waiver->exemption_reason ?? '')) ?></td>
                        <td><?= h($waiver->created_by_member?->sca_name ?? __('Unknown')) ?></td>
                        <td>
                            <?= $this->Timezone->format($waiver->created, null, 'M d, Y') ?>
                            <br>
                            <small class="text-muted"><?= $this->Timezone->format($waiver->created, null, 'g:i A') ?></small>
                        </td>
                        <td class="actions text-end">
                            <?php if ($waiver->can_be_declined): ?>
                                <small class="d-block text-muted mb-1"><?= __('Can be declined') ?></small>
                            <?php endif; ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-binoculars-fill"></i>',
                                ['action' => 'view', $waiver->id],
                                ['class' => 'btn btn-sm btn-secondary', 'escape' => false, 'title' => __('View'), 'aria-label' => __('View waiver')]
                            ) ?>
                            <?php if ($waiver->status === 'expired'): ?>
                                <?= $this->Form->postLink(
                                    'Delete',
                                    ['action' => 'delete', $waiver->id],
                                    [
                                        'confirm' => __('Are you sure you want to delete this expired waiver?'),
                                        'class' => 'btn btn-sm btn-outline-danger',
                                        'escape' => false,
                                        'title' => __('Delete')
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ((int)$this->getRequest()->getQuery('page', 1) === 1): ?>
                    <?php foreach ($pendingWaiverTypes as $waiverType): ?>
                    <tr>
                        <td><strong><?= h($waiverType->name) ?></strong></td>
                        <td><span class="badge bg-warning text-dark"><?= __('Pending') ?></span></td>
                        <td><?= __('No upload or exemption recorded.') ?></td>
                        <td>—</td><td>—</td><td></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$gatheringWaivers->isEmpty()): ?>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
        </p>
    </div>

    <?php endif; ?>
