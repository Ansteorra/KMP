<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Domains';
$this->KMP->endBlock(); ?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Awards
        </h3>
    </div>
    <div class="col text-end">
        <?php
        if ($user->checkCan("add", "Awards.Awards")) :
        ?>
            <?= $this->Html->link(
                ' Add Award',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort('id') ?></th>
            <th scope="col"><?= $this->Paginator->sort('name') ?></th>
            <th scope="col"><?= $this->Paginator->sort('domain_id') ?></th>
            <th scope="col"><?= $this->Paginator->sort('level_id') ?></th>
            <th scope="col"><?= $this->Paginator->sort('branch_id') ?></th>
            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($awards as $award) : ?>
            <tr>
                <td><?= $this->Number->format($award->id) ?></td>
                <td><?= h($award->name) ?></td>
                <td><?= $award->hasValue('domain') ? $this->Html->link($award->domain->name, ['controller' => 'Domains', 'action' => 'view', $award->domain->id]) : '' ?>
                </td>
                <td><?= $award->hasValue('level') ? $this->Html->link($award->level->name, ['controller' => 'Levels', 'action' => 'view', $award->level->id]) : '' ?>
                </td>
                <td><?= $award->hasValue('branch') ? $this->Html->link($award->branch->name, ['plugin' => null,  'controller' => 'Branches', 'action' => 'view', $award->branch->id]) : '' ?>
                </td>

                <td class="actions text-end text-nowrap">
                    <?= $this->Html->link(
                        __(""),
                        ["action" => "view", $award->id],
                        ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                    ) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
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
    <p><?= $this->Paginator->counter(
            __(
                "Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total",
            ),
        ) ?></p>
</div>