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
<div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0">Roles</h3>
    <a
        href="<?= $this->Url->build(['action' => 'index', '_ext' => 'csv'] + $this->getRequest()->getQueryParams()) ?>"
        class="btn btn-outline-primary btn-sm"
        data-controller="csv-download"
        data-csv-download-url-value="<?= $this->Url->build(['action' => 'index', '_ext' => 'csv'] + $this->getRequest()->getQueryParams()) ?>"
        data-csv-download-filename-value="roles.csv"
        title="Download CSV">
        <i class="bi bi-download"></i> Download CSV
    </a>
</div>
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