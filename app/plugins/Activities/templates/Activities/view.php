<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Activity $activity
 */
?>
<?php

use Cake\I18n\DateTime;

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Activity - ' . h($activity->name);
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($activity->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?= $this->Form->postLink(
    __("Delete"),
    ["action" => "delete", $activity->id],
    [
        "confirm" => __(
            "Are you sure you want to delete {0}?",
            $activity->name,
        ),
        "title" => __("Delete"),
        "class" => "btn btn-danger btn-sm",
    ],
) ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __("Activity Group") ?></th>
    <td><?= $activity->hasValue("activity_group")
            ? $this->Html->link(
                $activity->activity_group->name,
                [
                    "controller" => "ActivityGroups",
                    "action" => "view",
                    $activity->activity_group->id,
                ],
            )
            : "" ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __("Authorized By") ?></th>
    <td><?= $activity->hasValue("permission")
            ? $this->Html->link($activity->permission->name, [
                "controller" => "Permissions",
                "action" => "view",
                $activity->permission->id,
            ])
            : "" ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __("Grants Role") ?></th>
    <td><?= $activity->hasValue("role")
            ? $this->Html->link($activity->role->name, [
                "controller" => "Roles",
                "action" => "view",
                $activity->role->id,
            ])
            : "" ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __("Length") ?></th>
    <td><?= $this->Number->format(
            $activity->term_length,
        ) ?></td>
</tr>
<tr>
    <th scope="row"><?= __("Minimum Age") ?></th>
    <td><?= $activity->minimum_age === null
            ? ""
            : $this->Number->format($activity->minimum_age) ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __("Maximum Age") ?></th>
    <td><?= $activity->maximum_age === null
            ? ""
            : $this->Number->format($activity->maximum_age) ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __("# for Authorization") ?></th>
    <td><?= $this->Number->format(
            $activity->num_required_authorizors,
        ) ?></td>
</tr>
<tr>
    <th scope="row"><?= __("# for Renewal") ?></th>
    <td><?= $this->Number->format(
            $activity->num_required_renewers,
        ) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-authorizations-tab" data-bs-toggle="tab" data-bs-target="#nav-authorizations"
    type="button" role="tab" aria-controls="nav-authorizations" aria-selected="false"
    data-detail-tabs-target='tabBtn'><?= __("Authorizations") ?>
</button>
<button class="nav-link" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles" type="button" role="tab"
    aria-controls="nav-roles" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Authorizing Roles") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="tab-pane fade m-3" id="nav-authorizations" role="tabpanel" aria-labelledby="nav-authorizations-tab"
    data-detail-tabs-target="tabContent">
    <?php
    if (!$isEmpty) :
        echo $this->element('turboActiveTabs', [
            'user' => $user,
            'tabGroupName' => "authorizationTabs",
            'tabs' => [
                "active" => [
                    "label" => __("Active"),
                    "id" => "current-authorization",
                    "selected" => true,
                    "turboUrl" => $this->URL->build(["controller" => "Authorizations", "action" => "ActivityAuthorizations", "plugin" => "Activities", "current", $id])
                ],
                "pending" => [
                    "label" => __("Pending"),
                    "id" => "pending-authorization",
                    "badge" => $pendingCount,
                    "badgeClass" => "bg-danger",
                    "selected" => false,
                    "turboUrl" => $this->URL->build(["controller" => "Authorizations", "action" => "ActivityAuthorizations", "plugin" => "Activities", "pending", $id])
                ],
                "previous" => [
                    "label" => __("Previous"),
                    "id" => "previous-authorization",
                    "selected" => false,
                    "turboUrl" => $this->URL->build(["controller" => "Authorizations", "action" => "ActivityAuthorizations", "plugin" => "Activities", "previous", $id])
                ]
            ]
        ]);
    else :
        echo "<p>No Authorizations</p>";
    endif; ?>
</div>
<div class="tab-pane fade m-3" id="nav-roles" role="tabpanel" aria-labelledby="nav-roles-tab"
    data-detail-tabs-target="tabContent">
    <?php if (!empty($roles)) : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __("Name") ?></th>
                    <th scope="col" class="actions"></th>
                </tr>
                <?php foreach ($roles as $role) : ?>
                    <tr>
                        <td><?= h($role->name) ?></td>
                        <td class="actions text-end text-nowrap">
                            <?= $this->Html->link(
                                __(""),
                                [
                                    "controller" => "Roles",
                                    "action" => "view",
                                    $role->id,
                                ],
                                ["class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php $this->KMP->endBlock();
echo $this->KMP->startBlock("modals");
echo $this->Form->create($activity, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Activities",
        "action" => "edit",
        $activity->id,
    ],
]);
echo $this->Modal->create("Edit Authoriztion Type", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("activity_group_id", [
        "options" => $activityGroup,
    ]);
    echo $this->Form->control("permission_id", [
        "label" => "Authorized By",
        "options" => $authByPermissions,
        "empty" => true
    ]);
    echo $this->Form->control("grants_role_id", [
        "options" => $authAssignableRoles,
        "empty" => true,
    ]);
    echo $this->Form->control("term_length", [
        "label" => "Duration (Months)",
        "type" => "number",
    ]);
    echo $this->Form->control("minimum_age", ["type" => "number"]);
    echo $this->Form->control("maximum_age", ["type" => "number"]);
    echo $this->Form->control("num_required_authorizors", [
        "label" => "# for Authorization",
        "type" => "number",
    ]);
    echo $this->Form->control("num_required_renewers", [
        "label" => "# for Renewal",
        "type" => "number",
    ]);

    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();

echo $this->element('revokeAuthorizationModal', [
    'user' => $user,
]);

$this->KMP->endBlock(); ?>