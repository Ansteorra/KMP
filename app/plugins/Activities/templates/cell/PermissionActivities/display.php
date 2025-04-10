<?php
$user = $this->request->getAttribute("identity");
?>
<div class="table-responsive">
    <?php if (!empty($activities)) : ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th scope="col"><?= __("Name") ?></th>
                <th scope="col" class="actions"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activities as $activity) : ?>
            <tr>
                <td><?= h($activity->name) ?></td>
                <td class="actions text-end text-nowrap">
                    <?php if ($user->checkCan("view", "Activities.Activities")) { ?>
                    <?= $this->Html->link(
                                    __("View"),
                                    [
                                        "controller" => "Activities",
                                        "action" => "view",
                                        "plugin" => "Activities",
                                        $activity->id,
                                    ],
                                    ["class" => "btn btn-secondary btn-sm"],
                                ) ?>
                    <?php } ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else : ?>
<p><?= __("No Activities Assigned") ?></p>
<?php endif; ?>
</div>