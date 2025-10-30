<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringType $gatheringType
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
    <th class="col"><?= __('Created') ?></th>
    <td class="col-10"><?= h($gatheringType->created) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Modified') ?></th>
    <td class="col-10"><?= h($gatheringType->modified) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link active" id="nav-gatherings-tab" data-bs-toggle="tab" data-bs-target="#nav-gatherings" type="button"
    role="tab" aria-controls="nav-gatherings" aria-selected="true"><?= __("Gatherings") ?>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade show active m-3" id="nav-gatherings" role="tabpanel" aria-labelledby="nav-gatherings-tab">
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