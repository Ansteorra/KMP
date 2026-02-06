<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $member
 * @var \App\Model\Entity\GatheringAttendance[] $gatheringAttendances
 * @var array $availableGatherings
 */

use Cake\I18n\DateTime;
use Cake\I18n\Date;
use App\KMP\TimezoneHelper;

// Get today's date in the gathering's timezone for accurate comparison
// We need to compare dates in the gathering's local timezone, not UTC
$upcomingAttendances = [];
$pastAttendances = [];

foreach ($member->gathering_attendances as $attendance) {
    // Skip if gathering has no end date
    if ($attendance->gathering->end_date === null) {
        continue;
    }

    // Get the gathering's timezone
    $gatheringTimezone = TimezoneHelper::getGatheringTimezone($attendance->gathering, $member);

    // Get current date/time in the gathering's timezone
    $nowInGatheringTz = TimezoneHelper::toUserTimezone(DateTime::now(), null, $gatheringTimezone);

    // Convert gathering end date to the gathering's timezone for comparison
    $endDateInGatheringTz = TimezoneHelper::toUserTimezone($attendance->gathering->end_date, null, $gatheringTimezone);

    // A gathering is "upcoming" if its end date (in gathering's timezone) hasn't passed yet
    // Compare just the dates to determine if event is today or in the future
    if ($endDateInGatheringTz && $endDateInGatheringTz->format('Y-m-d') >= $nowInGatheringTz->format('Y-m-d')) {
        $upcomingAttendances[] = $attendance;
    } else {
        $pastAttendances[] = $attendance;
    }
}

$canManageOwn = $user && method_exists($user, 'canManageMember') ? $user->canManageMember($member) : false;
$canManageOthers = $user ? $user->checkCan('add', 'GatheringAttendances') : false;
$canManage = $canManageOwn || $canManageOthers;
?>

<div class="gathering-attendances">
    <?php if ($canManage): ?>
    <div class="mb-3">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
            data-bs-target="#addGatheringAttendanceModal">
            <i class="bi bi-plus-circle"></i> RSVP for Gathering
        </button>
    </div>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming"
                type="button" role="tab" aria-controls="upcoming" aria-selected="true">
                Upcoming (<?= count($upcomingAttendances) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab"
                aria-controls="past" aria-selected="false">
                Past (<?= count($pastAttendances) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="attendanceTabsContent">
        <!-- Upcoming Gatherings Tab -->
        <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
            <?php if (empty($upcomingAttendances)): ?>
            <div class="alert alert-info mt-3">
                No upcoming gatherings registered.
            </div>
            <?php else: ?>
            <div class="table-responsive mt-3">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Gathering</th>
                            <th>Branch</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Note</th>
                            <th>Sharing</th>
                            <?php if ($canManage): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAttendances as $attendance): ?>
                        <tr>
                            <td>
                                <?= $this->Html->link(
                                            h($attendance->gathering->name),
                                            ['controller' => 'Gatherings', 'action' => 'view', $attendance->gathering->public_id]
                                        ) ?>
                                <?php if ($attendance->gathering->is_cancelled): ?>
                                    <span class="badge bg-danger ms-1"><?= __('CANCELLED') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($attendance->gathering->branch->name) ?></td>
                            <td><?= h($attendance->gathering->gathering_type->name) ?></td>
                            <td>
                                <?= $this->Timezone->format($attendance->gathering->start_date, $attendance->gathering, 'M d, Y') ?>
                                <?php if ($attendance->gathering->end_date != $attendance->gathering->start_date): ?>
                                -
                                <?= $this->Timezone->format($attendance->gathering->end_date, $attendance->gathering, 'M d, Y') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= h($attendance->public_note) ?></td>
                            <td>
                                <small>
                                    <?php
                                            $sharing = [];
                                            if ($attendance->share_with_kingdom) $sharing[] = 'Kingdom';
                                            if ($attendance->share_with_hosting_group) $sharing[] = 'Hosting Group';
                                            if ($attendance->share_with_crown) $sharing[] = 'Crown';
                                            echo !empty($sharing) ? implode(', ', $sharing) : 'Not shared';
                                            ?>
                                </small>
                            </td>
                            <?php if ($canManage): ?>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editGatheringAttendanceModal"
                                    data-attendance-id="<?= $attendance->id ?>"
                                    data-gathering-id="<?= $attendance->gathering->id ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?= $this->Form->postLink(
                                                '<i class="bi bi-trash"></i>',
                                                ['controller' => 'GatheringAttendances', 'action' => 'delete', $attendance->id],
                                                [
                                                    'confirm' => 'Are you sure you want to remove this attendance?',
                                                    'class' => 'btn btn-sm btn-danger',
                                                    'escape' => false
                                                ]
                                            ) ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Past Gatherings Tab -->
        <div class="tab-pane fade" id="past" role="tabpanel" aria-labelledby="past-tab">
            <?php if (empty($pastAttendances)): ?>
            <div class="alert alert-info mt-3">
                No past gatherings on record.
            </div>
            <?php else: ?>
            <div class="table-responsive mt-3">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Gathering</th>
                            <th>Branch</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Note</th>
                            <th>Sharing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pastAttendances as $attendance): ?>
                        <tr>
                            <td>
                                <?= $this->Html->link(
                                            h($attendance->gathering->name),
                                            ['controller' => 'Gatherings', 'action' => 'view', $attendance->gathering->public_id]
                                        ) ?>
                                <?php if ($attendance->gathering->is_cancelled): ?>
                                    <span class="badge bg-danger ms-1"><?= __('CANCELLED') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($attendance->gathering->branch->name) ?></td>
                            <td><?= h($attendance->gathering->gathering_type->name) ?></td>
                            <td>
                                <?= $this->Timezone->format($attendance->gathering->start_date, $attendance->gathering, 'M d, Y') ?>
                                <?php if ($attendance->gathering->end_date != $attendance->gathering->start_date): ?>
                                -
                                <?= $this->Timezone->format($attendance->gathering->end_date, $attendance->gathering, 'M d, Y') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= h($attendance->public_note) ?></td>
                            <td>
                                <small>
                                    <?php
                                            $sharing = [];
                                            if ($attendance->share_with_kingdom) $sharing[] = 'Kingdom';
                                            if ($attendance->share_with_hosting_group) $sharing[] = 'Hosting Group';
                                            if ($attendance->share_with_crown) $sharing[] = 'Crown';
                                            echo !empty($sharing) ? implode(', ', $sharing) : 'Not shared';
                                            ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
