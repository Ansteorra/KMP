<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission $permission
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Permission - ' . $permission->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($permission->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php if (!$permission->is_system) { ?>
<?= $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $permission->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $permission->name,
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    ) ?>
<?php } ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr scope="row">
    <th class='col'><?= __("Name") ?></th>
    <td><?= h($permission->name) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Require Membership") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $permission->require_active_membership,
                            $this->Html,
                        ) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Require Background Check") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $permission->require_active_background_check,
                            $this->Html,
                        ) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Minimum Age") ?></th>
    <td class="col-10"><?= h($permission->require_min_age) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("System Permission") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $permission->is_system,
                            $this->Html,
                        ) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Is Super User") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $permission->is_super_user,
                            $this->Html,
                        ) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Requires a Warrant") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $permission->requires_warrant,
                            $this->Html,
                        ) ?></td>
</tr>
<?= $this->element('pluginDetailBodies', [
    'pluginViewCells' => $pluginViewCells,
    'id' => $permission->id
]) ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles" type="button" role="tab"
    aria-controls="nav-roles" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Roles") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="tab-content" id="nav-tabContent">
    <div class="related tab-pane fade m-3" id="nav-roles" role="tabpanel" aria-labelledby="nav-roles-tab"
        data-detail-tabs-target="tabContent" data-detail-tabs-target="tabContent">

        <?php if ($user->checkCan("addPermission", "Roles")) { ?>
        <button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
            data-bs-target="#addRoleModal">Add Role</button>
        <?php } ?>
        <?php if (!empty($permission->roles)) : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col"><?= __("Name") ?></th>
                        <th scope="col" class="actions"><?= __("Actions") ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permission->roles as $role) : ?>
                    <tr>
                        <td><?= h($role->name) ?></td>
                        <td class="actions">
                            <?php if ($user->checkCan("deletePermission", "Roles")) { ?>
                            <?= $this->Form->postLink(
                                            __("Remove"),
                                            [
                                                "controller" => "Roles",
                                                "action" => "deletePermission",
                                            ],
                                            [
                                                "confirm" => __(
                                                    "Are you sure you want to remove {0}?",
                                                    $role->name,
                                                ),
                                                "class" => "btn btn-danger",
                                                "data" => [
                                                    "permission_id" => $permission->id,
                                                    "role_id" => $role->id,
                                                ],
                                            ],
                                        ) ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else : ?>
        <p><?= __("No Roles Assigned") ?></p>
        <?php endif; ?>
    </div>
    <?php $this->KMP->endBlock() ?>


    <?php //Start writing to modal block in layout

    echo $this->KMP->startBlock("modals");

    echo $this->Form->create(null, [
        "url" => ["controller" => "Roles", "action" => "addPermission"],
        "data-permission-add-role-target" => "form",
        "data-controller" => "permission-add-role",
    ]);
    echo $this->Modal->create("Add Role to Permissions", [
        "id" => "addRoleModal",
        "close" => true,
    ]); ?>
    <fieldset>
        <?php
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'role_name',
            'role_id',
            $roles,
            "Role",
            true,
            false,
            [
                'data-permission-add-role-target' => 'role',
                'data-action' => 'change->permission-add-role#checkSubmitEnable',
            ]
        );
        echo $this->Form->control("permission_id", [
            "type" => "hidden",
            "value" => $permission->id,
            "id" => "add_role__permission_id",
        ]);
        ?>
    </fieldset>
    <?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "disabled" => "disabled",
            "data-permission-add-role-target" => "submitBtn",
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
            "type" => "button",
        ]),
    ]);
    echo $this->Form->end();

    echo $this->Form->create($permission, [
        "id" => "edit_entity",
        "url" => [
            "controller" => "Permissions",
            "action" => "edit",
            $permission->id,
        ],
    ]);

    echo $this->Modal->create("Edit Permissions", [
        "id" => "editModal",
        "close" => true,
    ]); ?>
    <fieldset>
        <?php
        if ($permission->is_system) {
            echo $this->Form->control("name", ["disabled" => "disabled"]);
        } else {
            echo $this->Form->control("name");
        }
        echo $this->Form->control("require_active_membership", [
            "switch" => true,
            "label" => "Require Membership",
        ]);
        echo $this->Form->control("require_active_background_check", [
            "switch" => true,
            "label" => "Require Background Check",
        ]);
        echo $this->Form->control("require_min_age", [
            "label" => "Minimum Age",
            "type" => "number",
        ]);
        if ($user->isSuperUser()) {
            echo $this->Form->control("is_super_user", ["switch" => true]);
        } else {
            echo $this->Form->control("is_super_user", [
                "switch" => true,
                "disabled" => "disabled",
            ]);
        }
        echo $this->Form->control("requires_warrant", ["switch" => true]);
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
    $this->KMP->endBlock(); ?>