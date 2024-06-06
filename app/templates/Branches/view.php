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
                <?= $this->Html->link(
                    "",
                    ["action" => "index"],
                    ["class" => "bi bi-arrow-left-circle"],
                ) ?>
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
            <trscope="row">
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
                </tr>
        </table>
    </div>
    <div class="related">
        <h4><?= __("Officers") ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#assignOfficerModal">Assign Officer</button>
        </h4>
        <?php if (!empty($branch->officers)) {

            $inbound = [];
            $active = [];
            $expired = [];
            $exp_date = Date::now();
            foreach ($branch->officers as $officer) {
                if ($officer->start_on > Date::now()) {
                    $inbound[] = $officer;
                } elseif ($officer->expires_on < $exp_date) {
                    $expired[] = $officer;
                } else {
                    $active[] = $officer;
                }
            }
        ?>
        <nav>
            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                <button class="nav-link active" id="nav-active-officers-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-active-officers" type="button" role="tab" aria-controls="nav-active-officers"
                    aria-selected="true">Current</button>
                <button class="nav-link" id="nav-inbound-officers-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-inbound-officers" type="button" role="tab" aria-controls="nav-inbound-officers"
                    aria-selected="false">Inbound</button>
                <button class="nav-link" id="nav-expired-officers-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-expired-officers" type="button" role="tab" aria-controls="nav-expired-officers"
                    aria-selected="false">Previous</button>
            </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active" id="nav-active-officers" role="tabpanel"
                aria-labelledby="nav-active-officers-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Name") ?></th>
                            <th scope="col"><?= __("Office") ?></th>
                            <th scope="col"><?= __("Start On") ?></th>
                            <th scope="col"><?= __("Ends On") ?></th>
                            <th scope="col" class="actions"><?= __(
                                                                    "Actions",
                                                                ) ?></th>
                        </tr>
                        <?php foreach ($active as $officer) : ?>
                        <tr>
                            <td><?= h($officer->member->sca_name) ?></td>
                            <td><?= h($officer->office->name) ?></td>
                            <td><?= h($officer->start_on) ?></td>
                            <td><?= h($officer->expires_on) ?></td>
                            <td>
                                <?php if ($user->can("release", "Officers")) { ?>
                                <button type="button" class="btn btn-danger " data-bs-toggle="modal"
                                    data-bs-target="#releaseModal"
                                    onclick="$('#release_officer__id').val('<?= $officer->id ?>')">Release</button>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="nav-inbound-officers" role="tabpanel"
                aria-labelledby="nav-inbound-officers-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Name") ?></th>
                            <th scope="col"><?= __("Office") ?></th>
                            <th scope="col"><?= __("Start On") ?></th>
                            <th scope="col"><?= __("Ends On") ?></th>
                            <th scope="col" class="actions"><?= __(
                                                                    "Actions",
                                                                ) ?></th>
                        </tr>
                        <?php foreach ($inbound as $officer) : ?>
                        <tr>
                            <td><?= h($officer->member->sca_name) ?></td>
                            <td><?= h($officer->office->name) ?></td>
                            <td><?= h($officer->start_on) ?></td>
                            <td><?= h($officer->expires_on) ?></td>
                            <td>
                                <?php if ($user->can("release", "Officers")) { ?>
                                <button type="button" class="btn btn-danger " data-bs-toggle="modal"
                                    data-bs-target="#releaseModal"
                                    onclick="$('#release_officer__id').val('<?= $officer->id ?>')">Cancel</button>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="nav-expired-officers" role="tabpanel"
                aria-labelledby="nav-expired-officers-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Name") ?></th>
                            <th scope="col"><?= __("Office") ?></th>
                            <th scope="col"><?= __("Start On") ?></th>
                            <th scope="col"><?= __("Ends On") ?></th>
                            <th scope="col"><?= __("Reason") ?></th>
                        </tr>
                        <?php foreach ($expired as $officer) : ?>
                        <tr>
                            <td><?= h($officer->member->sca_name) ?></td>
                            <td><?= h($officer->office->name) ?></td>
                            <td><?= h($officer->start_on) ?></td>
                            <td><?= h($officer->expires_on) ?></td>
                            <td><?= h($officer->release_reason) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php } ?>
        <div class="related">
            <h4><?= __("Children") ?></h4>
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
            <?php endif; ?>
        </div>
        <div class="related">
            <h4><?= __("Members") ?></h4>
            <?php if (!empty($branch->members)) : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <tr>
                        <th scope="col"><?= __("Name") ?></th>
                        <th scope="col" class="actions"><?= __("Actions") ?></th>
                    </tr>
                    <?php foreach ($branch->members as $member) : ?>
                    <tr>
                        <td><?= h($member->sca_name) ?></td>
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
            <?php endif; ?>
        </div>
    </div>


    <?php
    $this->start("modals");

    echo $this->element('branches/editModal', [
        'user' => $user,
    ]);

    echo $this->element('branches/releaseModal', [
        'user' => $user,
    ]);

    echo $this->element('branches/assignModal', [
        'user' => $user,
    ]);


    $this->end(); ?>


    <?php
    $this->append("script", $this->Html->script(["app/autocomplete.js"]));
    $this->append("script", $this->Html->script(["app/branches/view.js"]));
    ?>