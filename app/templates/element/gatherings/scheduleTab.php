<?php

/**
 * Gathering Schedule Tab
 *
 * Displays scheduled activities for a gathering with add/edit/delete capabilities.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\User $user
 */
$canEditGathering = $user->checkCan('edit', $gathering);
$canCreateScheduledActivity = $user->checkCan('createScheduledActivity', $gathering);
$showScheduleActions = $canEditGathering || $canCreateScheduledActivity;
$scheduleStartValue = $this->Timezone->format($gathering->start_date, 'Y-m-d\TH:i', false, null, $gathering);
$scheduleEndValue = $this->Timezone->format($gathering->end_date, 'Y-m-d\TH:i', false, null, $gathering);
$addScheduleUrl = $this->Url->build(['action' => 'addScheduledActivity', $gathering->public_id]);
$editScheduleUrl = $this->Url->build(['action' => 'editScheduledActivity', $gathering->public_id, '__ID__']);
$deleteScheduleUrl = $this->Url->build(['action' => 'deleteScheduledActivity', $gathering->public_id, '__ID__']);
?>
<div class="related tab-pane fade m-3"
    id="nav-schedule"
    role="tabpanel"
    aria-labelledby="nav-schedule-tab"
    data-detail-tabs-target="tabContent"
    data-tab-order="4"
    style="order: 4;"
    data-controller="gathering-schedule"
    data-gathering-schedule-gathering-id-value="<?= $gathering->public_id ?>"
    data-gathering-schedule-gathering-start-value="<?= h($scheduleStartValue) ?>"
    data-gathering-schedule-gathering-end-value="<?= h($scheduleEndValue) ?>"
    data-gathering-schedule-add-url-value="<?= h($addScheduleUrl) ?>"
    data-gathering-schedule-edit-url-value="<?= h($editScheduleUrl) ?>"
    data-gathering-schedule-delete-url-value="<?= h($deleteScheduleUrl) ?>">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?= __('Event Schedule') ?></h4>
        <?php if ($canCreateScheduledActivity) : ?>
            <button type="button"
                class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addScheduleModal">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= __('Add Scheduled Activity') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($gathering->gathering_scheduled_activities)) : ?>
        <div class="table-responsive" data-gathering-schedule-target="scheduleList">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th><?= __('Time') ?></th>
                        <th><?= __('Activity Type') ?></th>
                        <th><?= __('Title') ?></th>
                        <th><?= __('Description') ?></th>
                        <th><?= __('Pre-Registration') ?></th>
                        <?php if ($showScheduleActions) : ?>
                            <th class="actions text-end"><?= __('Actions') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gathering->gathering_scheduled_activities as $scheduledActivity) : ?>
                        <?php
                        $canEditScheduledActivity = $canEditGathering
                            || $user->checkCan('editScheduledActivity', $gathering, $scheduledActivity);
                        $scheduledStartDate = $this->Timezone->format(
                            $scheduledActivity->start_datetime,
                            'D, M j',
                            false,
                            null,
                            $gathering,
                        );
                        $scheduledStartTime = $this->Timezone->format(
                            $scheduledActivity->start_datetime,
                            'g:i A',
                            false,
                            null,
                            $gathering,
                        );
                        $scheduledEndTime = $scheduledActivity->end_datetime
                            ? $this->Timezone->format(
                                $scheduledActivity->end_datetime,
                                'g:i A',
                                false,
                                null,
                                $gathering,
                            )
                            : '';
                        $scheduledStartInput = $this->Timezone->forInput(
                            $scheduledActivity->start_datetime,
                            'Y-m-d\TH:i',
                            null,
                            $gathering,
                        );
                        $scheduledEndInput = $scheduledActivity->end_datetime
                            ? $this->Timezone->forInput(
                                $scheduledActivity->end_datetime,
                                'Y-m-d\TH:i',
                                null,
                                $gathering,
                            )
                            : '';
                        $gatheringActivityId = $scheduledActivity->gathering_activity_id;
                        $preRegisterValue = $scheduledActivity->pre_register ? 'true' : 'false';
                        $isOtherValue = $scheduledActivity->is_other ? 'true' : 'false';
                        $hasEndTimeValue = $scheduledActivity->has_end_time ? 'true' : 'false';
                        ?>
                        <tr>
                            <td class="text-nowrap">
                                <strong><?= h($scheduledStartDate) ?></strong><br>
                                <?= h($scheduledStartTime) ?>
                                <?php if ($scheduledActivity->end_datetime) : ?>
                                    - <?= h($scheduledEndTime) ?>
                                    <br>
                                    <small class="text-muted">
                                        (<?= number_format($scheduledActivity->duration_hours, 1) ?>
                                        <?= __n('hour', 'hours', $scheduledActivity->duration_hours) ?>)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($scheduledActivity->is_other) : ?>
                                    <span class="badge bg-secondary"><?= __('Other') ?></span>
                                <?php else : ?>
                                    <?= h($scheduledActivity->gathering_activity->name ?? __('N/A')) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= h($scheduledActivity->display_title) ?></strong>
                            </td>
                            <td>
                                <?= h($scheduledActivity->description) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($scheduledActivity->pre_register) : ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle" aria-hidden="true"></i> <?= __('Enabled') ?>
                                    </span>
                                <?php else : ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i> <?= __('Disabled') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php if ($showScheduleActions) : ?>
                                <td class="actions text-end text-nowrap">
                                    <?php if ($canEditScheduledActivity) : ?>
                                        <button type="button"
                                            class="btn btn-sm btn-primary"
                                            data-action="click->gathering-schedule#openEditModal"
                                            data-activity-id="<?= $scheduledActivity->id ?>"
                                            data-gathering-activity-id="<?= h($gatheringActivityId) ?>"
                                            data-start-datetime="<?= h($scheduledStartInput) ?>"
                                            data-end-datetime="<?= h($scheduledEndInput) ?>"
                                            data-display-title="<?= h($scheduledActivity->display_title) ?>"
                                            data-description="<?= h($scheduledActivity->description) ?>"
                                            data-pre-register="<?= $preRegisterValue ?>"
                                            data-is-other="<?= $isOtherValue ?>"
                                            data-has-end-time="<?= $hasEndTimeValue ?>"
                                            title="<?= __('Edit {0}', $scheduledActivity->display_title) ?>"
                                            aria-label="<?= __('Edit {0}', $scheduledActivity->display_title) ?>">
                                            <i class="bi bi-pencil-fill" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canEditGathering) : ?>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash-fill" aria-hidden="true"></i>',
                                            [
                                                'action' => 'deleteScheduledActivity',
                                                $gathering->public_id,
                                                $scheduledActivity->id,
                                            ],
                                            [
                                                'confirm' => __(
                                                    'Are you sure you want to delete "{0}"?',
                                                    $scheduledActivity->display_title,
                                                ),
                                                'escape' => false,
                                                'title' => __('Delete {0}', $scheduledActivity->display_title),
                                                'aria-label' => __(
                                                    'Delete {0}',
                                                    $scheduledActivity->display_title,
                                                ),
                                                'class' => 'btn btn-sm btn-danger',
                                            ],
                                        ) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="alert alert-secondary">
            <i class="bi bi-info-circle"></i>
            <?= __('No activities have been scheduled yet.') ?>
            <?php if ($canCreateScheduledActivity) : ?>
                <?= __('Click "Add Scheduled Activity" above to create your event schedule.') ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php // Modals must be inside the controller scope for Stimulus targets to work
    ?>
    <?php if ($canCreateScheduledActivity) : ?>
        <?= $this->element('gatherings/addScheduleModal', ['gathering' => $gathering]) ?>
    <?php endif; ?>
    <?php if ($showScheduleActions) : ?>
        <?= $this->element('gatherings/editScheduleModal', ['gathering' => $gathering]) ?>
    <?php endif; ?>
</div>