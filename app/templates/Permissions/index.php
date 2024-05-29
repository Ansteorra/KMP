<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<h3>
    Permissions
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col" colspan='2'></th>
            <th scope="col" colspan='3' class="text-center table-active">Requirements</th>
            <th scope="col" colspan='2'></th>
        </tr>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col"><?= $this->Paginator->sort(
                                "authorization_type_id",
                            ) ?></th>
            <th scope="col" class="text-center"><?= __("Membership") ?></th>
            <th scope="col" class="text-center"><?= __(
                                                    "Background Check",
                                                ) ?></th>
            <th scope="col" class="text-center"><?= __("Minimum Age") ?></th>
            <th scope="col" class="text-center"><?= __("Super User") ?></th>
            <th scope="col" class="text-center"><?= __("System") ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($permissions as $permission) : ?>
            <tr>
                <td><?= h($permission->name) ?></td>
                <td><?= h(
                        $permission->authorization_type === null
                            ? ""
                            : $permission->authorization_type->name,
                    ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->require_active_membership,
                                            $this->Html,
                                        ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->require_active_background_check,
                                            $this->Html,
                                        ) ?></td>
                <td class="text-center"><?= h($permission->require_min_age) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->is_super_user,
                                            $this->Html,
                                        ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->system,
                                            $this->Html,
                                        ) ?></td>
                <td class="actions">
                    <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $permission->id],
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