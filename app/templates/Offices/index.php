<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<h3>
    Offices
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col"><?= __("Department") ?></th>
            <th scope="col" class="text-center"><?= __("Term (years)") ?></th>
            <th scope="col" class="text-center"><?= __("Warrant") ?></th>
            <th scope="col" class="text-center"><?= __(
                                                    "One Per Branch",
                                                ) ?></th>
            <th scope="col" class="text-center"><?= __("At Large") ?></th>
            <th scope="col" class="text-center"><?= __("Deputy To") ?></th>
            <th scope="col" class="text-center"><?= __("Grants Role") ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($offices as $office) : ?>
        <tr>
            <td><?= h($office->name) ?></td>
            <td><?= h($office->department->name) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $office->require_active_membership,
                                            $this->Html,
                                        ) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $office->requires_warrant,
                                            $this->Html,
                                        ) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $office->one_per_branch,
                                            $this->Html,
                                        ) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $office->at_large,
                                            $this->Html,
                                        ) ?></td>
            <td class="text-center"><?= h(
                                            $office->deputy_to === null
                                                ? ""
                                                : $office->deputy_to->name,
                                        ) ?></td>

            <td class="text-center"><?= h(
                                            $office->grants_role === null
                                                ? ""
                                                : $office->grants_role->name,
                                        ) ?></td>
            <td class="actions">
                <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $office->id],
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