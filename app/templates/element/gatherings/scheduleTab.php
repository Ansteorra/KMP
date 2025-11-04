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
    data-gathering-schedule-gathering-start-value="<?= $gathering->start_date->format('Y-m-d') ?>"
    data-gathering-schedule-gathering-end-value="<?= $gathering->end_date->format('Y-m-d') ?>"
    data-gathering-schedule-add-url-value="<?= $this->Url->build(['action' => 'addScheduledActivity', $gathering->public_id]) ?>"
    data-gathering-schedule-edit-url-value="<?= $this->Url->build(['action' => 'editScheduledActivity', $gathering->public_id, '__ID__']) ?>"
    data-gathering-schedule-delete-url-value="<?= $this->Url->build(['action' => 'deleteScheduledActivity', $gathering->public_id, '__ID__']) ?>">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?= __('Event Schedule') ?></h4>
        <?php if ($user->checkCan('edit', $gathering)): ?>
            <button type="button"
                class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addScheduleModal">
                <i class="bi bi-plus-circle"></i> <?= __('Add Scheduled Activity') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($gathering->gathering_scheduled_activities)): ?>
        <div class="table-responsive" data-gathering-schedule-target="scheduleList">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th><?= __('Time') ?></th>
                        <th><?= __('Activity Type') ?></th>
                        <th><?= __('Title') ?></th>
                        <th><?= __('Description') ?></th>
                        <th><?= __('Pre-Registration') ?></th>
                        <?php if ($user->checkCan('edit', $gathering)): ?>
                            <th class="actions text-end"><?= __('Actions') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gathering->gathering_scheduled_activities as $scheduledActivity): ?>
                        <tr>
                            <td class="text-nowrap">
                                <strong><?= $scheduledActivity->start_datetime->format('D, M j') ?></strong><br>
                                <?= $scheduledActivity->start_datetime->format('g:i A') ?>
                                <?php if ($scheduledActivity->end_datetime): ?>
                                    - <?= $scheduledActivity->end_datetime->format('g:i A') ?>
                                    <br>
                                    <small class="text-muted">
                                        (<?= number_format($scheduledActivity->duration_hours, 1) ?>
                                        <?= __n('hour', 'hours', $scheduledActivity->duration_hours) ?>)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($scheduledActivity->is_other): ?>
                                    <span class="badge bg-secondary"><?= __('Other') ?></span>
                                <?php else: ?>
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
                                <?php if ($scheduledActivity->pre_register): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> <?= __('Enabled') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x-circle"></i> <?= __('Disabled') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php if ($user->checkCan('edit', $gathering)): ?>
                                <td class="actions text-end text-nowrap">
                                    <button type="button"
                                        class="btn btn-sm btn-primary"
                                        data-action="click->gathering-schedule#openEditModal"
                                        data-activity-id="<?= $scheduledActivity->id ?>"
                                        data-gathering-activity-id="<?= $scheduledActivity->gathering_activity_id ?>"
                                        data-start-datetime="<?= $scheduledActivity->start_datetime->format('Y-m-d\TH:i') ?>"
                                        data-end-datetime="<?= $scheduledActivity->end_datetime ? $scheduledActivity->end_datetime->format('Y-m-d\TH:i') : '' ?>"
                                        data-display-title="<?= h($scheduledActivity->display_title) ?>"
                                        data-description="<?= h($scheduledActivity->description) ?>"
                                        data-pre-register="<?= $scheduledActivity->pre_register ? 'true' : 'false' ?>"
                                        data-is-other="<?= $scheduledActivity->is_other ? 'true' : 'false' ?>"
                                        data-has-end-time="<?= $scheduledActivity->has_end_time ? 'true' : 'false' ?>"
                                        title="<?= __('Edit') ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash-fill"></i>',
                                        ['action' => 'deleteScheduledActivity', $gathering->public_id, $scheduledActivity->id],
                                        [
                                            'confirm' => __('Are you sure you want to delete "{0}"?', $scheduledActivity->display_title),
                                            'escape' => false,
                                            'title' => __('Delete'),
                                            'class' => 'btn btn-sm btn-danger',
                                        ]
                                    ) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">
            <i class="bi bi-info-circle"></i>
            <?= __('No activities have been scheduled yet.') ?>
            <?php if ($user->checkCan('edit', $gathering)): ?>
                <?= __('Click "Add Scheduled Activity" above to create your event schedule.') ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php // Modals must be inside the controller scope for Stimulus targets to work 
    ?>
    <?php if ($user->checkCan('edit', $gathering)): ?>
        <?= $this->element('gatherings/addScheduleModal', ['gathering' => $gathering]) ?>
        <?= $this->element('gatherings/editScheduleModal', ['gathering' => $gathering]) ?>
    <?php endif; ?>
</div>