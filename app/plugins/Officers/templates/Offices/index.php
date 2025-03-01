<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Offices';
$this->KMP->endBlock(); ?>
<h3>
    Offices
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col"><?= __("Department") ?></th>
            <th scope="col" class="text-center"><?= __("Term (Month)") ?></th>
            <th scope="col" class="text-center"><?= __("Required") ?></th>
            <th scope="col" class="text-center"><?= __("Skip Report") ?></th>
            <th scope="col" class="text-center"><?= __("Warrant") ?></th>
            <th scope="col" class="text-center"><?= __(
                                                    "One Per Branch",
                                                ) ?></th>
            <th scope="col" class="text-center"><?= __("Reports To") ?></th>
            <th scope="col" class="text-center"><?= __("Deputy To") ?></th>
            <th scope="col" class="text-center"><?= __("Grants Role") ?></th>
            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($offices as $office) : ?>
            <tr>
                <td><?= h($office->name) ?></td>
                <td><?= h($office->department->name) ?></td>
                <td class="text-center"><?= h($office->term_length) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $office->required_office,
                                            $this->Html,
                                        ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $office->can_skip_report,
                                            $this->Html,
                                        ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $office->requires_warrant,
                                            $this->Html,
                                        ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $office->only_one_per_branch,
                                            $this->Html,
                                        ) ?></td>
                <td class="text-center"><?= h(
                                            $office->reports_to === null
                                                ? "Society"
                                                : $office->reports_to->name,
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
                <td class="actions text-end text-nowrap">
                    <?= $this->Html->link(
                        __(""),
                        ["action" => "view", $office->id],
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