<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Activity[]|\Cake\Collection\CollectionInterface $authorizationTypes
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
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
                                                    "length",
                                                    [
                                                        "label" => "Duration (years)",
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

            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($authorizationTypes as $authorizationType) : ?>
            <tr>
                <td><?= h($authorizationType->name) ?></td>
                <td><?= h($authorizationType->activity_group->name) ?></td>
                <td class="text-center"><?= $authorizationType->role
                                            ? h($authorizationType->role->name)
                                            : "None" ?></td>
                <td class="text-center"><?= $this->Number->format(
                                            $authorizationType->length,
                                        ) ?></td>
                <td class="text-center"><?= $authorizationType->minimum_age === null
                                            ? ""
                                            : $this->Number->format($authorizationType->minimum_age) ?></td>
                <td class="text-center"><?= $authorizationType->maximum_age === null
                                            ? ""
                                            : $this->Number->format($authorizationType->maximum_age) ?></td>
                <td class="text-center"><?= $this->Number->format(
                                            $authorizationType->num_required_authorizors,
                                        ) ?></td>
                <td class="actions">
                    <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $authorizationType->id],
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