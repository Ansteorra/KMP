<?php

use function Cake\Collection\collection;

/**
 * Edit Scheduled Activity Modal
 * 
 * Modal form for editing an existing scheduled activity.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true"
    data-gathering-schedule-target="editModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editScheduleModalLabel">
                    <i class="bi bi-pencil-fill"></i> <?= __('Edit Scheduled Activity') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <?= $this->Form->create(null, [
                'id' => 'editScheduleForm',
                'data-gathering-schedule-target' => 'editForm',
                'data-action' => 'submit->gathering-schedule#submitEditForm',
            ]) ?>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <?= __(
                        'This gathering runs from {0} to {1}. Scheduled activities must fall within these dates.',
                        $gathering->start_date->format('F j, Y'),
                        $gathering->end_date->format('F j, Y')
                    ) ?>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <?= $this->Form->control('start_datetime', [
                            'type' => 'datetime-local',
                            'label' => __('Start Date & Time'),
                            'required' => true,
                            'class' => 'form-control',
                            'id' => 'edit-start-datetime',
                            'data-gathering-schedule-target' => 'editStartDatetime',
                            'data-action' => 'change->gathering-schedule#validateDatetimeRange',
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-">
                            <div class="form-check">
                                <?= $this->Form->checkbox('has_end_time', [
                                    'id' => 'edit-has-end-time',
                                    'class' => 'form-check-input',
                                    'value' => '1',
                                    'data-gathering-schedule-target' => 'editHasEndTimeCheckbox',
                                    'data-action' => 'change->gathering-schedule#toggleEditEndTime',
                                ]) ?>
                                <label class="form-check-label" for="edit-has-end-time">
                                    <?= __('End Date & Time') ?>
                                </label>
                            </div>
                        </div>
                        <div data-gathering-schedule-target="editEndTimeContainer" style="display: none;">
                            <?= $this->Form->control('end_datetime', [
                                'type' => 'datetime-local',
                                'label' => false,
                                'required' => false,
                                'class' => 'form-control',
                                'id' => 'edit-end-datetime',
                                'data-gathering-schedule-target' => 'editEndDatetime',
                                'data-action' => 'change->gathering-schedule#validateDatetimeRange',
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <?= $this->Form->checkbox('is_other', [
                            'id' => 'edit-is-other',
                            'class' => 'form-check-input',
                            'data-gathering-schedule-target' => 'editIsOtherCheckbox',
                            'data-action' => 'change->gathering-schedule#handleEditOtherChange',
                        ]) ?>
                        <label class="form-check-label" for="edit-is-other">
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
                        'data-gathering-schedule-target' => 'editActivitySelect',
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
                            'id' => 'edit-pre-register',
                            'class' => 'form-check-input',
                        ]) ?>
                        <label class="form-check-label" for="edit-pre-register">
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
                    <i class="bi bi-save"></i> <?= __('Save Changes') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>