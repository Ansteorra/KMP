<?php

/**
 * Gathering Waivers Cell Display Template
 * 
 * Displays waiver-centric information for a gathering, showing each waiver
 * requirement as a separate row with waiver type and upload status.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var int $gatheringId
 * @var array $waiverRows Array of waiver requirement rows (waiver type + upload count)
 * @var bool $isEmpty Whether gathering has any waiver requirements
 * @var bool $hasWaivers Whether any waivers have been uploaded
 * @var array $overallStats Overall completion statistics
 * @var int $totalWaiverCount Total number of waivers uploaded
 * @var int $declinedWaiverCount Number of declined waivers
 * @var bool $waiverCollectionClosed Whether waiver collection is closed for this gathering
 * @var bool $isReadyToClose Whether gathering is marked ready to close
 * @var \Waivers\Model\Entity\GatheringWaiverClosure|null $waiverClosure Closure details (if exists)
 * @var bool $canCloseWaivers Whether current user can close waivers
 * @var bool $canEditGathering Whether current user can edit the gathering
 */

$user = $this->getRequest()->getAttribute('identity');
$isCancelled = $gathering->cancelled_at !== null;
?>

<?php if ($isCancelled): ?>
<div class="gathering-waivers p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><?= __('Waivers') ?></h5>
    </div>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong><?= __('Gathering Cancelled') ?></strong>
        <p class="mb-0 mt-2">
            <?= __('This gathering has been cancelled. Waivers are not required for cancelled gatherings.') ?>
        </p>
        <?php if (!empty($gathering->cancellation_reason)): ?>
        <hr>
        <small><strong><?= __('Reason:') ?></strong> <?= h($gathering->cancellation_reason) ?></small>
        <?php endif; ?>
    </div>
    <?php if ($totalWaiverCount > 0): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle"></i>
        <?= __('There are {0} waiver(s) on file from before this gathering was cancelled.', $totalWaiverCount) ?>
        <?= $this->Html->link(
            __('View Waivers'),
            [
                'plugin' => 'Waivers',
                'controller' => 'GatheringWaivers',
                'action' => 'index',
                '?' => ['gathering_id' => $gathering->id]
            ],
            ['class' => 'btn btn-sm btn-outline-secondary ms-2']
        ) ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="gathering-waivers p-3" data-controller="waivers-waiver-attestation">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>
            <?= __('Waivers') ?>
            <?php if (!$isEmpty): ?>
            <span class="badge bg-secondary"><?= count($waiverRows) ?></span>
            <?php endif; ?>
        </h5>
        <div>
            <?php if ($canCloseWaivers): ?>
            <?php if ($waiverCollectionClosed): ?>
            <?= $this->Form->postLink(
                    '<i class="bi bi-unlock"></i> ' . __('Reopen Waivers'),
                    [
                        'plugin' => 'Waivers',
                        'controller' => 'GatheringWaivers',
                        'action' => 'reopen',
                        $gathering->id
                    ],
                    [
                        'class' => 'btn btn-sm btn-outline-success me-2',
                        'escape' => false,
                        'confirm' => __('Reopen waiver collection for this gathering?'),
                    ]
                ) ?>
            <?php else: ?>
            <?= $this->Form->postLink(
                    '<i class="bi bi-lock-fill"></i> ' . __('Close Waivers'),
                    [
                        'plugin' => 'Waivers',
                        'controller' => 'GatheringWaivers',
                        'action' => 'close',
                        $gathering->id
                    ],
                    [
                        'class' => 'btn btn-sm btn-outline-danger me-2',
                        'escape' => false,
                        'confirm' => __('Closing waivers will prevent new uploads or attestations. Continue?'),
                    ]
                ) ?>
            <?php endif; ?>
            <?php elseif ($canEditGathering && !$waiverCollectionClosed && !$isEmpty): ?>
            <?php // Show Ready to Close buttons for editors/stewards ?>
            <?php if ($isReadyToClose): ?>
            <?= $this->Form->postLink(
                    '<i class="bi bi-x-circle"></i> ' . __('Unmark Ready'),
                    [
                        'plugin' => 'Waivers',
                        'controller' => 'GatheringWaivers',
                        'action' => 'unmarkReadyToClose',
                        $gathering->id
                    ],
                    [
                        'class' => 'btn btn-sm btn-outline-secondary me-2',
                        'escape' => false,
                        'confirm' => __('Remove the ready-to-close status from this gathering?'),
                    ]
                ) ?>
            <?php else: ?>
            <?= $this->Form->postLink(
                    '<i class="bi bi-check2-square"></i> ' . __('Mark Ready to Close'),
                    [
                        'plugin' => 'Waivers',
                        'controller' => 'GatheringWaivers',
                        'action' => 'markReadyToClose',
                        $gathering->id
                    ],
                    [
                        'class' => 'btn btn-sm btn-outline-primary me-2',
                        'escape' => false,
                        'confirm' => __('Mark this gathering as ready for the waiver secretary to review and close?'),
                    ]
                ) ?>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($totalWaiverCount > 0): ?>
            <?= $this->Html->link(
                    '<i class="bi bi-list-ul"></i> ' . __('View All Waivers') . ' <span class="badge bg-light text-dark ms-1">' . $totalWaiverCount . '</span>',
                    [
                        'plugin' => 'Waivers',
                        'controller' => 'GatheringWaivers',
                        'action' => 'index',
                        '?' => ['gathering_id' => $gathering->id]
                    ],
                    [
                        'class' => 'btn btn-sm btn-outline-secondary me-2',
                        'escape' => false
                    ]
                ) ?>
            <?php endif; ?>
            <?php if ($user && $user->checkCan('edit', $gathering) && !$isEmpty && !$waiverCollectionClosed): ?>
            <?= $this->Html->link(
                    '<i class="bi bi-cloud-upload"></i> ' . __('Submit Waivers'),
                    [
                        'plugin' => 'Waivers',
                        'controller' => 'GatheringWaivers',
                        'action' => 'upload',
                        '?' => ['gathering_id' => $gathering->id]
                    ],
                    [
                        'class' => 'btn btn-sm btn-primary',
                        'escape' => false
                    ]
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($waiverCollectionClosed): ?>
    <div class="alert alert-dark">
        <i class="bi bi-lock-fill"></i>
        <?= __('Waiver collection is closed for this gathering.') ?>
        <?php if ($waiverClosure): ?>
        <div class="small text-muted mt-1">
            <?= __('Closed {0} by {1}', $this->Timezone->format($waiverClosure->closed_at, $gathering, 'M d, Y g:i A'), h($waiverClosure->closed_by_member?->sca_name ?? __('Unknown'))) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif ($isReadyToClose): ?>
    <div class="alert alert-info">
        <i class="bi bi-check2-square"></i>
        <?= __('This gathering is marked as ready for waiver secretary review.') ?>
        <?php if ($waiverClosure): ?>
        <div class="small text-muted mt-1">
            <?= __('Marked ready {0} by {1}', $this->Timezone->format($waiverClosure->ready_to_close_at, $gathering, 'M d, Y g:i A'), h($waiverClosure->ready_to_close_by_member?->sca_name ?? __('Unknown'))) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($isEmpty): ?>
    <!-- No Requirements Configured -->
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle"></i>
        <?= __('No waiver requirements have been configured for this gathering yet.') ?>
    </div>
    <?php else: ?>
    <!-- Overall Status Summary -->
    <div
        class="alert alert-<?= ($overallStats['complete'] + $overallStats['exempted']) === $overallStats['total'] ? 'success' : ($overallStats['pending'] > 0 ? 'warning' : 'info') ?> mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i
                    class="bi bi-<?= ($overallStats['complete'] + $overallStats['exempted']) === $overallStats['total'] ? 'check-circle' : 'info-circle' ?>"></i>
                <?= __(
                        '{0} waiver type requirements for this gathering.',
                        count($waiverRows)
                    ) ?>
            </div>
            <div>
                <span class="badge bg-success me-1">
                    <?= $overallStats['complete'] ?> <?= __('Uploaded') ?>
                </span>
                <?php if ($overallStats['exempted'] > 0): ?>
                <span class="badge bg-info me-1">
                    <?= $overallStats['exempted'] ?> <?= __('Exempted') ?>
                </span>
                <?php endif; ?>
                <span class="badge bg-warning me-1">
                    <?= $overallStats['pending'] ?> <?= __('Pending') ?>
                </span>
                <?php if ($declinedWaiverCount > 0): ?>
                <span class="badge bg-danger" title="<?= __('Declined waivers are not counted as valid') ?>">
                    <?= $declinedWaiverCount ?> <?= __('Declined') ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($gathering->waivers_collected): ?>
        <hr>
        <small>
            <i class="bi bi-lock-fill"></i>
            <?= __('Waivers have been marked as collected for this gathering.') ?>
        </small>
        <?php endif; ?>
    </div>
    <!-- Waiver Requirements Table - One Row Per Waiver Type -->
    <div class="table-responsive mb-3">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><?= __('Waiver Type') ?></th>
                    <th class="text-center"><?= __('Status') ?></th>
                    <th class="text-center"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($waiverRows as $row): ?>
                <?php
                        $waiverType = $row['waiver_type'];
                        $uploadedCount = $row['uploaded_count'];
                        $isComplete = $row['is_complete'];
                        $exemption = $row['exemption'];
                        $hasExemptionReasons = !empty($waiverType->exemption_reasons_parsed);
                        ?>
                <tr>
                    <td style="width: 60%;">
                        <?php if ($user && $user->checkCan('view', 'Waivers.WaiverTypes')): ?>
                        <?= $this->Html->link(
                                        h($waiverType->name),
                                        ['controller' => 'WaiverTypes', 'action' => 'view', $waiverType->id, 'plugin' => 'Waivers'],
                                        ['class' => 'text-decoration-none']
                                    ) ?>
                        <?php else: ?>
                        <?= h($waiverType->name) ?>
                        <?php endif; ?>
                        <?php if (!empty($waiverType->description)): ?>
                        <br>
                        <small class="text-muted"><?= h($waiverType->description) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center align-middle">
                        <?php if ($exemption): ?>
                        <span class="badge bg-info" data-bs-toggle="tooltip"
                            title="<?= h($exemption->exemption_reason) ?>">
                            <i class="bi bi-shield-check"></i> <?= __('Exempted') ?>
                        </span>
                        <br>
                        <small class="text-muted">
                            <?= __('by {0}', h($exemption->created_by_member?->sca_name ?? 'Unknown')) ?>
                        </small>
                        <?php elseif ($isComplete): ?>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle-fill"></i> <?= __('Uploaded') ?>
                        </span>
                        <br>
                        <small class="text-muted"><?= __('({0} submitted)', $uploadedCount) ?></small>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-exclamation-circle-fill"></i> <?= __('Pending') ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center align-middle">
                        <?php if ($user && $user->checkCan('edit', $gathering) && !$exemption && !$waiverCollectionClosed):
                                    if ($uploadedCount > 0) {
                                        $label = __('Submit More');
                                    } else {
                                        $label = __('Submit');
                                    } ?>
                        <?= $this->Html->link(
                                        '<i class="bi bi-cloud-upload"></i> ' . $label,
                                        [
                                            'plugin' => 'Waivers',
                                            'controller' => 'GatheringWaivers',
                                            'action' => 'upload',
                                            '?' => [
                                                'gathering_id' => $gatheringId,
                                                'waiver_type_id' => $waiverType->id
                                            ]
                                        ],
                                        [
                                            'class' => 'btn btn-sm btn-primary me-1',
                                            'escape' => false
                                        ]
                                    ) ?>
                        <!--<?php if ($hasExemptionReasons): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-waiver-type-id="<?= $waiverType->id ?>"
                            data-gathering-id="<?= $gatheringId ?>"
                            data-reasons="<?= h(json_encode($waiverType->exemption_reasons_parsed)) ?>"
                            data-action="click->waivers-waiver-attestation#showModal">
                            <i class="bi bi-shield-check"></i> <?= __('Attest Not Needed') ?>
                        </button>
                        <?php endif; ?>-->
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($user && $user->checkCan('edit', $gathering) && !$hasWaivers): ?>
    <!-- Important Notice -->
    <div class="alert alert-secondary">
        <i class="bi bi-exclamation-triangle"></i>
        <strong><?= __('Important:') ?></strong>
        <?= __('Once waivers are uploaded, gathering waiver requirements should not be changed to prevent data inconsistencies.') ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Include Attestation Modal (must be inside controller scope) -->
    <?= $this->element('Waivers.waiver_attestation_modal') ?>
</div>
<?php endif; ?>
