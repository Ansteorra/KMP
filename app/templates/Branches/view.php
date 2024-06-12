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
            <?php if (!empty($requiredOffices)) : ?>
            <?php foreach ($requiredOffices as $office) : ?>
            <tr scope="row">
                <th class="col"><?= h($office->name) ?></th>
                <td class="col-10">
                    <?php if (!empty($office->current_officers)) : ?>
                    <?php foreach ($office->current_officers as $officer) : ?>
                    <?= h($officer->member->sca_name) ?> (<?= h($officer->start_on->toDateString()) ?> -
                    <?= h($officer->expires_on->toDateString()) ?>)
                    <?php endforeach; ?>
                    <?php else : ?>
                    <?= __("No officer assigned") ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
    <nav>
        <div class="nav nav-tabs" id="nav-memberAreas" role="tablist">
            <button class="nav-link active" id="nav-members-tab" data-bs-toggle="tab" data-bs-target="#nav-members"
                type="button" role="tab" aria-controls="nav-members" aria-selected="true"><?= __("Members") ?>
            </button>
            <button class="nav-link" id="nav-offices-tab" data-bs-toggle="tab" data-bs-target="#nav-offices"
                type="button" role="tab" aria-controls="nav-offices" aria-selected="false"><?= __("Officers") ?>
            </button>
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
        <div class="related tab-pane fade m-3" id="nav-offices" role="tabpanel" aria-labelledby="nav-offices-tab">
            <button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
                data-bs-target="#assignOfficerModal">Assign Officer</button>
            <?php if (!empty($branch->previous_officers) || !empty($branch->current_officers) || !empty($branch->upcoming_officers)) {
                $linkTemplate = [
                    "type" => "button",
                    "verify" => true,
                    "label" => "Release",
                    "controller" => "Officers",
                    "action" => "release",
                    "id" => "officer_id",
                    "options" => [
                        "class" => "btn btn-danger",
                        "data-bs-toggle" => "modal",
                        "data-bs-target" => "#releaseModal",
                        "onclick" => "$('#release_officer__id').val('{{id}}')",
                    ],
                ];
                $currentAndUpcomingTemplate = [
                    "Name" => "member->sca_name",
                    "Office" => "{{office->name}}{{: (deputy_description) }}",
                    "Start Date" => "start_on",
                    "End Date" => "expires_on",
                    "Reports To" => "{{reports_to_branch->name}} - {{reports_to_office->name}}",
                    "Actions" => [
                        $linkTemplate
                    ],
                ];
                $previousTemplate = [
                    "Name" => "member->sca_name",
                    "Office" => "{{office->name}} {{: (deputy_description) }}",
                    "Start Date" => "start_on",
                    "End Date" => "expires_on",
                    "Reason" => "revoked_reason",
                ];
                echo $this->element('activeWindowTabs', [
                    'user' => $user,
                    'tabGroupName' => "officeTabs",
                    'tabs' => [
                        "active" => [
                            "label" => __("Active"),
                            "id" => "active-office",
                            "selected" => true,
                            "columns" => $currentAndUpcomingTemplate,
                            "data" => $branch->current_officers,
                        ],
                        "upcoming" => [
                            "label" => __("Incoming"),
                            "id" => "upcoming-office",
                            "selected" => false,
                            "columns" => $currentAndUpcomingTemplate,
                            "data" => $branch->upcoming_officers,
                        ],
                        "previous" => [
                            "label" => __("Previous"),
                            "id" => "previous-office",
                            "selected" => false,
                            "columns" => $previousTemplate,
                            "data" => $branch->previous_officers,
                        ]
                    ]
                ]);
            } else {
                echo "<p>No Offices assigned</p>";
            } ?>
        </div>
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
$this->append("script", $this->Html->scriptBlock("
        var pageControl = new branchesView();
        pageControl.run('" . $this->Url->webroot("") . "');
"));
?>