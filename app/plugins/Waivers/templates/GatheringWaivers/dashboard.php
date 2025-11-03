<?php

/**
 * @var \App\View\AppView $this
 * @var array $statistics
 * @var array $gatheringsMissingWaivers
 * @var array $branchesWithIssues
 * @var array $recentActivity
 * @var array $waiverTypesSummary
 * @var array|null $searchResults
 * @var string|null $searchTerm
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Waiver Secretary Dashboard';
$this->KMP->endBlock();
?>

<div class="waiver-dashboard content">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>
                <i class="bi bi-file-earmark-text"></i> <?= __('Waiver Secretary Dashboard') ?>
            </h2>
            <p class="text-muted">
                <?= __('Comprehensive overview of waiver compliance and management across all gatherings.') ?>
            </p>
        </div>
    </div>

    <!-- Key Statistics -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h4><i class="bi bi-bar-chart-fill"></i> <?= __('Key Statistics') ?></h4>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6 mb-3">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h2 class="display-4"><?= number_format($statistics['recentWaivers']) ?></h2>
                    <p class="card-text"><?= __('Uploads in the last 30 Days') ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6 mb-3">
            <div class="card text-white bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 class="display-4"><?= number_format($statistics['gatheringsNeedingCount']) ?></h2>
                    <p class="card-text"><?= __('Upcoming Events') ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6 mb-3">
            <div class="card text-white bg-danger">
                <div class="card-body text-center">
                    <h2 class="display-4"><?= number_format($statistics['gatheringsMissingCount']) ?></h2>
                    <p class="card-text"><?= __('Past Due') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gatherings with Missing Waivers (Past Due - >ComplianceDays after event ended) -->
    <?php if (!empty($gatheringsMissingWaivers)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-x-circle-fill"></i>
                            <?= __('Past Due - Missing Waivers (Events Ended >{0} Days Ago)', $complianceDays) ?>
                            <span class="badge bg-light text-dark"><?= count($gatheringsMissingWaivers) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-danger">
                                <thead>
                                    <tr>
                                        <th><?= __('Gathering') ?></th>
                                        <th><?= __('Branch') ?></th>
                                        <th><?= __('Event Dates') ?></th>
                                        <th><?= __('Days Since End') ?></th>
                                        <th><?= __('Missing Waivers') ?></th>
                                        <th class="actions"><?= __('Actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gatheringsMissingWaivers as $gathering): ?>
                                        <?php
                                        $today = \Cake\I18n\Date::now();
                                        $endDate = $gathering->end_date ? \Cake\I18n\Date::parse($gathering->end_date) : \Cake\I18n\Date::parse($gathering->start_date);
                                        $daysSinceEnd = $today->diffInDays($endDate, false);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= h($gathering->name) ?></strong>
                                                <span class="badge bg-danger ms-2">
                                                    <i class="bi bi-x-circle"></i> Past Due
                                                </span>
                                            </td>
                                            <td><?= h($gathering->branch->name) ?></td>
                                            <td>
                                                <?php
                                                $startFormatted = $gathering->start_date->format('M d, Y');
                                                $endFormatted = $gathering->end_date ? $gathering->end_date->format('M d, Y') : $startFormatted;
                                                ?>
                                                <?= h($startFormatted) ?>
                                                <?php if ($startFormatted !== $endFormatted): ?>
                                                    <br><small class="text-muted">to <?= h($endFormatted) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="text-danger fw-bold"><?= abs($daysSinceEnd) ?>
                                                    <?= __n('day', 'days', abs($daysSinceEnd)) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?= $gathering->missing_waiver_count ?></span>
                                                <ul class="mb-0 mt-1">
                                                    <?php foreach ($gathering->missing_waiver_names as $waiverName): ?>
                                                        <li><?= h($waiverName) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td class="actions">
                                                <?= $this->Html->link(
                                                    __('Upload'),
                                                    ['action' => 'upload', '?' => ['gathering_id' => $gathering->id]],
                                                    ['class' => 'btn btn-sm btn-danger']
                                                ) ?>
                                                <?= $this->Html->link(
                                                    __('View'),
                                                    ['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id, 'plugin' => null],
                                                    ['class' => 'btn btn-sm btn-secondary']
                                                ) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Upcoming/Ongoing Events Needing Waivers -->
    <?php if (!empty($gatheringsNeedingWaivers)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <?= __('Waivers Due (Next 30 Days)') ?>
                            <span class="badge bg-dark"><?= count($gatheringsNeedingWaivers) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= __('Gathering') ?></th>
                                        <th><?= __('Branch') ?></th>
                                        <th><?= __('Event Dates') ?></th>
                                        <th><?= __('Days Until Start') ?></th>
                                        <th><?= __('Missing Waivers') ?></th>
                                        <th class="actions"><?= __('Actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gatheringsNeedingWaivers as $gathering): ?>
                                        <?php
                                        $today = \Cake\I18n\Date::now();
                                        $startDate = \Cake\I18n\Date::parse($gathering->start_date);
                                        $daysUntilStart = $today->diffInDays($startDate, false);
                                        $hasStarted = $today >= $startDate;
                                        $hasEnded = $today > \Cake\I18n\Date::parse($gathering->end_date);
                                        ?>
                                        <tr
                                            class="<?= ($hasStarted && !$hasEnded) ? 'table-info' : '' ?><?= ($hasEnded) ? 'table-warning' : '' ?>">
                                            <td>
                                                <?= h($gathering->name) ?>
                                                <?php if ($hasStarted && !$hasEnded): ?>
                                                    <span class="badge bg-info ms-2">
                                                        <i class="bi bi-play-circle"></i> In Progress
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($hasEnded): ?>
                                                    <span class="badge bg-warning ms-2">
                                                        <i class="bi bi-stop-circle"></i> Ended
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= h($gathering->branch->name) ?></td>
                                            <td>
                                                <?php
                                                $startFormatted = $gathering->start_date->format('M d, Y');
                                                $endFormatted = $gathering->end_date ? $gathering->end_date->format('M d, Y') : $startFormatted;
                                                ?>
                                                <?= h($startFormatted) ?>
                                                <?php if ($startFormatted !== $endFormatted): ?>
                                                    <br><small class="text-muted">to <?= h($endFormatted) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($hasStarted && !$hasEnded): ?>
                                                    <span class="text-info fw-bold"><?= __('Started') ?></span>
                                                <?php endif; ?>
                                                <?php if ($hasEnded): ?>
                                                    <span class="text-warning fw-bold"><?= __('Ended') ?></span>
                                                <?php endif; ?>
                                                <?php if (!$hasStarted && !$hasEnded): ?>
                                                    <?= $daysUntilStart ?> <?= __n('day', 'days', abs($daysUntilStart)) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $gathering->missing_waiver_count ?></span>
                                                <ul class="mb-0 mt-1">
                                                    <?php foreach ($gathering->missing_waiver_names as $waiverName): ?>
                                                        <li><?= h($waiverName) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td class="actions">
                                                <?= $this->Html->link(
                                                    __('Upload'),
                                                    ['action' => 'upload', '?' => ['gathering_id' => $gathering->id]],
                                                    ['class' => 'btn btn-sm btn-primary']
                                                ) ?>
                                                <?= $this->Html->link(
                                                    __('View'),
                                                    ['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id, 'plugin' => null],
                                                    ['class' => 'btn btn-sm btn-info']
                                                ) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Branches with Compliance Issues -->
    <?php if (!empty($branchesWithIssues)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">
                            <i class="bi bi-building"></i> <?= __('Branches with Compliance Issues') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= __('Branch') ?></th>
                                        <th class="text-center"><?= __('Gatherings Missing Waivers') ?></th>
                                        <th class="text-center"><?= __('Total Missing Waivers') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branchesWithIssues as $branchIssue): ?>
                                        <tr>
                                            <td>
                                                <?= $this->Html->link(
                                                    h($branchIssue['branch']->name),
                                                    ['controller' => 'Branches', 'action' => 'view', $branchIssue['branch']->id, 'plugin' => null]
                                                ) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?= $branchIssue['gathering_count'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge bg-warning text-dark"><?= $branchIssue['total_missing_waivers'] ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Recent Waiver Activity and Waiver Types Summary -->
    <div class="row mb-4">
        <!-- Recent Activity -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history"></i> <?= __('Recent Waiver Activity (Last 30 Days)') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivity)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <?= __('No recent waiver activity.') ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th><?= __('Date') ?></th>
                                        <th><?= __('Gathering') ?></th>
                                        <th><?= __('Branch') ?></th>
                                        <th><?= __('Type') ?></th>
                                        <th><?= __('Status') ?></th>
                                        <th><?= __('Uploaded By') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= h($activity->created->format('M d, Y')) ?></td>
                                            <td><?= h($activity->gathering->name) ?></td>
                                            <td><?= h($activity->gathering->branch->name) ?></td>
                                            <td><?= h($activity->waiver_type->name) ?></td>
                                            <td>
                                                <?php if ($activity->is_declined): ?>
                                                    <span class="badge bg-danger"><?= __('Declined') ?></span>
                                                <?php elseif ($activity->status === 'active'): ?>
                                                    <span class="badge bg-success"><?= __('Active') ?></span>
                                                <?php elseif ($activity->status === 'expired'): ?>
                                                    <span class="badge bg-danger"><?= __('Expired') ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= h($activity->status) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= h($activity->created_by_member->sca_name ?? 'Unknown') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Waiver Types Summary -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-file-earmark-ruled"></i> <?= __('Waiver Types Summary') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($waiverTypesSummary)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <?= __('No waiver types configured.') ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($waiverTypesSummary as $summary): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= h($summary['waiver_type']->name) ?>
                                    <span class="badge bg-primary rounded-pill"><?= number_format($summary['count']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill"></i> <?= __('Quick Actions') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?= $this->Html->link(
                            '<i class="bi bi-file-earmark-check"></i> ' . __('View Gatherings Needing Waivers'),
                            ['action' => 'needingWaivers'],
                            ['class' => 'btn btn-primary', 'escape' => false]
                        ) ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-file-earmark-ruled"></i> ' . __('Manage Waiver Types'),
                            ['controller' => 'WaiverTypes', 'action' => 'index'],
                            ['class' => 'btn btn-secondary', 'escape' => false]
                        ) ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-upload"></i> ' . __('Upload Waiver'),
                            ['action' => 'upload'],
                            ['class' => 'btn btn-success', 'escape' => false]
                        ) ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-list-ul"></i> ' . __('All Waivers'),
                            ['action' => 'index'],
                            ['class' => 'btn btn-info', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>