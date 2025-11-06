<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<?php
$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': View Gathering - ' . $gathering->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($gathering->name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordActions') ?>
<?php

use Cake\I18n\Date;

$today = Date::now();
$canAttend = $gathering->end_date >= $today; // Can only register if gathering hasn't ended
$isPastEvent = $gathering->end_date < $today; // Event has ended
// Build public landing URL using dashed route format
$publicLandingUrl = $this->Url->build([
    'controller' => 'Gatherings',
    'action' => 'public-landing',
    $gathering->public_id
], ['fullBase' => true]);
?>
<!-- Download Calendar Button (only for current/future events) -->
<?php if (!$isPastEvent): ?>
    <?= $this->Html->link(
        '<i class="bi bi-calendar-plus"></i> ' . __('Add to Calendar'),
        ['action' => 'downloadCalendar', $gathering->public_id],
        [
            'class' => 'btn btn-outline-success btn-sm',
            'escape' => false,
            'title' => __('Download calendar file (.ics) for Outlook, Google Calendar, iOS, etc.')
        ]
    ) ?>
<?php endif; ?>

<!-- Share Public Landing Page -->
<div class="btn-group" role="group">
    <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
        aria-expanded="false" <?= !$gathering->public_page_enabled ? 'disabled' : '' ?>>
        <i class="bi bi-share"></i> Share Event
    </button>
    <?php if ($gathering->public_page_enabled): ?>
        <ul class="dropdown-menu">
            <li>
                <a class="dropdown-item" href="<?= $publicLandingUrl ?>" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i> View Public Page
                </a>
            </li>
            <li>
                <a class="dropdown-item bi bi-clipboard" href="#"
                    onclick="navigator.clipboard.writeText(<?= json_encode($publicLandingUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); alert(<?= json_encode(__('Link copied to clipboard!')) ?>); return false;">
                    Copy Link
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                    <i class="bi bi-qr-code"></i> Show QR Code
                </a>
            </li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li class="px-3 py-2">
                <small class="text-muted">Share this link with potential attendees</small>
            </li>
        </ul>
    <?php else: ?>
        <ul class="dropdown-menu">
            <li class="px-3 py-2">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> Public landing page is disabled for this gathering.
                    Enable it in the Edit form to share this event.
                </small>
            </li>
        </ul>
    <?php endif; ?>
</div>

<?php if ($canAttend): ?>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#attendGatheringModal">
        <i class="bi bi-calendar-check"></i>
        <?= $userAttendance ? __('Update Attendance') : __('Attend This Gathering') ?>
    </button>
<?php endif; ?>
<?php if ($user->checkCan('edit', $gathering)) : ?>
    <?= $this->Html->link(__('Edit'), ['action' => 'edit', $gathering->id], ['class' => 'btn btn-primary btn-sm']) ?>
<?php endif; ?>
<?php if ($gathering->gathering_type->clonable && $user->checkCan('add', $gathering)) : ?>

    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#cloneGatheringModal">
        <i class="bi bi-files"></i> <?= __('Clone') ?>
    </button>

<?php endif; ?>
<?php if ($user->checkCan('delete', $gathering)) : ?>
    <?= $this->Form->postLink(
        __('Delete'),
        ['action' => 'delete', $gathering->id],
        [
            'confirm' => __('Are you sure you want to delete "{0}"?', $gathering->name),
            'class' => 'btn btn-danger btn-sm',
        ],
    ) ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('recordDetails') ?>
<tr scope="row">
    <th class="col"><?= __('Name') ?></th>
    <td class="col-10"><?= h($gathering->name) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Branch') ?></th>
    <td class="col-10">
        <?php if ($gathering->has('branch')) : ?>
            <?php if ($user->can('view', $gathering->branch)) : ?>
                <?= $this->Html->link(
                    $gathering->branch->name,
                    ['controller' => 'Branches', 'action' => 'view', $gathering->branch->id],
                ) ?>
            <?php else : ?>
                <?= h($gathering->branch->name) ?>
            <?php endif; ?>

        <?php endif; ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Gathering Type') ?></th>
    <td class="col-10"><?= h($gathering->gathering_type->name) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Start Date & Time') ?></th>
    <td class="col-10">
        <?= $this->Timezone->format($gathering->start_date, 'F j, Y g:i A', false, null, $gathering) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('End Date & Time') ?></th>
    <td class="col-10">
        <?= $this->Timezone->format($gathering->end_date, 'F j, Y g:i A', false, null, $gathering) ?>
    </td>
</tr>
<?php if (!empty($gathering->timezone)) : ?>
    <tr scope="row">
        <th class="col"><?= __('Event Timezone') ?></th>
        <td class="col-10">
            <?= h($gathering->timezone) ?>
            <small class="text-muted">
                (<?= $this->Timezone->getAbbreviation($gathering->start_date, $gathering->timezone) ?>)
            </small>
            <br>
            <small class="text-muted">
                <i class="bi bi-info-circle"></i>
                All times for this event are shown in <?= h($gathering->timezone) ?>
            </small>
        </td>
    </tr>
<?php endif; ?>
<?php if ($gathering->start_date != $gathering->end_date) : ?>
    <tr scope="row">
        <th class="col"><?= __('Duration') ?></th>
        <td class="col-10">
            <?php
            // Convert to gathering's timezone before calculating duration
            $gatheringTz = new \DateTimeZone($gathering->timezone ?? \App\KMP\TimezoneHelper::getAppTimezone());
            $startInGatheringTz = (clone $gathering->start_date)->setTimezone($gatheringTz);
            $endInGatheringTz = (clone $gathering->end_date)->setTimezone($gatheringTz);

            $startDate = \Cake\I18n\Date::parse($startInGatheringTz->format('Y-m-d'));
            $endDate = \Cake\I18n\Date::parse($endInGatheringTz->format('Y-m-d'));
            ?>
            <?= $startDate->diffInDays($endDate) + 1 ?> days
        </td>
    </tr>
<?php endif; ?>
<?php if (!empty($gathering->location)) : ?>
    <tr scope="row">
        <th class="col"><?= __('Location') ?></th>
        <td class="col-10"><?= h($gathering->location) ?></td>
    </tr>
<?php endif; ?>
<tr scope="row">
    <th class="col"><?= __('Public Landing Page') ?></th>
    <td class="col-10">
        <?php if ($gathering->public_page_enabled): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enabled</span>
            <a href="<?= $publicLandingUrl ?>" target="_blank" class="ms-2">
                <i class="bi bi-box-arrow-up-right"></i> View Public Page
            </a>
        <?php else: ?>
            <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Disabled</span>
            <small class="text-muted ms-2">Public cannot access this event page</small>
        <?php endif; ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Created') ?></th>
    <td class="col-10">
        <?= $this->Timezone->format($gathering->created, 'F j, Y g:i A', true) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Modified') ?></th>
    <td class="col-10">
        <?= $this->Timezone->format($gathering->modified, 'F j, Y g:i A', true) ?>
    </td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('tabButtons') ?>
<!-- Gatherings view tabs with ordering:
     Order 1: Description tab (first - markdown content)
     Order 3: Staff tab (new - stewards and other staff)
     Order 4: Schedule tab (new - before activities)
     Order 5: Activities tab (primary)
     Order 6: Location tab
     Order 7: Attendance tab
     Order 10: Waivers plugin tab
     -->
<?php if (!empty($gathering->description)) : ?>
    <button class="nav-link" id="nav-description-tab" data-bs-toggle="tab" data-bs-target="#nav-description" type="button"
        role="tab" aria-controls="nav-description" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="1"
        style="order: 1;">
        <i class="bi bi-file-text"></i> <?= __('Description') ?>
    </button>
<?php endif; ?>
<button class="nav-link" id="nav-staff-tab" data-bs-toggle="tab" data-bs-target="#nav-staff" type="button" role="tab"
    aria-controls="nav-staff" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="3"
    style="order: 3;">
    <i class="bi bi-person-badge"></i> <?= __('Staff') ?>
    <span class="badge bg-secondary"><?= count($gathering->gathering_staff) ?></span>
</button>
<button class="nav-link" id="nav-schedule-tab" data-bs-toggle="tab" data-bs-target="#nav-schedule" type="button"
    role="tab" aria-controls="nav-schedule" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="4"
    style="order: 4;">
    <i class="bi bi-calendar-event"></i> <?= __('Schedule') ?>
    <span class="badge bg-secondary"><?= count($gathering->gathering_scheduled_activities) ?></span>
</button>
<button class="nav-link" id="nav-activities-tab" data-bs-toggle="tab" data-bs-target="#nav-activities" type="button"
    role="tab" aria-controls="nav-activities" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="5"
    style="order: 5;">
    <?= __('Activities') ?>
    <span class="badge bg-secondary"><?= count($gathering->gathering_activities) ?></span>
</button>
<?php if (!empty($gathering->location)) : ?>
    <button class="nav-link" id="nav-location-tab" data-bs-toggle="tab" data-bs-target="#nav-location" type="button"
        role="tab" aria-controls="nav-location" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="6"
        style="order: 6;">
        <i class="bi bi-geo-alt-fill"></i> <?= __('Location') ?>
    </button>
<?php endif; ?>
<?php if ($user->checkCan('viewAttendance', $gathering)) : ?>
    <button class="nav-link" id="nav-attendance-tab" data-bs-toggle="tab" data-bs-target="#nav-attendance" type="button"
        role="tab" aria-controls="nav-attendance" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="7"
        style="order: 7;">
        <i class="bi bi-people-fill"></i> <?= __('Attendance') ?>
        <span class="badge bg-secondary"><?= $totalAttendanceCount ?></span>
    </button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('tabContent') ?>
<!-- Tab content panels with order matching tab buttons -->
<?php if (!empty($gathering->description)) : ?>
    <div class="related tab-pane fade m-3" id="nav-description" role="tabpanel" aria-labelledby="nav-description-tab"
        data-detail-tabs-target="tabContent" data-tab-order="1" style="order: 1;">
        <div class="markdown-content">
            <?= $this->Markdown->toHtml($gathering->description) ?>
        </div>
    </div>
<?php endif; ?>

<!-- Staff Tab -->
<?= $this->element('gatherings/staffTab', [
    'gathering' => $gathering,
    'user' => $user,
]) ?>

<!-- Schedule Tab -->
<?= $this->element('gatherings/scheduleTab', [
    'gathering' => $gathering,
    'user' => $user,
]) ?>

<div class="related tab-pane fade m-3" id="nav-activities" role="tabpanel" aria-labelledby="nav-activities-tab"
    data-detail-tabs-target="tabContent" data-tab-order="5" style="order: 5;">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <?php if ($user->checkCan('edit', $gathering) && !$hasWaivers) : ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="bi bi-plus-circle"></i> <?= __('Add Activity') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($gathering->gathering_activities)) : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= __('Activity') ?></th>
                        <th><?= __('Description') ?></th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gathering->gathering_activities as $activity) : ?>
                        <?php
                        // Check if this activity is not removable based on gathering type template
                        $isNotRemovable = $activity->_joinData && $activity->_joinData->not_removable;
                        ?>
                        <tr>
                            <td>
                                <?= h($activity->name) ?>
                                <?php if ($isNotRemovable): ?>
                                    <span class="badge bg-warning text-dark ms-2"
                                        title="<?= __('This activity is required by the gathering type and cannot be removed') ?>">
                                        <i class="bi bi-lock-fill"></i> <?= __('Required') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Use custom description if set, otherwise fall back to default activity description
                                $description = $activity->_joinData->custom_description ?? $activity->description;
                                echo h($description);
                                ?>
                            </td>
                            <td class="actions text-end text-nowrap">
                                <?php if ($user->checkCan('view', $activity)) : ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye-fill"></i>',
                                        ['controller' => 'GatheringActivities', 'action' => 'view', $activity->id],
                                        ['escape' => false, 'title' => __('View'), 'class' => 'btn btn-sm btn-secondary'],
                                    ) ?>
                                <?php endif; ?>
                                <?php if ($user->checkCan('edit', $gathering) && !$hasWaivers) : ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#editActivityDescriptionModal" data-activity-id="<?= $activity->id ?>"
                                        data-activity-name="<?= h($activity->name) ?>"
                                        data-default-description="<?= h($activity->description) ?>"
                                        data-custom-description="<?= h($activity->_joinData->custom_description ?? '') ?>"
                                        title="<?= __('Edit Description') ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <?php if ($isNotRemovable): ?>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled
                                            title="<?= __('This activity is required by the gathering type and cannot be removed') ?>">
                                            <i class="bi bi-lock-fill"></i>
                                        </button>
                                    <?php else: ?>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-x-circle-fill"></i>',
                                            ['action' => 'remove-activity', $gathering->id, $activity->id],
                                            [
                                                'confirm' => __('Remove "{0}" from this gathering?', $activity->name),
                                                'escape' => false,
                                                'title' => __('Remove'),
                                                'class' => 'btn btn-sm btn-danger',
                                            ],
                                        ) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="alert alert-secondary">
            <i class="bi bi-info-circle"></i>
            <?= __('No activities have been added to this gathering yet.') ?>
            <?php if ($user->checkCan('edit', $gathering) && !$hasWaivers) : ?>
                <?= __('Click "Add Activity" above to get started.') ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($gathering->location)) : ?>
    <?= $this->element('gatherings/mapTab', [
        'gathering' => $gathering,
        'user' => $user,
    ]) ?>
