<?php

declare(strict_types=1);

use App\KMP\TimezoneHelper;

use function Cake\Collection\collection;

/**
 * Edit Scheduled Activity Modal
 *
 * Modal form for editing an existing scheduled activity.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array<\App\Model\Entity\GatheringActivity> $scheduleActivities
 * @var bool $allowOtherActivities
 */
?>
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true"
    data-gathering-schedule-target="editModal"
    data-action="hidden.bs.modal->gathering-schedule#restoreEditFocus">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editScheduleModalLabel">
                    <i class="bi bi-pencil-fill" aria-hidden="true"></i> <?= __('Edit Scheduled Activity') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <?= $this->Form->create(null, [
                'id' => 'editScheduleForm',
                'data-gathering-schedule-target' => 'editForm',
                'data-action' => 'submit->gathering-schedule#submitEditForm',
            ]) ?>
            <div class="modal-body bg-light-subtle">
                <div class="alert alert-info border-start border-info border-4">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <?php
                    $timezone = TimezoneHelper::getGatheringTimezone($gathering, $this->getRequest()->getAttribute('identity'));
                    ?>
                    <?= __(
                        'This gathering runs from {0} to {1}. Scheduled activities must fall within these dates.',
                        $this->Timezone->format($gathering->start_date, 'F j, Y g:i A', false, null, $gathering),
                        $this->Timezone->format($gathering->end_date, 'F j, Y g:i A', false, null, $gathering),
                    ) ?>
                    <br>
                    <small>
                        <i class="bi bi-clock" aria-hidden="true"></i> <?= __('All times in {0}', $timezone) ?>
                    </small>
                </div>

                <fieldset class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                        <i class="bi bi-clock text-primary me-1" aria-hidden="true"></i>
                        <?= __('Schedule Timing') ?>
                    </legend>
                    <?= $this->element('gatherings/scheduleTimingFields', ['mode' => 'edit']) ?>
                </fieldset>

                <fieldset class="border rounded-3 bg-white shadow-sm p-3">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                        <i class="bi bi-card-text text-success me-1" aria-hidden="true"></i>
                        <?= __('Activity Details') ?>
                    </legend>
                    <div class="row g-3">
                        <div class="col-12 col-lg-5">
                            <?php if ($allowOtherActivities) : ?>
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
                            <?php else : ?>
                                <?= $this->Form->hidden('is_other', [
                                    'value' => '0',
                                    'id' => 'edit-is-other',
                                    'data-gathering-schedule-target' => 'editIsOtherCheckbox',
                                ]) ?>
                            <?php endif; ?>
                            <?= $this->Form->control('gathering_activity_id', [
                                'type' => 'select',
                                'options' => $scheduleActivities ?
                                    collection($scheduleActivities)->combine('id', 'name')->toArray() :
                                    [],
                                'empty' => __('-- Select Activity --'),
                                'label' => __('Gathering Activity'),
                                'required' => !$allowOtherActivities,
                                'class' => 'form-select',
                                'data-gathering-schedule-target' => 'editActivitySelect',
                            ]) ?>
                            <small class="form-text text-muted">
                                <?= $allowOtherActivities
                                    ? __('Select an activity from the gathering\'s activity list, or check "Other" above.')
                                    : __('Select a Court activity attached to this gathering.') ?>
                            </small>
                        </div>
                        <div class="col-12 col-lg-7">
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
                            <?= $this->Form->control('description', [
                                'type' => 'textarea',
                                'label' => __('Description'),
                                'class' => 'form-control',
                                'rows' => 3,
                                'placeholder' => __('e.g., "Round robin tourney with bring your best"'),
                            ]) ?>
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
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= __('Cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save" aria-hidden="true"></i> <?= __('Save Changes') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
