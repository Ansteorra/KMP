<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationGroup[]|\Cake\Collection\CollectionInterface $authorizationGroups
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<h3>
    Authorization Groups
</h3>

<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($authorizationGroups as $authorizationGroup) : ?>
            <tr>
                <td><?= h($authorizationGroup->name) ?></td>
                <td class="actions">
                    <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $authorizationGroup->id],
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