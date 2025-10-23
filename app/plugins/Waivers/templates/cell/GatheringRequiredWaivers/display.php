<?php

/**
 * @var \App\View\AppView $this
 * @var int $gatheringId
 * @var array $aggregatedWaivers
 * @var bool $isEmpty
 * @var int $totalWaivers
 * @var int $totalActivities
 */

$user = $this->getRequest()->getAttribute('identity');
?>

<div class="gathering-required-waivers p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>
            <?= __('Required Waivers for this Gathering') ?>
            <?php if (!$isEmpty): ?>
                <span class="badge bg-info"><?= $totalWaivers ?></span>
            <?php endif; ?>
        </h5>
    </div>

    <?php if ($isEmpty): ?>
        <div class="alert alert-secondary">
            <i class="bi bi-info-circle"></i>
            <?= __('No waiver requirements have been configured for this gathering\'s activities yet.') ?>
            <br>
            <small class="text-muted">
                <?= __('Waiver requirements are configured at the activity level. Visit each activity to add waiver requirements.') ?>
            </small>
        </div>
    <?php else: ?>
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle"></i>
            <?= __(
                'This gathering requires {0} unique waiver type(s) across {1} activity/activities.',
                $totalWaivers,
                $totalActivities
            ) ?>
            <br>
            <small class="text-muted">
                <?= __('Waiver requirements are managed at the activity level. Click on an activity below to manage its waiver requirements.') ?>
            </small>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th><?= __('Waiver Type') ?></th>
                        <th><?= __('Description') ?></th>
                        <th><?= __('Required By Activities') ?></th>
                        <th><?= __('Retention Period') ?></th>
                        <th><?= __('Template') ?></th>
                        <th><?= __('Status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aggregatedWaivers as $waiverData): ?>
                        <?php $waiverType = $waiverData['waiver_type']; ?>
                        <?php $activities = $waiverData['activities']; ?>
                        <tr>
                            <td>
                                <?php if ($user && $user->checkCan('view', 'Waivers.WaiverTypes')): ?>
                                    <?= $this->Html->link(
                                        h($waiverType->name),
                                        ['controller' => 'WaiverTypes', 'action' => 'view', $waiverType->id, 'plugin' => 'Waivers'],
                                        ['class' => 'fw-bold']
                                    ) ?>
                                <?php else: ?>
                                    <strong><?= h($waiverType->name) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($waiverType->description)): ?>
                                    <small class="text-muted"><?= h($waiverType->description) ?></small>
                                <?php else: ?>
                                    <small class="text-muted fst-italic"><?= __('No description') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($activities as $activity): ?>
                                        <?= $this->Html->link(
                                            h($activity->name),
                                            ['controller' => 'GatheringActivities', 'action' => 'view', $activity->id, 'plugin' => null],
                                            ['class' => 'badge bg-secondary text-decoration-none', 'title' => __('View activity')]
                                        ) ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($waiverType->retention_description): ?>
                                    <?= h($waiverType->retention_description) ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= __('Not set') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($waiverType->template_path)): ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-box-arrow-up-right"></i> ' . __('View Template'),
                                        $waiverType->template_path,
                                        [
                                            'escape' => false,
                                            'class' => 'btn btn-sm btn-outline-primary',
                                            'target' => '_blank',
                                            'rel' => 'noopener noreferrer'
                                        ]
                                    ) ?>
                                <?php elseif (!empty($waiverType->document_id)): ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-file-earmark-text"></i> ' . __('Download'),
                                        ['controller' => 'WaiverTypes', 'action' => 'downloadTemplate', $waiverType->id, 'plugin' => 'Waivers'],
                                        ['escape' => false, 'class' => 'btn btn-sm btn-outline-primary']
                                    ) ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= __('No template') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($waiverType->is_active): ?>
                                    <span class="badge bg-success"><?= __('Active') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= __('Inactive') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-lightbulb"></i> <?= __('Managing Waiver Requirements') ?>
                    </h6>
                    <p class="card-text mb-0">
                        <?= __('To add or remove waiver requirements for this gathering:') ?>
                    </p>
                    <ol class="mb-0">
                        <li><?= __('Navigate to the specific activity from the Activities tab') ?></li>
                        <li><?= __('Go to the "Required Waivers" tab on the activity view') ?></li>
                        <li><?= __('Use the "Add Waiver Requirement" button to configure requirements') ?></li>
                    </ol>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>