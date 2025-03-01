<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Roles';
$this->KMP->endBlock(); ?>
<h3>
    Roles
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($roles as $role) : ?>
            <tr>
                <td><?= h($role->name) ?></td>
                <td class="actions text-end text-nowrap">
                    <?= $this->Html->link(
                        __(""),
                        ["action" => "view", $role->id],
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