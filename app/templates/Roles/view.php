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

$user = $this->request->getAttribute("identity");
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

        <?php if (!empty($role->previous_member_roles) || !empty($role->current_member_roles) || !empty($role->upcoming_member_roles)) {
            $linkTemplate = [
                "type" => "postLink",
                "verify" => true,
                "label" => "Deactivate",
                "controller" => "MemberRoles",
                "action" => "deactivate",
                "id" => "id",
                "condition" => ["granting_model" => "Direct Grant"],
                "options" => [
                    "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
                    "class" => "btn btn-danger"
                ],
            ];
            $currentUpcomingTemplate = [
                "Member" => "member->sca_name",
                "Start Date" => "start_on",
                "End Date" => "expires_on",
                "Approved By" => "approved_by->sca_name",
                "Granted By" => "granting_model",
                "Actions" => [
                    $linkTemplate
                ],
            ];
            $previousTemplate = [
                "Member" => "member->sca_name",
                "Start Date" => "start_on",
                "End Date" => "expires_on",
                "Approved By" => "approved_by->sca_name",
                "Deactivated By" => "revoked_by->sca_name",
            ];

            echo $this->element('activeWindowTabs', [
                'user' => $user,
                'tabGroupName' => "membersTabs",
                'tabs' => [
                    "active" => [
                        "label" => __("Active"),
                        "id" => "active-members",
                        "selected" => true,
                        "columns" => $currentUpcomingTemplate,
                        "data" => $role->current_member_roles,
                    ],
                    "upcoming" => [
                        "label" => __("Upcoming"),
                        "id" => "upcoming-members",
                        "selected" => false,
                        "columns" => $currentUpcomingTemplate,
                        "data" => $role->upcoming_member_roles,
                    ],
                    "previous" => [
                        "label" => __("Previous"),
                        "id" => "previous-members",
                        "selected" => false,
                        "columns" => $previousTemplate,
                        "data" => $role->previous_member_roles,
                    ]
                ]
            ]);
        } else {
            echo "<p>No Members Assigned</p>";
        } ?>
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
                        <th scope="col"><?= __("Activity") ?></th>
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
                                    $permission->activity === null
                                        ? ""
                                        : $permission->activity->name,
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

echo $this->element('roles/addMemberModal', []);
echo $this->element('roles/addPermissionModal', []);
echo $this->element('roles/editModal', []);

$this->end();
?>




<?php
$this->append("script", $this->Html->script(["app/autocomplete.js"]));
$this->append("script", $this->Html->script(["app/roles/view.js"]));
$this->append("script", $this->Html->scriptBlock("
        var pageControl = new rolesView();
        pageControl.run(" . $this->Url->webroot("") . ");
"));
?>