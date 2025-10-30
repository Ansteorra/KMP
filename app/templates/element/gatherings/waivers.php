<?php

/**
 * Gathering Waivers Element
 * 
 * Displays waiver information and upload interface for a gathering.
 * This element is embedded in the Gathering view as a tab.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $waiverStats Waiver counts by type
 * @var array $requiredWaiverTypes Required waiver types for this gathering
 * @var bool $hasWaivers Whether any waivers have been uploaded
 * @var \App\Model\Entity\User $user Current user
 */
?>

<div class="gathering-waivers">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><?= __('Gathering Waivers') ?></h5>
        <?php if ($user->checkCan('edit', $gathering) && !empty($requiredWaiverTypes)): ?>
            <?= $this->Html->link(
                '<i class="bi bi-cloud-upload"></i> ' . __('Upload Waivers'),
                [
                    'plugin' => 'Waivers',
                    'controller' => 'GatheringWaivers',
                    'action' => 'upload',
                    '?' => ['gathering_id' => $gathering->id]
                ],
                [
                    'class' => 'btn btn-sm btn-primary',
                    'escape' => false
                ]
            ) ?>
        <?php endif; ?>
    </div>

    <?php if (empty($requiredWaiverTypes)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <?= __('No waiver requirements are set for this gathering. Add activities that require waivers to enable waiver collection.') ?>
        </div>
    <?php else: ?>

        <!-- Waiver Statistics -->
        <div class="row mb-4">
            <?php foreach ($requiredWaiverTypes as $waiverType): ?>
                <?php
                $count = $waiverStats[$waiverType->id] ?? 0;
                $badgeClass = $count > 0 ? 'bg-success' : 'bg-warning';
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">
                                <?= h($waiverType->name) ?>
                                <span class="badge <?= $badgeClass ?> float-end"><?= $count ?></span>
                            </h6>
                            <?php if (!empty($waiverType->description)): ?>
                                <p class="card-text text-muted small"><?= h($waiverType->description) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($hasWaivers): ?>
            <!-- Waiver List Link -->
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><?= __('View Uploaded Waivers') ?></h6>
                    <p class="card-text">
                        <?= __('View, download, and manage the {0} waiver(s) uploaded for this gathering.', array_sum($waiverStats)) ?>
                    </p>
                    <?= $this->Html->link(
                        '<i class="bi bi-list-ul"></i> ' . __('View All Waivers'),
                        [
                            'plugin' => 'Waivers',
                            'controller' => 'GatheringWaivers',
                            'action' => 'index',
                            '?' => ['gathering_id' => $gathering->id]
                        ],
                        [
                            'class' => 'btn btn-secondary btn-sm',
                            'escape' => false
                        ]
                    ) ?>
                </div>
            </div>

            <!-- Gathering Status Info -->
            <div class="alert alert-success mt-3">
                <i class="bi bi-check-circle"></i>
                <?php if ($gathering->waivers_collected): ?>
                    <?= __('Waivers have been collected and uploaded for this gathering. Activities are now locked.') ?>
                <?php else: ?>
                    <?= __('Waivers are being uploaded. Mark as collected when complete.') ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- No Waivers Uploaded Yet -->
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <?= __('No waivers have been uploaded yet. Use the "Upload Waivers" button above to begin.') ?>
            </div>

            <!-- Instructions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><?= __('Upload Instructions') ?></h6>
                </div>
                <div class="card-body">
                    <ol>
                        <li><?= __('Click "Upload Waivers" above') ?></li>
                        <li><?= __('Use your mobile device camera or select files from your device') ?></li>
                        <li><?= __('Images will be automatically converted to PDF format') ?></li>
                        <li><?= __('Waivers are organized by type and stored securely') ?></li>
                        <li><?= __('Retention policies are automatically calculated based on the gathering end date') ?></li>
                    </ol>

                    <?php if ($user->checkCan('edit', $gathering)): ?>
                        <div class="mt-3">
                            <strong><?= __('Important:') ?></strong>
                            <?= __('Once waivers are uploaded and marked as collected, activities cannot be modified to prevent data inconsistencies.') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>