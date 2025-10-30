<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $member
 * @var \App\Model\Entity\GatheringAttendance[] $gatheringAttendances
 * @var array $availableGatherings
 */

use Cake\I18n\DateTime;
use Cake\I18n\Date;

$today = Date::now(); // Get today's date (without time component)

// Separate upcoming and past gatherings
$upcomingAttendances = [];
$pastAttendances = [];

foreach ($member->gathering_attendances as $attendance) {
    // A gathering is "upcoming" if its end date is today or in the future
    if ($attendance->gathering->end_date >= $today) {
        $upcomingAttendances[] = $attendance;
    } else {
        $pastAttendances[] = $attendance;
    }
}

$canManageOwn = $user->id == $member->id;
$canManageOthers = $user->checkCan('add', 'GatheringAttendances');
$canManage = $canManageOwn || $canManageOthers;
?>

<div class="gathering-attendances">
    <?php if ($canManage): ?>
        <div class="mb-3">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#addGatheringAttendanceModal">
                <i class="bi bi-plus-circle"></i> Register for Gathering
            </button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab"
                data-bs-target="#upcoming" type="button" role="tab"
                aria-controls="upcoming" aria-selected="true">
                Upcoming (<?= count($upcomingAttendances) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="past-tab" data-bs-toggle="tab"
                data-bs-target="#past" type="button" role="tab"
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
                                            ['controller' => 'Gatherings', 'action' => 'view', $attendance->gathering->id]
                                        ) ?>
                                    </td>
                                    <td><?= h($attendance->gathering->branch->name) ?></td>
                                    <td><?= h($attendance->gathering->gathering_type->name) ?></td>
                                    <td>
                                        <?= $attendance->gathering->start_date->format('M d, Y') ?>
                                        <?php if ($attendance->gathering->end_date != $attendance->gathering->start_date): ?>
                                            - <?= $attendance->gathering->end_date->format('M d, Y') ?>
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
                                            if ($attendance->is_public) $sharing[] = 'Public';
                                            echo !empty($sharing) ? implode(', ', $sharing) : 'Not shared';
                                            ?>
                                        </small>
                                    </td>
                                    <?php if ($canManage): ?>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editGatheringAttendanceModal"
                                                data-attendance-id="<?= $attendance->id ?>"
                                                data-gathering-name="<?= h($attendance->gathering->name) ?>"
                                                data-public-note="<?= h($attendance->public_note) ?>"
                                                data-share-kingdom="<?= $attendance->share_with_kingdom ? '1' : '0' ?>"
                                                data-share-hosting="<?= $attendance->share_with_hosting_group ? '1' : '0' ?>"
                                                data-share-crown="<?= $attendance->share_with_crown ? '1' : '0' ?>"
                                                data-is-public="<?= $attendance->is_public ? '1' : '0' ?>">
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
                                            ['controller' => 'Gatherings', 'action' => 'view', $attendance->gathering->id]
                                        ) ?>
                                    </td>
                                    <td><?= h($attendance->gathering->branch->name) ?></td>
                                    <td><?= h($attendance->gathering->gathering_type->name) ?></td>
                                    <td>
                                        <?= $attendance->gathering->start_date->format('M d, Y') ?>
                                        <?php if ($attendance->gathering->end_date != $attendance->gathering->start_date): ?>
                                            - <?= $attendance->gathering->end_date->format('M d, Y') ?>
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
                                            if ($attendance->is_public) $sharing[] = 'Public';
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