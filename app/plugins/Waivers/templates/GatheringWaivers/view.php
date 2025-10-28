<?php

/**
 * @var \App\View\AppView $this
 * @var \Waivers\Model\Entity\GatheringWaiver $gatheringWaiver
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("pageTitle");
echo __('Waiver Details');
$this->KMP->endBlock();

echo $this->KMP->startBlock("recordActions");
?>
<div class="btn-group" role="group">
    <?= $this->Html->link(
        '<i class="bi bi-download"></i> ' . __('Download'),
        ['action' => 'download', $gatheringWaiver->id],
        ['class' => 'btn btn-success', 'escape' => false]
    ) ?>
    <?= $this->Html->link(
        '<i class="bi bi-arrow-left"></i> ' . __('Back to List'),
        ['action' => 'index', '?' => ['gathering_id' => $gatheringWaiver->gathering_id]],
        ['class' => 'btn btn-secondary', 'escape' => false]
    ) ?>
    <?php
    $user = $this->getRequest()->getAttribute('identity');
    if ($user && $user->checkCan('canChangeWaiverType', $gatheringWaiver)): ?>
        <button type="button"
            class="btn btn-warning"
            data-bs-toggle="modal"
            data-bs-target="#changeTypeActivitiesModal">
            <i class="bi bi-pencil-square"></i> <?= __('Change Type/Activities') ?>
        </button>
    <?php endif; ?>
    <?php
    // Show decline button if user can decline and waiver can be declined
    if ($user && $user->checkCan('canDecline', $gatheringWaiver) && $gatheringWaiver->can_be_declined): ?>
        <button type="button"
            class="btn btn-danger"
            data-bs-toggle="modal"
            data-bs-target="#declineWaiverModal">
            <i class="bi bi-x-circle-fill"></i> <?= __('Decline Waiver') ?>
        </button>
    <?php endif; ?>
    <?php if ($gatheringWaiver->status === 'expired'): ?>
        <?= $this->Form->postLink(
            '<i class="bi bi-trash-fill"></i> ' . __('Delete'),
            ['action' => 'delete', $gatheringWaiver->id],
            [
                'confirm' => __('Are you sure you want to delete this expired waiver?'),
                'class' => 'btn btn-danger',
                'escape' => false
            ]
        ) ?>
    <?php endif; ?>
</div>
<?php
$this->KMP->endBlock();

echo $this->KMP->startBlock("recordDetails");
?>

<div class="row">
    <div class="col-md-8">
        <!-- Gathering Information -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> <?= __('Gathering Information') ?></h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3"><?= __('Gathering') ?></dt>
                    <dd class="col-sm-9">
                        <?= $this->Html->link(
                            h($gatheringWaiver->gathering->name),
                            ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gatheringWaiver->gathering->id]
                        ) ?>
                    </dd>

                    <dt class="col-sm-3"><?= __('Gathering Type') ?></dt>
                    <dd class="col-sm-9"><?= h($gatheringWaiver->gathering->gathering_type->name) ?></dd>

                    <dt class="col-sm-3"><?= __('Branch') ?></dt>
                    <dd class="col-sm-9"><?= h($gatheringWaiver->gathering->branch->name) ?></dd>

                    <dt class="col-sm-3"><?= __('Gathering Dates') ?></dt>
                    <dd class="col-sm-9">
                        <?= h($gatheringWaiver->gathering->start_date->format('M d, Y')) ?>
                        to
                        <?= h($gatheringWaiver->gathering->end_date->format('M d, Y')) ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Waiver Information -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-text"></i> <?= __('Waiver Information') ?></h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3"><?= __('Waiver Type') ?></dt>
                    <dd class="col-sm-9">
                        <strong><?= h($gatheringWaiver->waiver_type->name) ?></strong>
                        <?php if ($gatheringWaiver->waiver_type->description): ?>
                            <br>
                            <small class="text-muted"><?= h($gatheringWaiver->waiver_type->description) ?></small>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-3"><?= __('Status') ?></dt>
                    <dd class="col-sm-9">
                        <?php if ($gatheringWaiver->is_declined): ?>
                            <span class="badge bg-danger"><?= __('Declined') ?></span>
                        <?php elseif ($gatheringWaiver->status === 'active'): ?>
                            <span class="badge bg-success"><?= __('Active') ?></span>
                        <?php elseif ($gatheringWaiver->status === 'expired'): ?>
                            <span class="badge bg-danger"><?= __('Expired') ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= h($gatheringWaiver->status) ?></span>
                        <?php endif; ?>
                    </dd>

                    <?php if ($gatheringWaiver->is_declined): ?>
                        <dt class="col-sm-3"><?= __('Declined At') ?></dt>
                        <dd class="col-sm-9">
                            <?= h($gatheringWaiver->declined_at->format('F d, Y g:i A')) ?>
                        </dd>

                        <?php if (!empty($gatheringWaiver->declined_by_member)): ?>
                            <dt class="col-sm-3"><?= __('Declined By') ?></dt>
                            <dd class="col-sm-9">
                                <?php
                                $declinedByName = $gatheringWaiver->declined_by_member->SCA_name
                                    ?? $gatheringWaiver->declined_by_member->modern_name
                                    ?? 'Unknown';
                                ?>
                                <?= $this->Html->link(
                                    h($declinedByName),
                                    ['plugin' => false, 'controller' => 'Members', 'action' => 'view', $gatheringWaiver->declined_by]
                                ) ?>
                            </dd>
                        <?php elseif (!empty($gatheringWaiver->declined_by)): ?>
                            <dt class="col-sm-3"><?= __('Declined By') ?></dt>
                            <dd class="col-sm-9">
                                <?= __('Member ID: {0}', h($gatheringWaiver->declined_by)) ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ($gatheringWaiver->decline_reason): ?>
                            <dt class="col-sm-3"><?= __('Decline Reason') ?></dt>
                            <dd class="col-sm-9">
                                <div class="alert alert-danger mb-0">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <?= nl2br(h($gatheringWaiver->decline_reason)) ?>
                                </div>
                            </dd>
                        <?php endif; ?>
                    <?php endif; ?>

                    <dt class="col-sm-3"><?= __('Uploaded') ?></dt>
                    <dd class="col-sm-9">
                        <?= h($gatheringWaiver->created->format('F d, Y g:i A')) ?>
                    </dd>

                    <?php if ($gatheringWaiver->notes): ?>
                        <dt class="col-sm-3"><?= __('Notes') ?></dt>
                        <dd class="col-sm-9"><?= h($gatheringWaiver->notes) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Document Information -->
        <?php if ($gatheringWaiver->document): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-pdf"></i> <?= __('Document Information') ?></h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3"><?= __('File Type') ?></dt>
                        <dd class="col-sm-9"><?= h($gatheringWaiver->document->mime_type) ?></dd>

                        <dt class="col-sm-3"><?= __('File Size') ?></dt>
                        <dd class="col-sm-9">
                            <?= $this->Number->toReadableSize($gatheringWaiver->document->file_size) ?>
                        </dd>

                        <?php if ($gatheringWaiver->document->metadata): ?>
                            <?php $metadata = json_decode($gatheringWaiver->document->metadata, true); ?>
                            <?php if ($metadata && !empty($metadata['is_multipage']) && isset($metadata['page_count'])): ?>
                                <dt class="col-sm-3"><?= __('Pages') ?></dt>
                                <dd class="col-sm-9">
                                    <span class="badge bg-info">
                                        <i class="bi bi-file-earmark-text"></i> <?= h($metadata['page_count']) ?> <?= __('pages') ?>
                                    </span>
                                </dd>
                            <?php endif; ?>
                        <?php endif; ?>

                        <dt class="col-sm-3"><?= __('Storage') ?></dt>
                        <dd class="col-sm-9"><?= h($gatheringWaiver->document->storage_adapter) ?></dd>

                        <?php if ($gatheringWaiver->document->metadata): ?>
                            <?php $metadata = json_decode($gatheringWaiver->document->metadata, true); ?>
                            <?php if ($metadata && isset($metadata['original_filename'])): ?>
                                <dt class="col-sm-3"><?= __('Original Filename') ?></dt>
                                <dd class="col-sm-9"><?= h($metadata['original_filename']) ?></dd>
                            <?php endif; ?>
                            <?php if ($metadata && isset($metadata['original_size'])): ?>
                                <dt class="col-sm-3"><?= __('Original Size') ?></dt>
                                <dd class="col-sm-9">
                                    <?= $this->Number->toReadableSize($metadata['original_size']) ?>
                                </dd>
                            <?php endif; ?>
                            <?php if ($metadata && isset($metadata['compression_ratio'])): ?>
                                <dt class="col-sm-3"><?= __('Space Saved') ?></dt>
                                <dd class="col-sm-9">
                                    <span class="badge bg-success"><?= h($metadata['compression_ratio']) ?>%</span>
                                    <small class="text-muted">
                                        <?= __('(through compression)') ?>
                                    </small>
                                </dd>
                            <?php endif; ?>
                        <?php endif; ?>

                        <dt class="col-sm-3"><?= __('Checksum') ?></dt>
                        <dd class="col-sm-9">
                            <code class="small"><?= h(substr($gatheringWaiver->document->checksum, 0, 16)) ?>...</code>
                        </dd>
                    </dl>
                </div>
            </div>
        <?php endif; ?>

        <!-- Activity Associations -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-activity"></i> <?= __('Associated Activities') ?>
                    <?php if (!empty($gatheringWaiver->gathering_waiver_activities)): ?>
                        <span class="badge bg-primary ms-2">
                            <?= count($gatheringWaiver->gathering_waiver_activities) ?>
                        </span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($gatheringWaiver->gathering_waiver_activities)): ?>
                    <p class="text-muted mb-3">
                        <i class="bi bi-info-circle"></i>
                        <?= __('This waiver applies to the following activities:') ?>
                    </p>
                    <ul class="list-group">
                        <?php foreach ($gatheringWaiver->gathering_waiver_activities as $activityWaiver): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= h($activityWaiver->gathering_activity->name) ?></strong>
                                    <?php if ($activityWaiver->gathering_activity->description): ?>
                                        <br>
                                        <small class="text-muted"><?= h($activityWaiver->gathering_activity->description) ?></small>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-success rounded-pill">
                                    <i class="bi bi-check-circle"></i> <?= __('Covered') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-info mb-0" role="alert">
                        <i class="bi bi-info-circle"></i>
                        <?= __('This waiver is not associated with any specific activities. It may apply to the gathering as a whole.') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Retention Policy Information -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> <?= __('Retention Policy') ?></h5>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong><?= __('Retention Date:') ?></strong><br>
                    <span class="fs-4"><?= h($gatheringWaiver->retention_date->format('F d, Y')) ?></span>
                </p>

                <?php
                $today = new \Cake\I18n\Date();
                $daysRemaining = $today->diffInDays($gatheringWaiver->retention_date, false);
                ?>

                <?php if ($daysRemaining < 0): ?>
                    <div class="alert alert-danger mt-3 mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong><?= __('Expired') ?></strong><br>
                        <?= __('This waiver expired {0} days ago and is eligible for deletion.', abs($daysRemaining)) ?>
                    </div>
                <?php elseif ($daysRemaining < 90): ?>
                    <div class="alert alert-warning mt-3 mb-0" role="alert">
                        <i class="bi bi-clock"></i>
                        <strong><?= __('Expiring Soon') ?></strong><br>
                        <?= __('This waiver will expire in {0} days.', $daysRemaining) ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mt-3 mb-0" role="alert">
                        <i class="bi bi-check-circle"></i>
                        <strong><?= __('Active') ?></strong><br>
                        <?= __('This waiver will expire in {0} days.', $daysRemaining) ?>
                    </div>
                <?php endif; ?>

                <hr>

                <p class="small text-muted mb-0">
                    <i class="bi bi-info-circle"></i>
                    <?= __('Retention policies are captured at upload time and remain fixed even if the waiver type policy changes.') ?>
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= __('Quick Actions') ?></h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="<?= $this->Url->build(['action' => 'download', $gatheringWaiver->id]) ?>" class="list-group-item list-group-item-action">
                    <i class="bi bi-download"></i> <?= __('Download PDF') ?>
                </a>
                <a href="<?= $this->Url->build(['action' => 'index', '?' => ['gathering_id' => $gatheringWaiver->gathering_id]]) ?>" class="list-group-item list-group-item-action">
                    <i class="bi bi-list-ul"></i> <?= __('View All Waivers for This Gathering') ?>
                </a>
                <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gatheringWaiver->gathering_id]) ?>" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar-event"></i> <?= __('View Gathering Details') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Audit Notes Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-journal-text"></i> <?= __('Audit History & Notes') ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Check if user can add notes
                $user = $this->getRequest()->getAttribute('identity');
                $canAddNote = $user && $user->checkCan('canChangeWaiverType', $gatheringWaiver);

                echo $this->cell('Notes', [
                    $gatheringWaiver->id,
                    'Waivers.GatheringWaivers',
                    false,  // Don't show private notes
                    $canAddNote  // Can create notes if has changeWaiverType permission
                ]);
                ?>
            </div>
        </div>
    </div>
</div>

<?php
$this->KMP->endBlock();

// Include the change type/activities modal
$user = $this->getRequest()->getAttribute('identity');
if ($user && $user->checkCan('canChangeWaiverType', $gatheringWaiver)) {
    echo $this->element('Waivers.GatheringWaivers/changeTypeActivitiesModal', [
        'gatheringWaiver' => $gatheringWaiver,
        'waiverTypes' => $waiverTypes,
        'gatheringActivities' => $gatheringActivities,
    ]);
}

// Include the decline waiver modal
if ($user && $user->checkCan('canDecline', $gatheringWaiver) && $gatheringWaiver->can_be_declined): ?>
    <!-- Decline Waiver Modal -->
    <div class="modal fade" id="declineWaiverModal" tabindex="-1" aria-labelledby="declineWaiverModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <?= $this->Form->create(null, [
                    'url' => ['action' => 'decline', $gatheringWaiver->id],
                    'type' => 'post'
                ]) ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="declineWaiverModalLabel">
                        <i class="bi bi-x-circle-fill"></i> <?= __('Decline Waiver') ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong><?= __('Warning:') ?></strong>
                        <?= __('You are about to decline this waiver. This action cannot be undone.') ?>
                    </div>

                    <div class="mb-3">
                        <p><strong><?= __('Waiver Details:') ?></strong></p>
                        <ul>
                            <li><?= __('Type: {0}', h($gatheringWaiver->waiver_type->name)) ?></li>
                            <li><?= __('Uploaded: {0}', h($gatheringWaiver->created->format('M d, Y'))) ?></li>
                            <li><?= __('Gathering: {0}', h($gatheringWaiver->gathering->name)) ?></li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <?= $this->Form->control('decline_reason', [
                            'label' => __('Reason for Declining (Required)'),
                            'type' => 'textarea',
                            'rows' => 4,
                            'required' => true,
                            'class' => 'form-control',
                            'placeholder' => __('Please provide a detailed reason why this waiver is being declined...')
                        ]) ?>
                        <small class="form-text text-muted">
                            <i class="bi bi-info-circle"></i>
                            <?= __('This reason will be visible to users who can view this waiver.') ?>
                        </small>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-clock"></i>
                        <strong><?= __('Note:') ?></strong>
                        <?= __(
                            'Waivers can only be declined within 30 days of upload. This waiver was uploaded {0}.',
                            $gatheringWaiver->created->timeAgoInWords()
                        ) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> <?= __('Cancel') ?>
                    </button>
                    <?= $this->Form->button(
                        '<i class="bi bi-x-circle-fill"></i> ' . __('Decline Waiver'),
                        [
                            'type' => 'submit',
                            'class' => 'btn btn-danger',
                            'escape' => false
                        ]
                    ) ?>
                </div>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
<?php endif;
?>