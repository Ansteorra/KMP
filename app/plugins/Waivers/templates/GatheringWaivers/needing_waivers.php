<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering[] $gatherings All accessible gatherings (not closed by waiver secretary)
 * @var \App\Model\Entity\Gathering[] $gatheringsNeedingWaivers Gatherings that are missing required waivers
 */

$gatherings = $gatherings ?? [];
$gatheringsNeedingWaivers = $gatheringsNeedingWaivers ?? [];
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Gatherings Needing Waivers';
$this->KMP->endBlock();
?>

<div class="gatherings-needing-waivers content">
    <div class="row">
        <div class="col-md-12">
            <h2>
                <?= __('Gatherings Needing Waivers') ?>
            </h2>
            <p class="text-muted">
                <?= __('All gatherings you can upload waivers for. Gatherings with missing waivers are highlighted.') ?>
            </p>
            <?php if (!empty($gatheringsNeedingWaivers)): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= __n(
                    '{0} gathering needs waivers uploaded.',
                    '{0} gatherings need waivers uploaded.',
                    count($gatheringsNeedingWaivers),
                    count($gatheringsNeedingWaivers)
                ) ?>
            </div>
            <?php else: ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <?= __('All required waivers have been uploaded!') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($gatherings)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle-fill"></i>
        <?= __('No gatherings are currently available for waiver upload. Gatherings appear here when they start within 7 days and remain until the waiver secretary closes them.') ?>
    </div>

    <?php else: ?>
    <hr />
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?= __('Status') ?></th>
                    <th><?= __('Gathering') ?></th>
                    <th><?= __('Branch') ?></th>
                    <th><?= __('Dates') ?></th>
                    <th><?= __('Days Until Start') ?></th>
                    <th><?= __('Waivers') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gatherings as $gathering): ?>
                <?php
                        $today = \Cake\I18n\Date::now();
                        $startDate = \Cake\I18n\Date::parse($gathering->start_date);
                        $endDate = $gathering->end_date ? \Cake\I18n\Date::parse($gathering->end_date) : $startDate;

                        $daysUntilStart = $today->diffInDays($startDate, false);
                        $hasStarted = $today >= $startDate;
                        $hasEnded = $today > $endDate;
                        $isOngoing = $hasStarted && !$hasEnded;
                        $isUrgent = $daysUntilStart <= 7 && $daysUntilStart > 0;
                        
                        $needsWaivers = !$gathering->is_waiver_complete && $gathering->has_waiver_requirements;
                        $isComplete = $gathering->is_waiver_complete && $gathering->has_waiver_requirements;
                        $noRequirements = !$gathering->has_waiver_requirements;
                        $isReadyToClose = $gathering->is_ready_to_close ?? false;
                        
                        // Row class based on status
                        $rowClass = '';
                        if ($isReadyToClose) {
                            $rowClass = 'table-info';
                        } elseif ($needsWaivers && $hasEnded) {
                            $rowClass = 'table-danger';
                        } elseif ($needsWaivers && $isOngoing) {
                            $rowClass = 'table-warning';
                        } elseif ($needsWaivers) {
                            $rowClass = 'table-light';
                        } elseif ($isComplete) {
                            $rowClass = '';
                        }
                        ?>
                <tr class="<?= $rowClass ?>">
                    <td class="text-center">
                        <?php if ($isReadyToClose): ?>
                        <span class="badge bg-info" title="<?= __('Ready for secretary review') ?>">
                            <i class="bi bi-check2-square"></i>
                        </span>
                        <?php elseif ($isComplete): ?>
                        <span class="badge bg-success" title="<?= __('All waivers uploaded') ?>">
                            <i class="bi bi-check-circle-fill"></i>
                        </span>
                        <?php elseif ($noRequirements): ?>
                        <span class="badge bg-secondary" title="<?= __('No waiver requirements') ?>">
                            <i class="bi bi-dash-circle"></i>
                        </span>
                        <?php elseif ($needsWaivers): ?>
                        <span class="badge bg-warning text-dark" title="<?= __('Missing waivers') ?>">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= h($gathering->name) ?>
                        <?php if ($isReadyToClose): ?>
                        <span class="badge bg-info ms-2">
                            <i class="bi bi-check2-square"></i> Ready to Close
                        </span>
                        <?php endif; ?>
                        <?php if ($isUrgent && $needsWaivers): ?>
                        <span class="badge bg-info text-dark ms-2">
                            <i class="bi bi-exclamation-triangle-fill"></i> Upcoming
                        </span>
                        <?php endif; ?>
                        <?php if ($isOngoing): ?>
                        <span class="badge bg-warning ms-2">
                            <i class="bi bi-alarm-fill"></i> In Progress
                        </span>
                        <?php endif; ?>
                        <?php if ($hasEnded): ?>
                        <span class="badge bg-danger ms-2">
                            <i class="bi bi-calendar-x"></i> Ended
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($gathering->branch->name) ?></td>
                    <td class="text-nowrap">
                        <?php
                                $startDateFormatted = $this->Timezone->format($gathering->start_date, $gathering, 'M d, Y');
                                $endDateFormatted = $gathering->end_date ? $this->Timezone->format($gathering->end_date, $gathering, 'M d, Y') : $startDateFormatted;
                                ?>
                        <?php if ($startDateFormatted === $endDateFormatted): ?>
                        <?= h($startDateFormatted) ?>
                        <?php else: ?>
                        <?= h($startDateFormatted) ?><br>
                        <small class="text-muted">to <?= h($endDateFormatted) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($hasEnded): ?>
                        <span class="text-danger fw-bold">Ended</span>
                        <?php elseif ($isOngoing): ?>
                        <span class="text-warning fw-bold">Started</span>
                        <?php else: ?>
                        <?= $daysUntilStart ?> <?= __n('day', 'days', abs($daysUntilStart)) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($noRequirements): ?>
                        <span class="text-muted">—</span>
                        <?php elseif ($isComplete): ?>
                        <span class="badge bg-success">Complete</span>
                        <?php else: ?>
                        <span class="badge bg-<?= $hasEnded ? 'danger' : ($isOngoing ? 'warning' : 'info') ?> me-1" 
                              title="<?= h(implode(', ', $gathering->missing_waiver_names)) ?>">
                            <?= $gathering->missing_waiver_count ?> missing
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions text-end text-nowrap">
                        <?php if ($gathering->has_waiver_requirements): ?>
                        <?= $this->Html->link(
                                    '<i class="bi bi-upload"></i> ' . __('Upload'),
                                    ['controller' => 'GatheringWaivers', 'action' => 'upload', 'plugin' => 'Waivers', '?' => ['gathering_id' => $gathering->id]],
                                    ['class' => 'btn btn-sm btn-primary', 'escape' => false, 'title' => __('Upload Waivers')]
                                ) ?>
                        <?php endif; ?>
                        <?= $this->Html->link(
                                    '',
                                    ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id],
                                    ['class' => 'btn btn-sm btn-secondary bi bi-binoculars-fill', 'escape' => false, 'title' => __('View Gathering')]
                                ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="alert alert-light border" role="alert">
                <h6 class="alert-heading"><i class="bi bi-info-circle"></i> <?= __('Legend') ?></h6>
                <div class="row">
                    <div class="col-md-2">
                        <span class="badge bg-info"><i class="bi bi-check2-square"></i></span>
                        <?= __('Ready to close') ?>
                    </div>
                    <div class="col-md-2">
                        <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i></span>
                        <?= __('All waivers uploaded') ?>
                    </div>
                    <div class="col-md-2">
                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill"></i></span>
                        <?= __('Missing waivers') ?>
                    </div>
                    <div class="col-md-2">
                        <span class="badge bg-secondary"><i class="bi bi-dash-circle"></i></span>
                        <?= __('No waiver requirements') ?>
                    </div>
                    <div class="col-md-2">
                        <span class="badge bg-danger">Ended</span>
                        <?= __('Event has ended') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>