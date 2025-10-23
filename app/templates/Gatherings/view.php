<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Gathering - ' . $gathering->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($gathering->name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->checkCan("edit", $gathering)) : ?>
<?= $this->Html->link(__('Edit'), ['action' => 'edit', $gathering->id], ['class' => 'btn btn-primary btn-sm']) ?>
<?php endif; ?>
<?php if ($gathering->gathering_type->clonable && $user->checkCan("add", $gathering)) : ?>
<button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#cloneGatheringModal">
    <i class="bi bi-files"></i> <?= __('Clone') ?>
</button>
<?php endif; ?>
<?php if ($user->checkCan("delete", $gathering)) : ?>
<?= $this->Form->postLink(
        __('Delete'),
        ['action' => 'delete', $gathering->id],
        [
            'confirm' => __('Are you sure you want to delete "{0}"?', $gathering->name),
            'class' => 'btn btn-danger btn-sm'
        ]
    ) ?>
<?php endif; ?>
<?= $this->Html->link(__('List Gatherings'), ['action' => 'index'], ['class' => 'btn btn-secondary btn-sm']) ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("recordDetails") ?>
<tr scope="row">
    <th class="col"><?= __('Name') ?></th>
    <td class="col-10"><?= h($gathering->name) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Branch') ?></th>
    <td class="col-10">
        <?= $gathering->has('branch') ? $this->Html->link(
            $gathering->branch->name,
            ['controller' => 'Branches', 'action' => 'view', $gathering->branch->id]
        ) : '' ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Gathering Type') ?></th>
    <td class="col-10">
        <?= $gathering->has('gathering_type') ? $this->Html->link(
            $gathering->gathering_type->name,
            ['controller' => 'GatheringTypes', 'action' => 'view', $gathering->gathering_type->id]
        ) : '' ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Start Date') ?></th>
    <td class="col-10"><?= h($gathering->start_date->format('F j, Y')) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('End Date') ?></th>
    <td class="col-10"><?= h($gathering->end_date->format('F j, Y')) ?></td>
</tr>
<?php if ($gathering->start_date != $gathering->end_date): ?>
<tr scope="row">
    <th class="col"><?= __('Duration') ?></th>
    <td class="col-10">
        <?= $gathering->start_date->diffInDays($gathering->end_date) + 1 ?> days
    </td>
</tr>
<?php endif; ?>
<?php if (!empty($gathering->location)): ?>
<tr scope="row">
    <th class="col"><?= __('Location') ?></th>
    <td class="col-10"><?= h($gathering->location) ?></td>
</tr>
<?php endif; ?>
<?php if (!empty($gathering->description)): ?>
<tr scope="row">
    <th class="col"><?= __('Notes') ?></th>
    <td class="col-10"><?= $this->Text->autoParagraph(h($gathering->description)) ?></td>
</tr>
<?php endif; ?>
<tr scope="row">
    <th class="col"><?= __('Created') ?></th>
    <td class="col-10"><?= h($gathering->created->format('F j, Y g:i A')) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Modified') ?></th>
    <td class="col-10"><?= h($gathering->modified->format('F j, Y g:i A')) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link active" id="nav-activities-tab" data-bs-toggle="tab" data-bs-target="#nav-activities"
    type="button" role="tab" aria-controls="nav-activities" aria-selected="true" data-detail-tabs-target='tabBtn'>
    <?= __("Activities") ?>
    <span class="badge bg-secondary"><?= count($gathering->gathering_activities) ?></span>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade show active m-3" id="nav-activities" role="tabpanel"
    aria-labelledby="nav-activities-tab" data-detail-tabs-target="tabContent">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><?= __('Gathering Activities') ?></h5>
        <?php if ($user->checkCan('edit', $gathering) && !$hasWaivers): ?>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <i class="bi bi-plus-circle"></i> <?= __('Add Activity') ?>
        </button>
        <?php endif; ?>
    </div>

    <?php if ($hasWaivers): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        <?= __('Activities are locked because waivers have been uploaded for this gathering.') ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($gathering->gathering_activities)): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= __('Activity') ?></th>
                    <th><?= __('Description') ?></th>
                    <th><?= __('Waiver Requirements') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gathering->gathering_activities as $activity): ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                                    h($activity->name),
                                    ['controller' => 'GatheringActivities', 'action' => 'view', $activity->id]
                                ) ?>
                    </td>
                    <td><?= h($activity->description) ?></td>
                    <td>
                        <span class="text-muted">Waiver info from plugin</span>
                    </td>
                    <td class="actions text-end text-nowrap">
                        <?= $this->Html->link(
                                    '<i class="bi bi-eye-fill"></i>',
                                    ['controller' => 'GatheringActivities', 'action' => 'view', $activity->id],
                                    ['escape' => false, 'title' => __('View'), 'class' => 'btn btn-sm btn-secondary']
                                ) ?>
                        <?php if ($user->checkCan('edit', $gathering) && !$hasWaivers): ?>
                        <?= $this->Form->postLink(
                                        '<i class="bi bi-x-circle-fill"></i>',
                                        ['action' => 'remove-activity', $gathering->id, $activity->id],
                                        [
                                            'confirm' => __('Remove "{0}" from this gathering?', $activity->name),
                                            'escape' => false,
                                            'title' => __('Remove'),
                                            'class' => 'btn btn-sm btn-danger'
                                        ]
                                    ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle"></i>
        <?= __('No activities have been added to this gathering yet.') ?>
        <?php if ($user->checkCan('edit', $gathering) && !$hasWaivers): ?>
        <?= __('Click "Add Activity" above to get started.') ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php $this->KMP->endBlock() ?>

<?php
echo $this->KMP->startBlock("modals");

// Add Activity Modal
if ($user->checkCan('edit', $gathering) && !$hasWaivers) {
    echo $this->element('gatherings/addActivityModal', [
        'gathering' => $gathering,
        'availableActivities' => $availableActivities,
        'user' => $user,
    ]);
}

// Clone Gathering Modal
if ($gathering->gathering_type->clonable && $user->checkCan('add', $gathering)) {
    echo $this->element('gatherings/cloneModal', [
        'gathering' => $gathering,
        'user' => $user,
    ]);
}

$this->KMP->endBlock();
?>