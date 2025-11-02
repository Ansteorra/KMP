<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringType $gatheringType
 * @var array $availableActivities
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Gathering Type - ' . $gatheringType->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($gatheringType->name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->checkCan("edit", $gatheringType)) : ?>
    <?= $this->Html->link(__('Edit'), ['action' => 'edit', $gatheringType->id], ['class' => 'btn btn-primary btn-sm']) ?>
<?php endif; ?>
<?php if ($user->checkCan("delete", $gatheringType)) : ?>
    <?= $this->Form->postLink(
        __('Delete'),
        ['action' => 'delete', $gatheringType->id],
        [
            'confirm' => __('Are you sure you want to delete "{0}"?', $gatheringType->name),
            'class' => 'btn btn-danger btn-sm'
        ]
    ) ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("recordDetails") ?>
<tr scope="row">
    <th class="col"><?= __('Name') ?></th>
    <td class="col-10"><?= h($gatheringType->name) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Description') ?></th>
    <td class="col-10"><?= h($gatheringType->description) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Can Clone') ?></th>
    <td class="col-10"><?= $this->KMP->bool($gatheringType->clonable, $this->Html) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Calendar Color') ?></th>
    <td class="col-10">
        <span class="badge" style="background-color: <?= h($gatheringType->color) ?>;">
            <?= h($gatheringType->color) ?>
        </span>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Created') ?></th>
    <td class="col-10"><?= h($gatheringType->created) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Modified') ?></th>
    <td class="col-10"><?= h($gatheringType->modified) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link active" id="nav-template-activities-tab" data-bs-toggle="tab" data-bs-target="#nav-template-activities" type="button"
    role="tab" aria-controls="nav-template-activities" aria-selected="true"><?= __("Template Activities") ?>
</button>
<button class="nav-link" id="nav-gatherings-tab" data-bs-toggle="tab" data-bs-target="#nav-gatherings" type="button"
    role="tab" aria-controls="nav-gatherings" aria-selected="false"><?= __("Gatherings") ?>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade show active m-3" id="nav-template-activities" role="tabpanel" aria-labelledby="nav-template-activities-tab">
    <h4><?= __('Template Activities') ?></h4>
    <p class="text-muted">
        <?= __('These activities will be automatically added to any gathering of this type.') ?>
    </p>

    <?php if ($user->checkCan("edit", $gatheringType)) : ?>
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <i class="bi bi-plus-circle"></i> <?= __('Add Template Activity') ?>
        </button>
    <?php endif; ?>

    <?php if (!empty($gatheringType->gathering_activities)) : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= __('Activity Name') ?></th>
                        <th><?= __('Description') ?></th>
                        <th><?= __('Not Removable') ?></th>
                        <?php if ($user->checkCan("edit", $gatheringType)) : ?>
                            <th class="actions"><?= __('Actions') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gatheringType->gathering_activities as $activity) : ?>
                        <tr>
                            <td><?= h($activity->name) ?></td>
                            <td><?= h($activity->description) ?></td>
                            <td>
                                <?php if ($activity->_joinData && $activity->_joinData->not_removable): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-lock-fill"></i> <?= __('Cannot be removed') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted"><?= __('Can be removed') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($user->checkCan("edit", $gatheringType)) : ?>
                                <td class="actions">
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash-fill"></i>',
                                        ['action' => 'removeActivity', $gatheringType->id, $activity->id],
                                        [
                                            'confirm' => __('Are you sure you want to remove "{0}" from this gathering type?', $activity->name),
                                            'escape' => false,
                                            'title' => __('Remove'),
                                            'class' => 'btn btn-sm btn-outline-danger'
                                        ]
                                    ) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p><?= __('No template activities configured for this gathering type.') ?></p>
    <?php endif; ?>
</div>

<!-- Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1" aria-labelledby="addActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => ['action' => 'addActivity', $gatheringType->id],
                'id' => 'addActivityForm'
            ]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="addActivityModalLabel"><?= __('Add Template Activity') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <?= $this->Form->control('activity_id', [
                        'type' => 'select',
                        'options' => $availableActivities,
                        'empty' => __('-- Select Activity --'),
                        'required' => true,
                        'label' => __('Activity'),
                        'class' => 'form-select'
                    ]) ?>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <?= $this->Form->checkbox('not_removable', [
                            'label' => false,
                            'class' => 'form-check-input',
                            'id' => 'not-removable-checkbox'
                        ]) ?>
                        <label class="form-check-label" for="not-removable-checkbox">
                            <?= __('Not Removable') ?>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        <?= __('If checked, this activity will be locked on gatherings and cannot be removed.') ?>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <?= $this->Form->button(__('Add Activity'), ['class' => 'btn btn-primary']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
<div class="related tab-pane fade m-3" id="nav-gatherings" role="tabpanel" aria-labelledby="nav-gatherings-tab">
    <?php if (!empty($gatheringType->gatherings)) : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= __('Name') ?></th>
                        <th><?= __('Branch') ?></th>
                        <th><?= __('Start Date') ?></th>
                        <th><?= __('End Date') ?></th>
                        <th class="actions"><?= __('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gatheringType->gatherings as $gathering) : ?>
                        <tr>
                            <td><?= h($gathering->name) ?></td>
                            <td><?= $gathering->hasValue('branch') ? $this->Html->link($gathering->branch->name, ['controller' => 'Branches', 'action' => 'view', $gathering->branch->id]) : '' ?></td>
                            <td><?= h($gathering->start_date) ?></td>
                            <td><?= h($gathering->end_date) ?></td>
                            <td class="actions">
                                <?= $this->Html->link('<i class="bi bi-binoculars-fill"></i>', ['controller' => 'Gatherings', 'action' => 'view', $gathering->id], ['escape' => false, 'title' => __('View'), 'class' => 'btn btn-sm btn-outline-primary']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p><?= __('No gatherings using this type yet.') ?></p>
    <?php endif; ?>
</div>
<?php $this->KMP->endBlock() ?>