<?php

/**
 * @var \App\View\AppView $this
 * @var int $gatheringActivityId
 * @var \Cake\ORM\ResultSet $waiverRequirements
 * @var bool $isEmpty
 */
$user = $this->request->getAttribute("identity");
?>

<?php if ($user && $user->checkCan('add', 'Waivers.GatheringActivityWaivers')) : ?>
    <button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
        data-bs-target="#addWaiverRequirementModal">
        <?= __('Add Waiver Requirement') ?>
    </button>
<?php endif; ?>

<?php if (!$isEmpty) : ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?= __('Waiver Type') ?></th>
                    <th><?= __('Description') ?></th>
                    <th><?= __('Retention Period') ?></th>
                    <th><?= __('Template') ?></th>
                    <th><?= __('Status') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($waiverRequirements as $requirement) : ?>
                    <?php if (isset($requirement->waiver_type)) : ?>
                        <tr>
                            <td>
                                <?= $this->Html->link(
                                    h($requirement->waiver_type->name),
                                    [
                                        'controller' => 'WaiverTypes',
                                        'action' => 'view',
                                        'plugin' => 'Waivers',
                                        $requirement->waiver_type->id
                                    ]
                                ) ?>
                            </td>
                            <td><?= h($requirement->waiver_type->description) ?></td>
                            <td>
                                <?php if ($requirement->waiver_type->retention_description) : ?>
                                    <?= h($requirement->waiver_type->retention_description) ?>
                                <?php else : ?>
                                    <span class="text-muted"><?= __('Not set') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($requirement->waiver_type->template_path) : ?>
                                    <?php // External URL - link to external site 
                                    ?>
                                    <?= $this->Html->link(
                                        '<i class="fas fa-external-link-alt"></i> ' . __('View Template'),
                                        $requirement->waiver_type->template_path,
                                        [
                                            'escape' => false,
                                            'class' => 'btn btn-sm btn-outline-primary',
                                            'target' => '_blank',
                                            'rel' => 'noopener noreferrer'
                                        ]
                                    ) ?>
                                <?php elseif ($requirement->waiver_type->document_id) : ?>
                                    <?php // Internal document - download link 
                                    ?>
                                    <?= $this->Html->link(
                                        '<i class="fas fa-download"></i> ' . __('Download'),
                                        [
                                            'controller' => 'WaiverTypes',
                                            'action' => 'downloadTemplate',
                                            'plugin' => 'Waivers',
                                            $requirement->waiver_type->id
                                        ],
                                        [
                                            'escape' => false,
                                            'class' => 'btn btn-sm btn-outline-primary'
                                        ]
                                    ) ?>
                                <?php else : ?>
                                    <span class="text-muted"><?= __('No template') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($requirement->waiver_type->is_active) : ?>
                                    <span class="badge bg-success"><?= __('Active') ?></span>
                                <?php else : ?>
                                    <span class="badge bg-secondary"><?= __('Inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <?php if ($user && $user->checkCan('delete', $requirement)) : ?>
                                    <?= $this->Form->postLink(
                                        __('Remove'),
                                        [
                                            'controller' => 'GatheringActivityWaivers',
                                            'action' => 'delete',
                                            'plugin' => 'Waivers',
                                            $gatheringActivityId,
                                            $requirement->waiver_type_id
                                        ],
                                        [
                                            'confirm' => __('Are you sure you want to remove this waiver requirement?'),
                                            'class' => 'btn btn-danger btn-sm'
                                        ]
                                    ) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else : ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <?= __('No waiver requirements have been configured for this activity.') ?>
        <?php if ($user && $user->checkCan('add', 'Waivers.GatheringActivityWaivers')) : ?>
            <?= __('Click "Add Waiver Requirement" above to specify which waivers are required for participants.') ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
echo $this->KMP->startBlock("modals");
echo $this->element('addWaiverRequirementModal', [
    'gatheringActivityId' => $gatheringActivityId,
], ['plugin' => 'Waivers']);
$this->KMP->endBlock();
?>