<?php

/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Gathering> $gatherings
 * @var \App\Model\Entity\Branch[] $branches
 * @var \App\Model\Entity\GatheringType[] $gatheringTypes
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Gatherings';
$this->KMP->endBlock();
?>
<h3><?= __('Gatherings') ?></h3>

<div class="mb-3">
    <?= $this->Html->link(
        '<i class="bi bi-plus-circle"></i> ' . __('New Gathering'),
        ['action' => 'add'],
        ['class' => 'btn btn-primary', 'escape' => false]
    ) ?>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <?= $this->Form->create(null, ['type' => 'get', 'valueSources' => 'query']) ?>
        <div class="row g-3">
            <div class="col-md-3">
                <?= $this->Form->control('branch_id', [
                    'options' => $branches,
                    'empty' => __('All Branches'),
                    'class' => 'form-select',
                    'label' => __('Branch')
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('gathering_type_id', [
                    'options' => $gatheringTypes,
                    'empty' => __('All Types'),
                    'class' => 'form-select',
                    'label' => __('Type')
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $this->Form->control('start_date', [
                    'type' => 'date',
                    'class' => 'form-control',
                    'label' => __('From Date')
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $this->Form->control('end_date', [
                    'type' => 'date',
                    'class' => 'form-control',
                    'label' => __('To Date')
                ]) ?>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <?= $this->Form->button(__('Filter'), ['class' => 'btn btn-secondary me-2']) ?>
                <?= $this->Html->link(__('Clear'), ['action' => 'index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>
        </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<!-- Gatherings Table -->
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('name') ?></th>
                <th><?= $this->Paginator->sort('branch_id') ?></th>
                <th><?= $this->Paginator->sort('gathering_type_id', 'Type') ?></th>
                <th><?= $this->Paginator->sort('start_date') ?></th>
                <th><?= $this->Paginator->sort('end_date') ?></th>
                <th><?= __('Activities') ?></th>
                <th><?= $this->Paginator->sort('created') ?></th>
                <th class="actions"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($gatherings)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        <?= __('No gatherings found.') ?>
                        <?= $this->Html->link(__('Create one now'), ['action' => 'add']) ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($gatherings as $gathering): ?>
                    <tr>
                        <td>
                            <?= $this->Html->link(
                                h($gathering->name),
                                ['action' => 'view', $gathering->id]
                            ) ?>
                        </td>
                        <td><?= $gathering->has('branch') ? h($gathering->branch->name) : '' ?></td>
                        <td><?= $gathering->has('gathering_type') ? h($gathering->gathering_type->name) : '' ?></td>
                        <td><?= h($gathering->start_date->format('Y-m-d')) ?></td>
                        <td><?= h($gathering->end_date->format('Y-m-d')) ?></td>
                        <td>
                            <?php if (!empty($gathering->gathering_activities)): ?>
                                <span class="badge bg-info">
                                    <?= count($gathering->gathering_activities) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($gathering->created->format('Y-m-d')) ?></td>
                        <td class="actions">
                            <?= $this->Html->link(
                                '<i class="bi bi-eye-fill"></i>',
                                ['action' => 'view', $gathering->id],
                                ['escape' => false, 'title' => __('View'), 'class' => 'btn btn-sm btn-secondary']
                            ) ?>
                            <?php if ($user->checkCan('edit', $gathering)): ?>
                                <?= $this->Html->link(
                                    '<i class="bi bi-pencil-fill"></i>',
                                    ['action' => 'edit', $gathering->id],
                                    ['escape' => false, 'title' => __('Edit'), 'class' => 'btn btn-sm btn-primary']
                                ) ?>
                            <?php endif; ?>
                            <?php if ($user->checkCan('delete', $gathering)): ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-trash-fill"></i>',
                                    ['action' => 'delete', $gathering->id],
                                    [
                                        'confirm' => __('Are you sure you want to delete "{0}"?', $gathering->name),
                                        'escape' => false,
                                        'title' => __('Delete'),
                                        'class' => 'btn btn-sm btn-danger'
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first("«", ["label" => __("First")]) ?>
        <?= $this->Paginator->prev("‹", ["label" => __("Previous")]) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next("›", ["label" => __("Next")]) ?>
        <?= $this->Paginator->last("»", ["label" => __("Last")]) ?>
    </ul>
    <p class="text-muted"><?= $this->Paginator->counter(__(
                                "Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total"
                            )) ?></p>
</div>