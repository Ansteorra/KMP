<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Levels';
$this->KMP->endBlock(); ?>
<h3>
    Award Levels
</h3>

<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= h("Name") ?></th>
            <th scope="col"><?= h("Progression Order") ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($levels as $level) : ?>
            <tr>
                <td><?= h($level->name) ?></td>
                <td><?= h($level->progression_order) ?></td>
                <td class="actions">
                    <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $level->id],
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