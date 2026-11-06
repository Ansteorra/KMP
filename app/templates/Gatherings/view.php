<?php
declare(strict_types=1);

use App\KMP\TimezoneHelper;
use Cake\I18n\Date;

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<?php
$this->extend('reader');

$publicLandingUrl = $this->Url->build(['action' => 'publicLanding', $gathering->public_id], ['fullBase' => true]);
$hasManagementActions = $user->checkCan('edit', $gathering)
    || $user->checkCan('publish', $gathering)
    || $user->checkCan('delete', $gathering)
    || ($gathering->gathering_type->clonable && $user->checkCan('add', $gathering));
?>
<?= $this->KMP->startBlock('recordActions') ?>
<?php if ($user->checkCan('edit', $gathering)) : ?>
    <?= $this->Html->link(__('Edit'), ['action' => 'edit', $gathering->id], ['class' => 'btn btn-primary btn-sm']) ?>
<?php endif; ?>

<?php if ($hasManagementActions) : ?>
<div class="dropdown d-inline-block">
    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
        aria-expanded="false"><?= __('More actions') ?></button>
    <div class="dropdown-menu dropdown-menu-end p-3 gathering-management-actions">
    <?php if ($user->checkCan('edit', $gathering)) : ?>
        <?php if (!$gathering->is_cancelled) : ?>
<!-- Cancel Gathering Button -->
<button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#cancelGatheringModal">
    <kmp-icon name="circle-x"  aria-hidden="true"></kmp-icon> <?= __('Cancel Gathering') ?>
</button>
        <?php else : ?>
<!-- Restore Cancelled Gathering -->
            <?= $this->Form->postLink(
                '<kmp-icon name="rotate"  aria-hidden="true"></kmp-icon> ' . __('Restore Gathering'),
                ['action' => 'uncancel', $gathering->id],
                [
                'confirm' => __('Are you sure you want to restore "{0}"?', $gathering->name),
                'class' => 'btn btn-outline-success btn-sm',
                'escape' => false,
                ],
            ) ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($user->checkCan('publish', $gathering)) : ?>
        <?php if ($gathering->published) : ?>
            <?= $this->Form->postLink(
                __('Unpublish'),
                ['action' => 'publish', $gathering->id, '?' => ['publish' => false]],
                [
                'confirm' => __(
                    'Are you sure you want to unpublish "{0}"? '
                        . 'This will remove the gathering from the Kingdom Calendar.',
                    $gathering->name,
                ),
                'class' => 'btn btn-secondary btn-sm',
                ],
            ) ?>
        <?php else : ?>
            <?= $this->Form->postLink(
                __('Publish'),
                ['action' => 'publish', $gathering->id, '?' => ['publish' => true]],
                [
                'confirm' => __(
                    'Are you sure you want to publish "{0}"? '
                        . 'This will make the gathering available on the Kingdom Calendar.',
                    $gathering->name,
                ),
                'class' => 'btn btn-outline-success btn-sm',
                ],
            ) ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($gathering->gathering_type->clonable && $user->checkCan('add', $gathering)) : ?>
<button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#cloneGatheringModal">
    <kmp-icon name="files"  aria-hidden="true"></kmp-icon> <?= __('Clone') ?>
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
    </div>
</div>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php if ($canViewManagement) : ?>
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
                    ['controller' => 'Branches', 'action' => 'view', $gathering->branch->public_id],
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
<tr>
    <th scope="row" class="col"><?= __('Created By') ?></th>
    <td class="col-10"><?= $gathering->has('creator') ? h($gathering->creator->sca_name) : '' ?></td>
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
            <kmp-icon name="info-circle"  aria-hidden="true"></kmp-icon>
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
            $timezoneString = !empty($gathering->timezone) ? $gathering->timezone : TimezoneHelper::getAppTimezone();
            $gatheringTz = new DateTimeZone($timezoneString);
            $startInGatheringTz = (clone $gathering->start_date)->setTimezone($gatheringTz);
            $endInGatheringTz = (clone $gathering->end_date)->setTimezone($gatheringTz);

            $startDate = Date::parse($startInGatheringTz->format('Y-m-d'));
            $endDate = Date::parse($endInGatheringTz->format('Y-m-d'));
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
    <?php if (!empty($gathering->website_url)) : ?>
<tr scope="row">
    <th class="col"><?= __('Website') ?></th>
    <td class="col-10">
        <a href="<?= h($gathering->website_url) ?>" target="_blank" rel="noopener"><?= h($gathering->website_url) ?></a>
            <?php if ($gathering->public_page_enabled) : ?>
        <small class="text-muted ms-2">
                <?= __('(not shown publicly - the public landing page is used as the event link)') ?>
        </small>
            <?php endif; ?>
    </td>
</tr>
    <?php endif; ?>
    <?php if (!empty($gathering->preregister_url)) : ?>
