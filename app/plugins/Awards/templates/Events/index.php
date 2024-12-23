<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Rec Events';
$this->KMP->endBlock(); ?>
<h3>
    Award Rec Events
</h3>

<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= h("Name") ?></th>
            <th scope="col"><?= h("Branch") ?></th>
            <th scope="col"><?= $this->Paginator->sort('start_date') ?></th>
            <th scope="col"><?= $this->Paginator->sort('end_date') ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($events as $event) : ?>
        <tr>
            <td><?= h($event->name) ?></td>
            <td><?= $event->hasValue('branch') ? $this->Html->link($event->branch->name, ['plugin' => null, 'controller' => 'Branches', 'action' => 'view', $event->branch->id]) : '' ?>
            </td>
            <td><?= h(($event->start_date ? $event->start_date->toDateString() : "")) ?></td>
            <td><?= h(($event->end_date ? $event->end_date->toDateString() : "")) ?></td>
            <td class="actions">
                <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $event->id],
                        ["title" => __("View"), "class" => "btn btn-secondary"],
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