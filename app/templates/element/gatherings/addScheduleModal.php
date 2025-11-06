<?php

use function Cake\Collection\collection;

/**
 * Add Scheduled Activity Modal
 * 
 * Modal form for adding a new scheduled activity to a gathering.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true"
    data-gathering-schedule-target="addModal" data-action="shown.bs.modal->gathering-schedule#resetAddForm">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addScheduleModalLabel">
                    <i class="bi bi-calendar-plus"></i> <?= __('Add Scheduled Activity') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <?= $this->Form->create(null, [
                'id' => 'addScheduleForm',
                'data-gathering-schedule-target' => 'addForm',
                'data-action' => 'submit->gathering-schedule#submitAddForm',
            ]) ?>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <?php
                    $timezone = \App\KMP\TimezoneHelper::getGatheringTimezone($gathering, $this->getRequest()->getAttribute('identity'));
                    ?>
                    <?= __(
                        'This gathering runs from {0} to {1}. Scheduled activities must fall within these dates.',
                        $this->Timezone->format($gathering->start_date, 'F j, Y g:i A', false, null, $gathering),
                        $this->Timezone->format($gathering->end_date, 'F j, Y g:i A', false, null, $gathering)
                    ) ?>
                    <br>
                    <small>
                        <i class="bi bi-clock"></i> <?= __('All times in {0}', $timezone) ?>
                    </small>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <?= $this->Form->control('start_datetime', [
                            'type' => 'datetime-local',
                            'label' => __('Start Date & Time'),
                            'required' => true,
                            'class' => 'form-control',
                            'id' => 'add-start-datetime',
                            'data-gathering-schedule-target' => 'startDatetime',
                            'data-action' => 'change->gathering-schedule#validateDatetimeRange',
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-1">
                            <div class="form-check">
                                <?= $this->Form->checkbox('has_end_time', [
                                    'id' => 'add-has-end-time',
                                    'class' => 'form-check-input',
                                    'value' => '1',
                                    'data-gathering-schedule-target' => 'hasEndTimeCheckbox',
                                    'data-action' => 'change->gathering-schedule#toggleEndTime',
                                ]) ?>
                                <label class="form-check-label" for="add-has-end-time">
                                    <?= __('End Date & Time') ?>
                                </label>
                            </div>
                        </div>
                        <div data-gathering-schedule-target="endTimeContainer" style="display: none;">
                            <?= $this->Form->control('end_datetime', [
                                'type' => 'datetime-local',
                                'required' => false,
                                'label' => false,
                                'class' => 'form-control',
                                'id' => 'add-end-datetime',
                                'data-gathering-schedule-target' => 'endDatetime',
                                'data-action' => 'change->gathering-schedule#validateDatetimeRange',
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <?= $this->Form->checkbox('is_other', [
                            'id' => 'add-is-other',
                            'class' => 'form-check-input',
                            'data-gathering-schedule-target' => 'isOtherCheckbox',
                            'data-action' => 'change->gathering-schedule#handleOtherChange',
                        ]) ?>
                        <label class="form-check-label" for="add-is-other">
                            <?= __('This is an "Other" activity (not from the gathering\'s activity list)') ?>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <?= $this->Form->control('gathering_activity_id', [
                        'type' => 'select',
                        'options' => $gathering->gathering_activities ?
                            collection($gathering->gathering_activities)->combine('id', 'name')->toArray() :
                            [],
                        'empty' => __('-- Select Activity --'),
                        'label' => __('Gathering Activity'),
                        'class' => 'form-select',
                        'data-gathering-schedule-target' => 'activitySelect',
                    ]) ?>
                    <small class="form-text text-muted">
                        <?= __('Select an activity from the gathering\'s activity list, or check "Other" above.') ?>
                    </small>
                </div>

                <div class="mb-3">
                    <?= $this->Form->control('display_title', [
                        'type' => 'text',
                        'label' => __('Display Title'),
                        'required' => true,
                        'class' => 'form-control',
                        'placeholder' => __('e.g., "Baronial Armored Championship"'),
                    ]) ?>
                    <small class="form-text text-muted">
                        <?= __('The custom title that will be displayed in the schedule.') ?>
                    </small>
                </div>

                <div class="mb-3">
                    <?= $this->Form->control('description', [
                        'type' => 'textarea',
                        'label' => __('Description'),
                        'class' => 'form-control',
                        'rows' => 3,
                        'placeholder' => __('e.g., "Round robin tourney with bring your best"'),
                    ]) ?>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <?= $this->Form->checkbox('pre_register', [
                            'id' => 'add-pre-register',
                            'class' => 'form-check-input',
                        ]) ?>
                        <label class="form-check-label" for="add-pre-register">
                            <?= __('Enable pre-registration') ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= __('Cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> <?= __('Add Scheduled Activity') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>