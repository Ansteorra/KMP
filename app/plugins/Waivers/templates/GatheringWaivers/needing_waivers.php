<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering[] $gatheringsNeedingWaivers
 */
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
                <?= __('These gatherings have required waivers that haven\'t been uploaded yet.') ?>
            </p>
        </div>
    </div>

    <?php if (empty($gatheringsNeedingWaivers)): ?>
    <div class="alert alert-success" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <?= __('Great! All gatherings that you can manage have the required waivers uploaded.') ?>
    </div>

    <?php else: ?>
    <hr />
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?= __('Gathering') ?></th>
                    <th><?= __('Branch') ?></th>
                    <th><?= __('Dates') ?></th>
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
                        $endDate = $gathering->end_date ? \Cake\I18n\Date::parse($gathering->end_date) : $startDate;

                        $daysUntilStart = $today->diffInDays($startDate, false);
                        $hasStarted = $today >= $startDate;
                        $hasEnded = $today > $endDate;
                        $isOngoing = $hasStarted && !$hasEnded;
                        $isUrgent = $daysUntilStart <= 7 && $daysUntilStart > 0;
                        ?>
                <tr class="<?= $hasEnded ? 'table-danger' : '' ?> <?= $isOngoing ? 'table-warning' : '' ?>">
                    <td>
                        <?= h($gathering->name) ?>
                        <?php if ($isUrgent): ?>
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
                            <i class="bi bi-check-circle-fill"></i> Ended
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
                        <?php if ($hasEnded): ?>
                        <span class="badge bg-danger me-1"><?= $gathering->missing_waiver_count ?></span>
                        <?php elseif ($isOngoing): ?>
                        <span class="badge bg-warning me-1"><?= $gathering->missing_waiver_count ?></span>
                        <?php else: ?>
                        <span class="badge bg-info me-1"><?= $gathering->missing_waiver_count ?></span>
                        <?php endif; ?>

                    </td>
                    <td class="actions text-end text-nowrap">
                        <?= $this->Html->link(
                                    '<i class="bi bi-upload"></i> ' . __('Upload'),
                                    ['controller' => 'GatheringWaivers', 'action' => 'upload', 'plugin' => 'Waivers', '?' => ['gathering_id' => $gathering->id]],
                                    ['class' => 'btn btn-sm btn-primary', 'escape' => false, 'title' => __('Upload Waivers')]
                                ) ?>
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
                    <div class="col-md-4">
                        <span class="badge bg-info text-dark">Upcoming</span>
                        <?= __('Event starts within 7 days') ?>
                    </div>
                    <div class="col-md-4">
                        <span class="badge bg-warning">In Progress</span>
                        <?= __('Event has already started') ?>
                    </div>
                    <div class="col-md-4">
                        <span class="badge bg-danger">Count</span>
                        <?= __('Number of missing waivers') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>