<?php endif; ?>

<?php if ($user->checkCan('viewAttendance', $gathering)) : ?>
    <div class="related tab-pane fade m-3" id="nav-attendance" role="tabpanel" aria-labelledby="nav-attendance-tab"
        data-detail-tabs-target="tabContent" data-tab-order="7" style="order: 7;">
        <?= $this->element('gatherings/attendanceTab', [
            'gathering' => $gathering,
            'totalAttendanceCount' => $totalAttendanceCount,
            'user' => $user,
        ]) ?>
    </div>
<?php endif; ?>

<?php $this->KMP->endBlock() ?>

<?php
echo $this->KMP->startBlock('modals');

// Schedule modals are now rendered inside scheduleTab.php for Stimulus scope

// Add Activity Modal
if ($user->checkCan('edit', $gathering) && !$hasWaivers) {
    echo $this->element('gatherings/addActivityModal', [
        'gathering' => $gathering,
        'availableActivities' => $availableActivities,
        'user' => $user,
    ]);

    // Edit Activity Description Modal
    echo $this->element('gatherings/editActivityDescriptionModal', [
        'gathering' => $gathering,
        'user' => $user,
    ]);
}

// Clone Gathering Modal
if ($gathering->gathering_type->clonable && $user->checkCan('add', $gathering)) {
    echo $this->element('gatherings/cloneModal', [
        'gathering' => $gathering,
        'user' => $user,
    ]);
}

