<?php

use Cake\I18n\Date;
use Cake\I18n\DateTime;

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Branch $branch
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

$user = $this->request->getAttribute("identity");
?>

<div class="branches view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
                <?= h($branch->name) ?>
            </h3>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#editModal">Edit</button>
            <?php if (empty($branch->children) && empty($branch->members)) {
                echo $this->Form->postLink(
                    __("Delete"),
                    ["action" => "delete", $branch->id],
                    [
                        "confirm" => __(
                            "Are you sure you want to delete {0}?",
                            $branch->name,
                        ),
                        "title" => __("Delete"),
                        "class" => "btn btn-danger btn-sm",
                    ],
                );
            } ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr scope="row">
                <th class="col"><?= __("Location") ?></th>
                <td class="col-10"><?= h($branch->location) ?></td>
            </tr>
            <tr scope="row">
                <th class="col"><?= __("Parent") ?></th>
                <td class="col-10"><?= $branch->parent === null
                                        ? "Root"
                                        : $this->Html->link(
                                            __($branch->parent->name),
                                            ["action" => "view", $branch->parent_id],
                                            ["title" => __("View")],
                                        ) ?></td>
            </tr>
            <?= $this->element('pluginDetailBodies', [
                'pluginViewCells' => $pluginViewCells,
                'id' => $branch->id
            ]) ?>
        </table>
    </div>
    <nav>
        <div class="nav nav-tabs" id="nav-memberAreas" role="tablist">
            <button class="nav-link active" id="nav-members-tab" data-bs-toggle="tab" data-bs-target="#nav-members"
                type="button" role="tab" aria-controls="nav-members" aria-selected="true"><?= __("Members") ?>
            </button>
            <?= $this->element('pluginTabButtons', [
                'pluginViewCells' => $pluginViewCells,
                'activateFirst' => false,
            ]) ?>
            <button class="nav-link" id="nav-branches-tab" data-bs-toggle="tab" data-bs-target="#nav-branches"
                type="button" role="tab" aria-controls="nav-branches" aria-selected="false"><?= __("Branches") ?>
            </button>
        </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <div class="related tab-pane fade show active m-3" id="nav-members" role="tabpanel"
            aria-labelledby="nav-members-tab">
            <?php if (!empty($branch->members)) : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <tr>
                        <th scope="col"><?= __("Name") ?></th>
                        <th scope="col"><?= __("Minor") ?></th>
                        <th scope="col"><?= __("Membership Number") ?></th>
                        <th scope="col"><?= __("Membership Exp. Date") ?></th>
                        <th scope="col"><?= __("Status") ?></th>
                        <th scope="col" class="actions"><?= __("Actions") ?></th>
                    </tr>
                    <?php foreach ($branch->members as $member) : ?>
                    <tr>
                        <td><?= h($member->sca_name) ?></td>
                        <td><?= $this->KMP->bool($member->age < 18, $this->Html) ?></td>
                        <td><?= h($member->membership_number) ?></td>
                        <td><?= h($member->membership_expires_on->toDateString()) ?></td>
                        <td><?= h($member->status) ?></td>
                        <td class="actions">
                            <?= $this->Html->link(
                                        __("View"),
                                        [
                                            "controller" => "members",
                                            "action" => "view",
                                            $member->id,
                                        ],
                                        [
                                            "title" => __("View"),
                                            "class" => "btn btn-secondary",
                                        ],
                                    ) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php else : ?>
            <p><?= __("No members found") ?></p>
            <?php endif; ?>
        </div>
        <?= $this->element('pluginTabBodies', [
            'pluginViewCells' => $pluginViewCells,
            'id' => $branch->id,
            'activateFirst' => false
        ]) ?>
        <div class="related tab-pane fade m-3" id="nav-branches" role="tabpanel" aria-labelledby="nav-branches-tab">
            <?php if (!empty($branch->children)) : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <tr>
                        <th scope="col"><?= __("Name") ?></th>
                        <th scope="col"><?= __("Location") ?></th>
                        <th scope="col" class="actions"><?= __("Actions") ?></th>
                    </tr>
                    <?php foreach ($branch->children as $child) : ?>
                    <tr>
                        <td><?= h($child->name) ?></td>
                        <td><?= h($child->location) ?></td>
                        <td class="actions">
                            <?= $this->Html->link(
                                        __("View"),
                                        ["action" => "view", $child->id],
                                        [
                                            "title" => __("View"),
                                            "class" => "btn btn-secondary",
                                        ],
                                    ) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php else : ?>
            <p><?= __("No branches found") ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>



<?php
echo $this->KMP->startBlock("modals");

echo $this->element('branches/editModal', [
    'user' => $user,
]);

$this->KMP->endBlock(); ?>