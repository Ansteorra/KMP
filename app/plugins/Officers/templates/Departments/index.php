<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Departments';
$this->KMP->endBlock(); ?>
<div class="row align-items-start">
    <div class="col">
        <h3>Departments</h3>
    </div>
    <div class="col text-end">
        <?php
        $departmentsTable = \Cake\ORM\TableRegistry::getTableLocator()->get("Officers.Departments");
        $tempDepartment = $departmentsTable->newEmptyEntity();
        if ($user->checkCan("add", $tempDepartment)) :
        ?>
            <?= $this->Html->link(
                ' Add Department',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($departments as $department) : ?>
            <tr>
                <td><?= h($department->name) ?></td>
                <td class="actions text-end text-nowrap">
                    <?= $this->Html->link(
                        __(""),
                        ["action" => "view", $department->id],
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