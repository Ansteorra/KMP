<?php

/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\GatheringType> $gatheringTypes
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Gathering Types';
$this->KMP->endBlock();
?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Gathering Types
        </h3>
    </div>
    <div class="col text-end">
        <?php
        if ($user->checkCan("add", "GatheringTypes")) :
        ?>
            <?= $this->Html->link(
                ' Add Gathering Type',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('name') ?></th>
                <th><?= $this->Paginator->sort('description') ?></th>
                <th><?= $this->Paginator->sort('clonable', 'Can Clone') ?></th>
                <th><?= $this->Paginator->sort('created') ?></th>
                <th class="actions"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gatheringTypes as $gatheringType): ?>
                <tr>
                    <td><?= h($gatheringType->name) ?></td>
                    <td><?= h($gatheringType->description) ?></td>
                    <td><?= $gatheringType->clonable ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-muted"></i>' ?>
                    </td>
                    <td><?= h($gatheringType->created) ?></td>
                    <td class="actions">
                        <?= $this->Html->link('<i class="bi bi-binoculars-fill"></i>', ['action' => 'view', $gatheringType->id], ['escape' => false, 'title' => __('View'), 'class' => 'btn btn-sm btn-secondary']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first("«", ["label" => __("First")]) ?>
        <?= $this->Paginator->prev("‹", [
            "label" => __("Previous"),
        ]) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next("›", ["label" => __("Next")]) ?>
        <?= $this->Paginator->last("»", ["label" => __("Last")]) ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
    </p>
</div>