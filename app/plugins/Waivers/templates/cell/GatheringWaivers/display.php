<?php

/**
 * Gathering Waivers Cell Display Template
 * 
 * Displays waiver-centric information for a gathering, showing each waiver
 * requirement as a separate row with activity, waiver type, and upload status.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var int $gatheringId
 * @var array $waiverRows Array of waiver requirement rows (activity + waiver type + upload count)
 * @var bool $isEmpty Whether gathering has any waiver requirements
 * @var bool $hasWaivers Whether any waivers have been uploaded
 * @var array $overallStats Overall completion statistics
 * @var int $totalWaiverCount Total number of waivers uploaded
 * @var int $declinedWaiverCount Number of declined waivers
 */

$user = $this->getRequest()->getAttribute('identity');
?>

<div class="gathering-waivers p-3" data-controller="waivers-waiver-attestation">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>
            <?= __('Waivers') ?>
            <?php if (!$isEmpty): ?>
            <span class="badge bg-secondary"><?= count($waiverRows) ?></span>
            <?php endif; ?>
        </h5>
        <div>
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
            <?php if ($user && $user->checkCan('edit', $gathering) && !$isEmpty): ?>
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

    <?php if ($isEmpty): ?>
    <!-- No Requirements Configured -->
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle"></i>
        <?= __('No waiver requirements have been configured for this gathering\'s activities yet.') ?>
        <br>
        <small class="text-muted">
            <?= __('Waiver requirements are configured at the activity level. Visit each activity to add waiver requirements.') ?>
        </small>
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
                        '{0} waiver requirements for this gathering.',
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
            <?= __('Waivers have been marked as collected. Activities are now locked.') ?>
        </small>
        <?php endif; ?>
    </div>
    <!-- Waiver Requirements Table - One Row Per Waiver -->
    <div class="table-responsive mb-3">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><?= __('Activity') ?></th>
                    <th><?= __('Waiver Type') ?></th>
                    <th class="text-center"><?= __('Status') ?></th>
                    <th class="text-center"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($waiverRows as $row): ?>
                <?php
                        $activity = $row['activity'];
                        $waiverType = $row['waiver_type'];
                        $uploadedCount = $row['uploaded_count'];
                        $isComplete = $row['is_complete'];
                        $exemption = $row['exemption'];
                        $hasExemptionReasons = !empty($waiverType->exemption_reasons_parsed);
                        ?>
                <tr>
                    <td style="width: 30%;">
                        <?= $this->Html->link(
                                    h($activity->name),
                                    ['controller' => 'GatheringActivities', 'action' => 'view', $activity->id, 'plugin' => null],
                                    ['class' => 'fw-bold text-decoration-none']
                                ) ?>
                        <?php if (!empty($activity->description)): ?>
                        <br>
                        <small class="text-muted"><?= h($activity->description) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="width: 40%;">
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
                        <?php if ($user && $user->checkCan('edit', $gathering) && !$exemption && !$isComplete): ?>
                        <?= $this->Html->link(
                                        '<i class="bi bi-cloud-upload"></i> ' . __('Submit'),
                                        [
                                            'plugin' => 'Waivers',
                                            'controller' => 'GatheringWaivers',
                                            'action' => 'upload',
                                            '?' => [
                                                'gathering_id' => $gatheringId,
                                                'activity_id' => $activity->id,
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
                            data-activity-id="<?= $activity->id ?>" data-waiver-type-id="<?= $waiverType->id ?>"
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
        <?= __('Once waivers are uploaded, activities cannot be modified to prevent data inconsistencies.') ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Include Attestation Modal (must be inside controller scope) -->
    <?= $this->element('Waivers.waiver_attestation_modal') ?>
</div>