<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 */
?>
<?php

use Cake\I18n\DateTime;
use Cake\Log\Log;

$this->extend("/layout/TwitterBootstrap/dashboard");
$active = [];
$inactive = [];
foreach ($role->member_roles as $assignee) {
    if ($assignee->expires_on === null || $assignee->expires_on > DateTime::now()) {
        $active[] = $assignee;
    } else {
        $inactive[] = $assignee;
    }
} //sort $active by start_on
usort($active, function ($a, $b) {
    return $a->start_on <=> $b->start_on;
});
//sort $inactive by expires_on
usort($inactive, function ($a, $b) {
    return $a->expires_on <=> $b->expires_on;
});
?>

<div class="roles view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
                <?= h($role->name) ?>
            </h3>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#editModal">Edit</button>
            <?= $this->Form->postLink(
                __("Delete"),
                ["action" => "delete", $role->id],
                [
                    "confirm" => __(
                        "Are you sure you want to delete {0}?",
                        $role->name,
                    ),
                    "title" => __("Delete"),
                    "class" => "btn btn-danger btn-sm",
                ],
            ) ?>
        </div>
    </div>
    <div class="related pt-2">
        <h4><?= __(
                "Related Members",
            ) ?> : <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#addMemberModal">Add Member</button></h4>
        <?php if (!empty($role->member_roles)) : ?>
        <nav>
            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                <button class="nav-link active" id="nav-active-members-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-active-members" type="button" role="tab" aria-controls="nav-active-members"
                    aria-selected="true">Active</button>
                <button class="nav-link" id="nav-deactivated-members-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-deactivated-members" type="button" role="tab"
                    aria-controls="nav-pdeactivated-members" aria-selected="false">Deactivated</button>
            </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active" id="nav-active-members" role="tabpanel"
                aria-labelledby="nav-active-members-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Sca Name") ?></th>
                            <th scope="col"><?= __(
                                                    "Assignment Date",
                                                ) ?></th>
                            <th scope="col"><?= __("Expire Date") ?></th>
                            <th scope="col"><?= __("Approved By") ?></th>
                            <th scope="col" class="actions"><?= __(
                                                                    "Actions",
                                                                ) ?></th>
                        </tr>
                        <?php foreach ($active as $assignee) : ?>
                        <tr>
                            <td><?= h(
                                            $assignee->member->sca_name,
                                        ) ?></td>
                            <td><?= h($assignee->start_on) ?></td>
                            <td><?= h($assignee->expires_on) ?></td>
                            <td><?= h(
                                            $assignee->approved_by->sca_name,
                                        ) ?></td>
                            <td class="actions">
                                <?= $this->Form->postLink(
                                            __("Deactivate"),
                                            [
                                                "controller" => "MemberRoles",
                                                "action" => "deactivate",
                                                $assignee->id,
                                            ],
                                            [
                                                "confirm" => __(
                                                    "Are you sure you want to deactivate for {0}?",
                                                    $assignee->member->sca_name,
                                                ),
                                                "class" => "btn btn-danger",
                                            ],
                                        ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="nav-deactivated-members" role="tabpanel"
                aria-labelledby="nav-deactivated-members-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Sca Name") ?></th>
                            <th scope="col"><?= __(
                                                    "Assignment Date",
                                                ) ?></th>
                            <th scope="col"><?= __("Expire Date") ?></th>
                            <th scope="col"><?= __("Approved By") ?></th>
                        </tr>
                        <?php foreach ($inactive as $assignee) : ?>
                        <tr>
                            <td><?= h(
                                            $assignee->member->sca_name,
                                        ) ?></td>
                            <td><?= h($assignee->start_on) ?></td>
                            <td><?= h($assignee->expires_on) ?></td>
                            <td><?= h(
                                            $assignee->approved_by->sca_name,
                                        ) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="related">
    <h4><?= __(
            "Related Permissions",
        ) ?> : <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
            data-bs-target="#addPermissionModal">Add Permission</button></h4>
    </h4>
    <?php if (!empty($role->permissions)) : ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col" colspan='2'></th>
                    <th scope="col" colspan='3' class="text-center table-active">Requirements</th>
                    <th scope="col" colspan='2'></th>
                </tr>
                <tr>
                    <th scope="col"><?= __("Name") ?></th>
                    <th scope="col"><?= __("Authorization Type") ?></th>
                    <th scope="col" class="text-center"><?= __(
                                                                "Membership",
                                                            ) ?></th>
                    <th scope="col" class="text-center"><?= __(
                                                                "Background Check",
                                                            ) ?></th>
                    <th scope="col" class="text-center"><?= __(
                                                                "Minimum Age",
                                                            ) ?></th>
                    <th scope="col" class="text-center"><?= __(
                                                                "Super User",
                                                            ) ?></th>
                    <th scope="col" class="text-center"><?= __(
                                                                "System",
                                                            ) ?></th>
                    <th scope="col" class="actions"><?= __(
                                                            "Actions",
                                                        ) ?></th>
                </tr>
            </thead>
            <?php foreach ($role->permissions as $permission) : ?>
            <tr>
                <td><?= h($permission->name) ?></td>
                <td><?= h(
                                $permission->authorization_type === null
                                    ? ""
                                    : $permission->authorization_type->name,
                            ) ?>
                </td>
                <td class="text-center"><?= $this->Kmp->bool(
                                                    $permission->require_active_membership,
                                                    $this->Html,
                                                ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                                    $permission->require_active_background_check,
                                                    $this->Html,
                                                ) ?>
                </td>
                <td class="text-center"><?= h(
                                                    $permission->require_min_age,
                                                ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                                    $permission->is_super_user,
                                                    $this->Html,
                                                ) ?></td>
                <td class="text-center"><?= $this->Kmp->bool(
                                                    $permission->system,
                                                    $this->Html,
                                                ) ?></td>
                <td class="actions">
                    <?= $this->Form->postLink(
                                __("Remove"),
                                [
                                    "controller" => "Roles",
                                    "action" => "deletePermission",
                                ],
                                [
                                    "confirm" => __(
                                        "Are you sure you want to remove for {0}?",
                                        $permission->name,
                                    ),
                                    "class" => "btn btn-danger",
                                    "data" => [
                                        "permission_id" => $permission->id,
                                        "role_id" => $role->id,
                                    ],
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
echo $this->Modal->create("Add Member to Role", [
    "id" => "addMemberModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create(null, [
        "id" => "add_member__form",
        "url" => ["controller" => "MemberRoles", "action" => "add"],
    ]);
    echo $this->Form->control("sca_name", [
        "type" => "text",
        "label" => "SCA Name",
        "id" => "add_member__sca_name",
    ]);
    echo $this->Form->control("role_id", [
        "type" => "hidden",
        "value" => $role->id,
        "id" => "add_member__role_id",
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "id" => "add_member__member_id",
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "add_member__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>

<?php echo $this->Modal->create("Add Permission to Role", [
    "id" => "addPermissionModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->create(null, [
        "id" => "add_permission__form",
        "url" => ["controller" => "Roles", "action" => "addPermission"],
    ]);
    echo $this->Form->control("permission_id", [
        "options" => $permissions,
        "empty" => true,
        "id" => "add_permission__permission_id",
    ]);
    echo $this->Form->control("role_id", [
        "type" => "hidden",
        "value" => $role->id,
        "id" => "add_permission__role_id",
    ]);
    echo $this->Form->end();
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "add_permission__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);
$this->end();
?>

<?php echo $this->Modal->create("Edit", [
    "id" => "editModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->create($role, [
        "id" => "edit_entity",
        "url" => ["controller" => "Roles", "action" => "edit", $role->id],
    ]);
    echo $this->Form->control("name");
    echo $this->Form->end();
    ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
        "onclick" => '$("#edit_entity").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);
$this->end();
?>

<?php
$this->append("script", $this->Html->script(["app/autocomplete.js"]));
$this->append("script", $this->Html->script(["app/roles/view.js"]));
?>