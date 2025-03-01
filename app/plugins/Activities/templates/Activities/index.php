<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Activity[]|\Cake\Collection\CollectionInterface $activities
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Activities';
$this->KMP->endBlock(); ?>
<h3>
    Activities
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col">Activity Group</th>
            <th scope="col" class="text-center">Grants Role</th>
            <th scope="col" class="text-center"><?= $this->Paginator->sort(
                                                    "term_length",
                                                    [
                                                        "label" => "Duration (Months)",
                                                    ],
                                                ) ?></th>
            <th scope="col" class="text-center"><?= $this->Paginator->sort(
                                                    "minimum_age",
                                                ) ?></th>
            <th scope="col" class="text-center"><?= $this->Paginator->sort(
                                                    "maximum_age",
                                                ) ?></th>
            <th scope="col" class="text-center"><?= $this->Paginator->sort(
                                                    "num_required_authorizors",
                                                    ["label" => "# for Auth"],
                                                ) ?></th>

            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($activities as $activity) : ?>
        <tr>
            <td><?= h($activity->name) ?></td>
            <td><?= h($activity->activity_group->name) ?></td>
            <td class="text-center"><?= $activity->role
                                            ? h($activity->role->name)
                                            : "None" ?></td>
            <td class="text-center"><?= $this->Number->format(
                                            $activity->term_length,
                                        ) ?></td>
            <td class="text-center"><?= $activity->minimum_age === null
                                            ? ""
                                            : $this->Number->format($activity->minimum_age) ?></td>
            <td class="text-center"><?= $activity->maximum_age === null
                                            ? ""
                                            : $this->Number->format($activity->maximum_age) ?></td>
            <td class="text-center"><?= $this->Number->format(
                                            $activity->num_required_authorizors,
                                        ) ?></td>
            <td class="actions text-end text-nowrap">
                <?= $this->Html->link(
                        __(""),
                        ["action" => "view", $activity->id],
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