<tr scope="row">
    <th class="col"><?= __('Pre-Registration') ?></th>
    <td class="col-10">
        <a href="<?= h($gathering->preregister_url) ?>" target="_blank"
            rel="noopener"><?= h($gathering->preregister_url) ?></a>
    </td>
</tr>
    <?php endif; ?>
<tr scope="row">
    <th class="col"><?= __('Published') ?></th>
    <td>
        <?php if ($gathering->published) : ?>
        <span class="badge bg-success"><?= 'Yes' ?></span>
        <?php else : ?>
        <span class="badge bg-secondary"><?= 'No' ?></span>
        <?php endif; ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Public Landing Page') ?></th>
    <td class="col-10">
        <?php if ($gathering->public_page_enabled) : ?>
        <span class="badge bg-success"><kmp-icon name="circle-check"  aria-hidden="true"></kmp-icon> Enabled</span>
        <a href="<?= $publicLandingUrl ?>" target="_blank" class="ms-2">
            <kmp-icon name="external-link"  aria-hidden="true"></kmp-icon> View Public Page
        </a>
        <?php else : ?>
        <span class="badge bg-secondary"><kmp-icon name="circle-x"  aria-hidden="true"></kmp-icon> Disabled</span>
        <small class="text-muted ms-2">Public cannot access this event page</small>
        <?php endif; ?>
    </td>
</tr>
    <?php if ($gathering->is_cancelled) : ?>
<tr scope="row" class="table-danger">
    <th class="col"><?= __('Status') ?></th>
    <td class="col-10">
        <span class="badge bg-danger"><kmp-icon name="circle-x" aria-hidden="true"></kmp-icon>
                            <?= __('CANCELLED') ?></span>
        <small class="text-muted ms-2">
            <?= __('Cancelled on {0}', $this->Timezone->format($gathering->cancelled_at, 'F j, Y g:i A', true)) ?>
        </small>
            <?php if (!empty($gathering->cancellation_reason)) : ?>
        <div class="mt-1">
            <strong><?= __('Reason:') ?></strong> <?= h($gathering->cancellation_reason) ?>
        </div>
            <?php endif; ?>
    </td>
</tr>
    <?php endif; ?>
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
<?php endif; ?>

<?php $this->KMP->startBlock('tabButtons') ?>
<?php if ($canViewManagement) : ?>
<button class="nav-link" id="nav-staff-tab" data-bs-toggle="tab" data-bs-target="#nav-staff" type="button" role="tab"
    aria-controls="nav-staff" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="3"
    style="order: 3;">
    <kmp-icon name="id-badge"  aria-hidden="true"></kmp-icon> <?= __('Staff') ?>
    <span class="badge bg-secondary"><?= count($gathering->gathering_staff) ?></span>
</button>
<?php endif; ?>
<button class="nav-link" id="nav-schedule-tab" data-bs-toggle="tab" data-bs-target="#nav-schedule" type="button"
    role="tab" aria-controls="nav-schedule" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="4"
    style="order: 4;">
    <kmp-icon name="calendar-event"  aria-hidden="true"></kmp-icon> <?= __('Schedule') ?>
    <span id="gathering-schedule-count" data-refresh-on="schedule"
        class="badge bg-secondary"><?= count($gathering->gathering_scheduled_activities) ?></span>
</button>
<button class="nav-link" id="nav-activities-tab" data-bs-toggle="tab" data-bs-target="#nav-activities" type="button"
    role="tab" aria-controls="nav-activities" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="5"
    style="order: 5;">
    <?= __('Activities') ?>
    <span class="badge bg-secondary"><?= count($gathering->gathering_activities) ?></span>
</button>
<?php if ($canViewManagement && !empty($gathering->location)) : ?>
<button class="nav-link" id="nav-location-tab" data-bs-toggle="tab" data-bs-target="#nav-location" type="button"
    role="tab" aria-controls="nav-location" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="6"
    style="order: 6;">
    <kmp-icon name="map-pin"  aria-hidden="true"></kmp-icon> <?= __('Location') ?>
</button>
<?php endif; ?>
<?php if ($user->checkCan('viewAttendance', $gathering)) : ?>
<button class="nav-link" id="nav-attendance-tab" data-bs-toggle="tab" data-bs-target="#nav-attendance" type="button"
    role="tab" aria-controls="nav-attendance" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="7"
    style="order: 7;">
    <kmp-icon name="users"  aria-hidden="true"></kmp-icon> <?= __('Attendance') ?>
    <span id="gathering-attendance-count" data-refresh-on="attendance"
        class="badge bg-secondary"><?= $totalAttendanceCount ?></span>
</button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('tabContent') ?>
<!-- Tab content panels with order matching tab buttons -->
<!-- Staff Tab -->
<?php if ($canViewManagement) : ?>
    <?= $this->element('gatherings/staffTab', [
        'gathering' => $gathering,
        'user' => $user,
    ]) ?>
<?php endif; ?>