// Attend Gathering Modal
if ($canAttend) {
    echo $this->element('gatherings/attendGatheringModal', [
        'gathering' => $gathering,
        'userAttendance' => $userAttendance,
        'user' => $user,
    ]);
}

// QR Code Modal for Public Landing Page - only show if public page is enabled
if ($gathering->public_page_enabled):
?>
    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true"
        data-controller="qrcode" data-qrcode-url-value="<?= h($publicLandingUrl) ?>"
        data-qrcode-modal-id-value="qrCodeModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">
                        <i class="bi bi-qr-code"></i> Share Event QR Code
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-3">Scan this QR code to view the event landing page</p>
                    <div data-qrcode-target="canvas" class="d-inline-block p-3 bg-white border rounded"></div>
                    <div class="mt-3">
                        <small class="text-muted d-block mb-2">Or copy the link:</small>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= h($publicLandingUrl) ?>" readonly
                                id="publicLandingUrlInput">
                            <button class="btn btn-outline-secondary bi bi-clipboard" type="button"
                                onclick="navigator.clipboard.writeText(<?= json_encode($publicLandingUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); this.innerHTML=<?= json_encode(' Copied!') ?>; setTimeout(() => this.innerHTML=<?= json_encode(' Copy') ?>, 2000)">
                                Copy
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="<?= $publicLandingUrl ?>" target="_blank" class="btn btn-primary">
                        <i class="bi bi-box-arrow-up-right"></i> Open Public Page
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php
endif;

$this->KMP->endBlock();
?>