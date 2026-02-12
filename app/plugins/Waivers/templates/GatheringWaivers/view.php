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
    <?php if (!$gatheringWaiver->is_exemption && $gatheringWaiver->document_id): ?>
    <?= $this->Html->link(
            '<i class="bi bi-eye"></i> ' . __('View PDF'),
            ['action' => 'inlinePdf', $gatheringWaiver->id],
            ['class' => 'btn btn-primary', 'escape' => false, 'target' => '_blank', 'rel' => 'noopener']
        ) ?>
    <?= $this->Html->link(
            '<i class="bi bi-download"></i> ' . __('Download'),
            ['action' => 'download', $gatheringWaiver->id],
            ['class' => 'btn btn-success', 'escape' => false]
        ) ?>
    <?php endif; ?>
    <?php
    $user = $this->getRequest()->getAttribute('identity');
    // Only show change type/activities for actual waivers, not exemptions
    if (!$gatheringWaiver->is_exemption && $user && $user->checkCan('changeWaiverType', $gatheringWaiver)): ?>
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#changeTypeActivitiesModal">
        <i class="bi bi-pencil-square"></i> <?= __('Change') ?>
    </button>
    <?php endif; ?>
    <?php
    // Show decline button if user can decline and waiver can be declined
    if ($user && $user->checkCan('decline', $gatheringWaiver) && $gatheringWaiver->can_be_declined): ?>
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#declineWaiverModal">
        <i class="bi bi-x-circle-fill"></i> <?= __('Decline') ?>
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
                            ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gatheringWaiver->gathering->public_id]
                        ) ?>
                    </dd>

                    <dt class="col-sm-3"><?= __('Gathering Type') ?></dt>
                    <dd class="col-sm-9"><?= h($gatheringWaiver->gathering->gathering_type->name) ?></dd>

                    <dt class="col-sm-3"><?= __('Branch') ?></dt>
                    <dd class="col-sm-9"><?= h($gatheringWaiver->gathering->branch->name) ?></dd>

                    <dt class="col-sm-3"><?= __('Gathering Dates') ?></dt>
                    <dd class="col-sm-9">
                        <?= $this->Timezone->format($gatheringWaiver->gathering->start_date, $gatheringWaiver->gathering, 'M d, Y') ?>
                        to
                        <?= $this->Timezone->format($gatheringWaiver->gathering->end_date, $gatheringWaiver->gathering, 'M d, Y') ?>
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

                    <dt class="col-sm-3"><?= __('Exemption') ?></dt>
                    <dd class="col-sm-9">
                        <?php if ($gatheringWaiver->is_exemption): ?>
                        <span class="badge bg-info text-dark"><i class="bi bi-shield-check"></i>
                            <?= __('Waiver Exempted') ?></span>
                        <?php else: ?>
                        <span class="text-muted"><?= __('No') ?></span>
                        <?php endif; ?>
                    </dd>

                    <?php if ($gatheringWaiver->is_declined): ?>
                    <dt class="col-sm-3"><?= __('Declined At') ?></dt>
                    <dd class="col-sm-9">
                        <?= $this->Timezone->format($gatheringWaiver->declined_at, null, null, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT) ?>
                    </dd>

                    <?php if (!empty($gatheringWaiver->declined_by_member)): ?>
                    <dt class="col-sm-3"><?= __('Declined By') ?></dt>
                    <dd class="col-sm-9">
                        <?php
                                $declinedByName = $gatheringWaiver->declined_by_member->sca_name
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

                    <?php if ($gatheringWaiver->is_exemption): ?>
                    <dt class="col-sm-3"><?= __('Exemption Reason') ?></dt>
                    <dd class="col-sm-9">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle-fill"></i>
                            <?php if ($gatheringWaiver->exemption_reason): ?>
                            <?= nl2br(h($gatheringWaiver->exemption_reason)) ?>
                            <?php else: ?>
                            <em><?= __('No reason provided.') ?></em>
                            <?php endif; ?>
                        </div>
                    </dd>
                    <?php endif; ?>

                    <dt class="col-sm-3"><?= __('Submitted') ?></dt>
                    <dd class="col-sm-9">
                        <?= $this->Timezone->format($gatheringWaiver->created, null, null, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT) ?>
                        <?php if (!empty($gatheringWaiver->created_by_member)): ?>
                        by
                        <?php
                            $createdByName = $gatheringWaiver->created_by_member->sca_name
                                ?? 'Unknown';
                            ?>
                        <?= $this->Html->link(
                                h($createdByName),
                                ['plugin' => false, 'controller' => 'Members', 'action' => 'view', $gatheringWaiver->created_by]
                            ) ?>
                        <?php elseif (!empty($gatheringWaiver->created_by)): ?>
                        by <?= __('Member ID: {0}', h($gatheringWaiver->created_by)) ?>
                        <?php endif; ?>
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

                <?php if (!empty($previewAvailable)): ?>
                <hr>
                <?php
                        $cacheValue = null;
                        if ($gatheringWaiver->document->modified instanceof \DateTimeInterface) {
                            $cacheValue = $gatheringWaiver->document->modified->format('U');
                        } elseif ($gatheringWaiver->document->created instanceof \DateTimeInterface) {
                            $cacheValue = $gatheringWaiver->document->created->format('U');
                        }

                        $previewUrlParams = ['action' => 'preview', $gatheringWaiver->id];
                        if ($cacheValue !== null) {
                            $previewUrlParams['?'] = ['v' => $cacheValue];
                        }
                        $previewUrl = $this->Url->build($previewUrlParams); ?>

                <div class="text-center">
                    <h6 class="text-muted mb-3">
                        <i class="bi bi-image"></i> <?= __('Waiver Preview') ?>
                    </h6>
                    <img src="<?= h($previewUrl) ?>" class="img-fluid border rounded shadow-sm"
                        alt="<?= h(__('Preview of waiver document first page')) ?>" loading="lazy" />
                    <p class="small text-muted mt-2 mb-0">
                        <i class="bi bi-eye"></i>
                        <?= __('Displaying the first page for quick review. Use View PDF to open embedded PDF Viewer for the complete document.') ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

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
                    <span
                        class="fs-4"><?= $this->Timezone->format($gatheringWaiver->retention_date, null, null, \IntlDateFormatter::LONG) ?></span>
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
                <?php if (!$gatheringWaiver->is_exemption && $gatheringWaiver->document_id): ?>
                <a href="<?= $this->Url->build(['action' => 'inlinePdf', $gatheringWaiver->id]) ?>" target="_blank"
                    rel="noopener" class="list-group-item list-group-item-action">
                    <i class="bi bi-eye"></i> <?= __('Open Embedded PDF Viewer') ?>
                </a>
                <a href="<?= $this->Url->build(['action' => 'download', $gatheringWaiver->id]) ?>"
                    class="list-group-item list-group-item-action">
                    <i class="bi bi-download"></i> <?= __('Download PDF') ?>
                </a>
                <?php endif; ?>
                <a href="<?= $this->Url->build(['action' => 'index', '?' => ['gathering_id' => $gatheringWaiver->gathering_id]]) ?>"
                    class="list-group-item list-group-item-action">
                    <i class="bi bi-list-ul"></i> <?= __('View All Waivers for This Gathering') ?>
                </a>
                <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gatheringWaiver->gathering->public_id]) ?>"
                    class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar-event"></i> <?= __('View Gathering Details') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$this->KMP->endBlock();

// Include the change type/activities modal
$user = $this->getRequest()->getAttribute('identity');
if ($user && $user->checkCan('changeWaiverType', $gatheringWaiver)) {
    echo $this->element('Waivers.GatheringWaivers/changeTypeActivitiesModal', [
        'gatheringWaiver' => $gatheringWaiver,
        'waiverTypes' => $waiverTypes,
    ]);
}

// Include the decline waiver modal
if ($user && $user->checkCan('decline', $gatheringWaiver) && $gatheringWaiver->can_be_declined): ?>
<!-- Decline Waiver Modal -->
<div class="modal fade" id="declineWaiverModal" tabindex="-1" aria-labelledby="declineWaiverModalLabel"
    aria-hidden="true">
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
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
                        <li><?= __('Uploaded: {0}', $this->Timezone->format($gatheringWaiver->created, null, 'M d, Y')) ?>
                        </li>
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
                        'Decline Waiver',
                        [
                            'type' => 'submit',
                            'class' => 'btn btn-danger bi bi-x-circle-fill',
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