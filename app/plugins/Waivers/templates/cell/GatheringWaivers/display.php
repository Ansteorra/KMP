<?php

/**
 * Gathering Waivers Cell Display Template
 * 
 * Displays activity-centric waiver information for a gathering, showing which
 * waivers each activity requires and their upload completion status.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var int $gatheringId
 * @var array $activitiesWithWaivers Activity-centric data with completion status
 * @var bool $isEmpty Whether gathering has any waiver requirements
 * @var bool $hasWaivers Whether any waivers have been uploaded
 * @var array $overallStats Overall completion statistics
 */

$user = $this->getRequest()->getAttribute('identity');
?>

<div class="gathering-waivers p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>
            <?= __('Waivers') ?>
            <?php if (!$isEmpty): ?>
                <span class="badge bg-secondary"><?= count($activitiesWithWaivers) ?></span>
            <?php endif; ?>
        </h5>
        <?php if ($user && $user->checkCan('edit', $gathering) && !$isEmpty): ?>
            <?= $this->Html->link(
                '<i class="bi bi-cloud-upload"></i> ' . __('Upload Waivers'),
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
        <div class="alert alert-<?= $overallStats['complete'] === $overallStats['total'] ? 'success' : ($hasWaivers ? 'warning' : 'info') ?> mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-<?= $overallStats['complete'] === $overallStats['total'] ? 'check-circle' : 'info-circle' ?>"></i>
                    <?= __(
                        '{0} activities require waivers for this gathering.',
                        count($activitiesWithWaivers)
                    ) ?>
                </div>
                <div>
                    <span class="badge bg-success me-1">
                        <?= $overallStats['complete'] ?> <?= __('Complete') ?>
                    </span>
                    <span class="badge bg-warning">
                        <?= $overallStats['pending'] ?> <?= __('Pending') ?>
                    </span>
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
        <!-- Activity-Centric Waiver Requirements -->
        <div class="table-responsive mb-3">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?= __('Activity') ?></th>
                        <th><?= __('Required Waivers') ?></th>
                        <th class="text-center"><?= __('Status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activitiesWithWaivers as $activityData): ?>
                        <?php
                        $activity = $activityData['activity'];
                        $requiredWaivers = $activityData['required_waivers'];
                        $status = $activityData['completion_status'];
                        $isComplete = $status['complete'] === $status['total'];
                        $percentage = $status['total'] > 0 ? round(($status['complete'] / $status['total']) * 100) : 0;
                        ?>
                        <tr>
                            <td style="width: 25%;">
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
                            <td style="width: 55%;">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <?php foreach ($requiredWaivers as $waiverData): ?>
                                        <?php
                                        $waiverType = $waiverData['waiver_type'];
                                        $uploadedCount = $waiverData['uploaded_count'];
                                        $hasUploads = $uploadedCount > 0;
                                        $badgeClass = $hasUploads ? 'bg-success' : 'bg-warning text-dark';
                                        $icon = $hasUploads ? 'check-circle-fill' : 'exclamation-circle-fill';
                                        ?>
                                        <div>
                                            <?php if ($user && $user->checkCan('view', 'Waivers.WaiverTypes')): ?>
                                                <?= $this->Html->link(
                                                    '<i class="bi bi-' . $icon . '"></i> ' . h($waiverType->name),
                                                    ['controller' => 'WaiverTypes', 'action' => 'view', $waiverType->id, 'plugin' => 'Waivers'],
                                                    [
                                                        'class' => 'badge ' . $badgeClass . ' text-decoration-none',
                                                        'escape' => false,
                                                        'title' => $hasUploads ? __('Waivers uploaded for this activity') : __('No waivers uploaded for this activity')
                                                    ]
                                                ) ?>
                                            <?php else: ?>
                                                <span class="badge <?= $badgeClass ?>" title="<?= $hasUploads ? __('Waivers uploaded for this activity') : __('No waivers uploaded for this activity') ?>">
                                                    <i class="bi bi-<?= $icon ?>"></i> <?= h($waiverType->name) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($hasUploads): ?>
                                                <small class="text-muted ms-1">(<?= $uploadedCount ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="text-center align-middle" style="width: 20%;">
                                <div>
                                    <?php if ($isComplete): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle-fill"></i> <?= __('Complete') ?>
                                        </span>
                                    <?php else: ?>
                                        <div class="progress" style="height: 25px; min-width: 100px;">
                                            <div class="progress-bar <?= $status['complete'] > 0 ? 'bg-warning' : 'bg-secondary' ?>"
                                                role="progressbar"
                                                style="width: <?= $percentage ?>%;"
                                                aria-valuenow="<?= $percentage ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100">
                                                <?= $status['complete'] ?> / <?= $status['total'] ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Action Cards -->
        <div class="row">
            <?php if ($hasWaivers): ?>
                <!-- Waiver Management Card -->
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-list-ul"></i> <?= __('Manage Uploaded Waivers') ?>
                            </h6>
                            <p class="card-text">
                                <?= __('View, download, and manage all uploaded waivers for this gathering.') ?>
                            </p>
                            <?= $this->Html->link(
                                '<i class="bi bi-list-ul"></i> ' . __('View All Waivers'),
                                [
                                    'plugin' => 'Waivers',
                                    'controller' => 'GatheringWaivers',
                                    'action' => 'index',
                                    '?' => ['gathering_id' => $gathering->id]
                                ],
                                [
                                    'class' => 'btn btn-secondary',
                                    'escape' => false
                                ]
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Upload Instructions -->
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-cloud-upload"></i> <?= __('Upload Instructions') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <p><?= __('To collect waivers:') ?></p>
                            <ol class="small mb-0">
                                <li><?= __('Click "Upload Waivers" button above') ?></li>
                                <li><?= __('Select waiver type and upload files') ?></li>
                                <li><?= __('Images are converted to PDF automatically') ?></li>
                                <li><?= __('Repeat for each waiver type') ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Help Card -->
            <div class="col-md-6 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-lightbulb"></i> <?= __('Managing Waiver Requirements') ?>
                        </h6>
                        <p class="card-text small mb-2">
                            <?= __('To configure which waivers each activity requires:') ?>
                        </p>
                        <ol class="small mb-0">
                            <li><?= __('Click on an activity name above') ?></li>
                            <li><?= __('Go to the "Required Waivers" tab') ?></li>
                            <li><?= __('Add or remove waiver requirements') ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user && $user->checkCan('edit', $gathering) && !$hasWaivers): ?>
            <!-- Important Notice -->
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong><?= __('Important:') ?></strong>
                <?= __('Once waivers are uploaded and marked as collected, activities cannot be modified to prevent data inconsistencies.') ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>