<!-- Schedule Tab -->
<?= $this->element('gatherings/scheduleTab', [
    'gathering' => $gathering,
    'user' => $user,
]) ?>

<div class="related tab-pane fade m-3" id="nav-activities" role="tabpanel" aria-labelledby="nav-activities-tab"
    data-detail-tabs-target="tabContent" data-tab-order="5" style="order: 5;">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <?php if ($canAddActivity) : ?>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <kmp-icon name="circle-plus"  aria-hidden="true"></kmp-icon>
            <?= __('Add Activity') ?>
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
                        <?= $this->GatheringActivity->badge($activity) ?>
                        <?php if ($isNotRemovable) : ?>
                        <span class="badge bg-warning text-dark ms-2"
                            title="<?= __('This activity is required by the gathering type and cannot be removed') ?>">
                            <kmp-icon name="lock"  aria-hidden="true"></kmp-icon> <?= __('Required') ?>
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
                                '<kmp-icon name="eye"  aria-hidden="true"></kmp-icon>',
                                ['controller' => 'GatheringActivities', 'action' => 'view', $activity->id],
                                ['escape' => false, 'title' => __('View'), 'class' => 'btn btn-sm btn-secondary'],
                            ) ?>
                        <?php endif; ?>
                        <?php if ($user->checkCan('manageActivity', $gathering, $activity)) : ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                            data-bs-target="#editActivityDescriptionModal" data-activity-id="<?= $activity->id ?>"
                            data-activity-name="<?= h($activity->name) ?>"
                            data-default-description="<?= h($activity->description) ?>"
                            data-custom-description="<?= h($activity->_joinData->custom_description ?? '') ?>"
                            title="<?= __('Edit Description') ?>">
                            <kmp-icon name="pencil"  aria-hidden="true"></kmp-icon>
                        </button>
                        <?php endif; ?>
                        <?php
                                $canRemoveActivity = $user->checkCan('manageActivity', $gathering, $activity) && (
                                    !$hasWaivers || (
                                        $waiverRemovalAuthorization !== null &&
                                        $user->checkCan(
                                            'removeGatheringActivity',
                                            $waiverRemovalAuthorization,
                                            $activity->id,
                                        )
                                    )
                                );
                        ?>
                        <?php if ($canRemoveActivity) : ?>
                            <?php if ($isNotRemovable) : ?>
                        <button type="button" class="btn btn-sm btn-secondary" disabled
                            title="<?= __('This activity is required by the gathering type and cannot be removed') ?>">
                            <kmp-icon name="lock"  aria-hidden="true"></kmp-icon>
                        </button>
                            <?php else : ?>
                                <?= $this->Form->postLink(
                                    '<kmp-icon name="circle-x"  aria-hidden="true"></kmp-icon>',
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
        <kmp-icon name="info-circle"  aria-hidden="true"></kmp-icon>
        <?= __('No activities have been added to this gathering yet.') ?>
        <?php if ($canAddActivity) : ?>
            <?= __('Click "Add Activity" above to get started.') ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($canViewManagement && !empty($gathering->location)) : ?>
    <?= $this->element('gatherings/mapTab', [
        'gathering' => $gathering,
        'user' => $user,
    ]) ?>
<?php endif; ?>

<?php if ($user->checkCan('viewAttendance', $gathering)) : ?>
<div class="related tab-pane fade m-3" id="nav-attendance" role="tabpanel" aria-labelledby="nav-attendance-tab"
    data-detail-tabs-target="tabContent" data-tab-order="7" style="order: 7;">
    <div id="gathering-attendance-content" data-refresh-on="attendance">
    <?= $this->element('gatherings/attendanceTab', [
            'gathering' => $gathering,
            'totalAttendanceCount' => $totalAttendanceCount,
            'user' => $user,
        ]) ?>
    </div>
</div>
<?php endif; ?>

<?php $this->KMP->endBlock() ?>

<?php
echo $this->KMP->startBlock('modals');

// Schedule modals are now rendered inside scheduleTab.php for Stimulus scope

// Add Activity Modal
if ($canAddActivity) {
    echo $this->element('gatherings/addActivityModal', [
        'gathering' => $gathering,
        'availableActivities' => $availableActivities,
        'user' => $user,
    ]);
}
echo $this->element('gatherings/editActivityDescriptionModal', [
    'gathering' => $gathering,
    'user' => $user,
]);

// Clone Gathering Modal
if ($gathering->gathering_type->clonable && $user->checkCan('add', $gathering)) {
    echo $this->element('gatherings/cloneModal', [
        'gathering' => $gathering,
        'user' => $user,
    ]);
}

// Cancel Gathering Modal
if ($user->checkCan('edit', $gathering) && !$gathering->is_cancelled) {
    echo $this->element('gatherings/cancelModal', [
        'gathering' => $gathering,
        'user' => $user,
    ]);
}

$this->KMP->endBlock();
?>
