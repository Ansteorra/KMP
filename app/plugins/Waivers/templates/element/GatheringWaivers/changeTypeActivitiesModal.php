<?php

/**
 * Change Waiver Type and Activities Modal
 *
 * Allows authorized users to change the waiver type and activity associations
 * for an already uploaded waiver.
 *
 * @var \App\View\AppView $this
 * @var \Waivers\Model\Entity\GatheringWaiver $gatheringWaiver
 * @var array $waiverTypes List of waiver types (id => name)
 * @var \Waivers\Model\Entity\GatheringActivity[] $gatheringActivities Gathering's activities
 */

// Get currently selected activity IDs
$currentActivityIds = [];
foreach ($gatheringWaiver->gathering_waiver_activities as $activityWaiver) {
    $currentActivityIds[] = $activityWaiver->gathering_activity_id;
}
?>

<!-- Change Type/Activities Modal -->
<div class="modal fade" id="changeTypeActivitiesModal" tabindex="-1" aria-labelledby="changeTypeActivitiesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTypeActivitiesModalLabel">
                    <i class="bi bi-pencil-square"></i> <?= __('Change Waiver Type and Activities') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <?= $this->Form->create($gatheringWaiver, [
                'url' => ['action' => 'changeTypeActivities', $gatheringWaiver->id],
                'id' => 'changeTypeActivitiesForm'
            ]) ?>

            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong><?= __('Warning:') ?></strong>
                    <?= __('Changing the waiver type or activity associations should be done carefully. This action affects which activities this waiver fulfills requirements for.') ?>
                </div>

                <!-- Waiver Type Selection -->
                <div class="mb-3">
                    <label for="waiver-type-select" class="form-label">
                        <i class="bi bi-file-earmark-text"></i> <?= __('Waiver Type') ?>
                        <span class="text-danger">*</span>
                    </label>
                    <?= $this->Form->control('waiver_type_id', [
                        'type' => 'select',
                        'options' => $waiverTypes,
                        'class' => 'form-select',
                        'id' => 'waiver-type-select',
                        'label' => false,
                        'required' => true,
                        'empty' => __('-- Select Waiver Type --')
                    ]) ?>
                    <div class="form-text">
                        <?= __('Select the type of waiver that this document represents.') ?>
                    </div>
                </div>

                <!-- Activity Selection -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="bi bi-activity"></i> <?= __('Activities Covered by This Waiver') ?>
                        <span class="text-danger">*</span>
                    </label>

                    <?php if (empty($gatheringActivities)): ?>
                        <div class="alert alert-info mb-0" role="alert">
                            <i class="bi bi-info-circle"></i>
                            <?= __('This gathering has no activities defined.') ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($gatheringActivities as $activity): ?>
                                    <div class="form-check mb-2">
                                        <?= $this->Form->checkbox('activity_ids[]', [
                                            'value' => $activity->id,
                                            'checked' => in_array($activity->id, $currentActivityIds),
                                            'id' => 'activity-' . $activity->id,
                                            'class' => 'form-check-input',
                                            'hiddenField' => false
                                        ]) ?>
                                        <label class="form-check-label" for="activity-<?= $activity->id ?>">
                                            <strong><?= h($activity->name) ?></strong>
                                            <?php if ($activity->description): ?>
                                                <br>
                                                <small class="text-muted"><?= h($activity->description) ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-text">
                            <?= __('Select all activities that this waiver document covers. At least one activity must be selected.') ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Current Associations Info -->
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title mb-2">
                            <i class="bi bi-info-circle"></i> <?= __('Current Settings') ?>
                        </h6>
                        <dl class="row mb-0">
                            <dt class="col-sm-4"><?= __('Current Type:') ?></dt>
                            <dd class="col-sm-8"><?= h($gatheringWaiver->waiver_type->name) ?></dd>

                            <dt class="col-sm-4"><?= __('Current Activities:') ?></dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($gatheringWaiver->gathering_waiver_activities)): ?>
                                    <ul class="mb-0">
                                        <?php foreach ($gatheringWaiver->gathering_waiver_activities as $activityWaiver): ?>
                                            <li><?= h($activityWaiver->gathering_activity->name) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <em class="text-muted"><?= __('None') ?></em>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> <?= __('Cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> <?= __('Save Changes') ?>
                </button>
            </div>

            <?= $this->Form->end() ?>
        </div>
    </div>
</div>