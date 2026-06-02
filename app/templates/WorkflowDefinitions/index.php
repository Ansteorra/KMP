<?php

/**
 * Workflow Definitions Index
 *
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\WorkflowDefinition> $workflows
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Workflows';
$this->KMP->endBlock();

$this->assign('title', __('Workflows'));

$csrfToken = $this->request->getAttribute('csrfToken');
$toggleUrl = $this->Url->build(['action' => 'toggleActive', '__id__']);

?>

<div class="workflows index content"
    data-controller="workflow-index"
    data-workflow-index-toggle-url-value="<?= h($toggleUrl) ?>"
    data-workflow-index-csrf-value="<?= h($csrfToken) ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Workflow Definitions') ?></h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i>' . __('New Workflow'),
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false],
            ) ?>
        </div>
    </div>

    <!-- Search/Filter -->
    <div class="mb-3">
        <label class="form-label" for="workflow-definition-search"><?= __('Search workflows') ?></label>
        <input type="text" class="form-control"
            id="workflow-definition-search"
            data-workflow-index-target="search"
            data-action="input->workflow-index#filter"
            placeholder="<?= __('Search workflows by name, slug, or trigger...') ?>">
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?= __('Name') ?></th>
                    <th><?= __('Slug') ?></th>
                    <th><?= __('Active') ?></th>
                    <th><?= __('Mode') ?></th>
                    <th><?= __('Version') ?></th>
                    <th><?= __('Trigger') ?></th>
                    <th><?= __('Entity Type') ?></th>
                    <th class="text-end"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody data-workflow-index-target="body">
                <?php foreach ($workflows as $workflow) : ?>
                    <?php
                    $instanceCount = (int)$workflow->get('instance_count');
                    $searchText = strtolower(implode(' ', [
                        $workflow->name,
                        $workflow->slug,
                        $workflow->trigger_type,
                        $workflow->entity_type ?? '',
                        $workflow->execution_mode ?? '',
                    ]));
                    ?>
                <tr data-search-text="<?= h($searchText) ?>">
                    <td><strong><?= h($workflow->name) ?></strong></td>
                    <td><code><?= h($workflow->slug) ?></code></td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                id="toggle-active-<?= h($workflow->id) ?>"
                                data-action="change->workflow-index#toggleActive"
                                data-workflow-id="<?= h($workflow->id) ?>"
                                <?= $workflow->is_active ? 'checked' : '' ?>>
                            <label class="form-check-label visually-hidden" for="toggle-active-<?= h($workflow->id) ?>">
                                <?= __('Toggle active for {0}', h($workflow->name)) ?>
                            </label>
                        </div>
                    </td>
                    <td>
                        <?php if (($workflow->execution_mode ?? 'durable') === 'ephemeral') : ?>
                            <span class="badge bg-info text-dark"><?= __('Ephemeral') ?></span>
                        <?php else : ?>
                            <span class="badge bg-primary"><?= __('Durable') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($workflow->current_version) : ?>
                            v<?= h($workflow->current_version->version_number) ?>
                            <?= $this->KMP->workflowStatusBadge($workflow->current_version->status) ?>
                        <?php else : ?>
                            <span class="badge bg-light text-dark"><?= __('No version') ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($workflow->trigger_type) ?></td>
                    <td><?= h($workflow->entity_type) ?: '—' ?></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?= $this->Html->link(
                                '<i class="bi bi-pencil-square" aria-hidden="true"></i>',
                                ['action' => 'designer', $workflow->id],
                                [
                                    'class' => 'btn btn-primary',
                                    'escape' => false,
                                    'title' => __('Design workflow'),
                                    'aria-label' => __('Design workflow {0}', $workflow->name),
                                ],
                            ) ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-play-circle" aria-hidden="true"></i>',
                                ['controller' => 'WorkflowInstances', 'action' => 'instances', $workflow->id],
                                [
                                    'class' => 'btn btn-info',
                                    'escape' => false,
                                    'title' => __('View instances'),
                                    'aria-label' => __('View instances for workflow {0}', $workflow->name),
                                ],
                            ) ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-clock-history" aria-hidden="true"></i>',
                                ['action' => 'versions', $workflow->id],
                                [
                                    'class' => 'btn btn-secondary',
                                    'escape' => false,
                                    'title' => __('View versions'),
                                    'aria-label' => __('View versions for workflow {0}', $workflow->name),
                                ],
                            ) ?>
                            <?php if ($instanceCount > 0) : ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-archive" aria-hidden="true"></i>',
                                    ['action' => 'archive', $workflow->id],
                                    [
                                        'class' => 'btn btn-warning',
                                        'escape' => false,
                                        'title' => __('Archive workflow'),
                                        'aria-label' => __('Archive workflow {0}', $workflow->name),
                                        'confirm' => __(
                                            'Archive workflow "{0}"? It will be deactivated and hidden, '
                                            . 'but run history will be preserved.',
                                            $workflow->name,
                                        ),
                                    ],
                                ) ?>
                            <?php else : ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-trash" aria-hidden="true"></i>',
                                    ['action' => 'delete', $workflow->id],
                                    [
                                        'class' => 'btn btn-danger',
                                        'escape' => false,
                                        'title' => __('Delete workflow'),
                                        'aria-label' => __('Delete workflow {0}', $workflow->name),
                                        'confirm' => __(
                                            'Delete workflow "{0}"? This removes the unused workflow '
                                            . 'and its draft/published versions.',
                                            $workflow->name,
                                        ),
                                    ],
                                ) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($workflows) || $workflows->count() === 0) : ?>
                <tr id="wf-empty-row">
                    <td colspan="8" class="text-center text-muted py-4">
                        <?= __('No workflow definitions found.') ?>
                        <?= $this->Html->link(__('Create one'), ['action' => 'add']) ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
