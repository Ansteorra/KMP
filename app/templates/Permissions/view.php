<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission $permission
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");
$user = $this->request->getAttribute("identity");

?>
<div class="permissions view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
                <?= h($permission->name) ?>
            </h3>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#editModal">Edit</button>
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
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
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
        </table>
    </div>
    <nav>
        <div class="nav nav-tabs" id="nav-permissionAreas" role="tablist">
            <button class="nav-link active" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles"
                type="button" role="tab" aria-controls="nav-roles" aria-selected="true"><?= __("Roles") ?>
            </button>
            <?= $this->element('pluginTabButtons', [
                'pluginViewCells' => $pluginViewCells,
                'activateFirst' => false,
            ]) ?>

        </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <div class="related tab-pane fade show active m-3" id="nav-roles" role="tabpanel"
            aria-labelledby="nav-roles-tab">

            <?php if ($user->can("addPermission", "Roles")) { ?>
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
                                <?php if ($user->can("deletePermission", "Roles")) { ?>
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
        <?= $this->element('pluginTabBodies', [
            'pluginViewCells' => $pluginViewCells,
            'id' => $permission->id,
            'activateFirst' => false,
        ]) ?>
    </div>


    <?php //Start writing to modal block in layout

    echo $this->KMP->startBlock("modals"); ?>

    <?php echo $this->Modal->create("Add Role to Permissions", [
        "id" => "addRoleModal",
        "close" => true,
    ]); ?>
    <fieldset>
        <?php
        echo $this->Form->create(null, [
            "id" => "add_role__form",
            "url" => ["controller" => "Roles", "action" => "addPermission"],
        ]);
        echo $this->Form->control("role_id", [
            "options" => $roles,
            "empty" => true,
            "id" => "add_role__role_id",
        ]);
        echo $this->Form->control("permission_id", [
            "type" => "hidden",
            "value" => $permission->id,
            "id" => "add_role__permission_id",
        ]);
        echo $this->Form->end();
        ?>
    </fieldset>
    <?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "add_role__submit",
            "disabled" => "disabled",
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]); ?>

    <?php echo $this->Modal->create("Edit Permissions", [
        "id" => "editModal",
        "close" => true,
    ]); ?>
    <fieldset>
        <?php
        echo $this->Form->create($permission, [
            "id" => "edit_entity",
            "url" => [
                "controller" => "Permissions",
                "action" => "edit",
                $permission->id,
            ],
        ]);
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
        echo $this->Form->end();
        ?>
    </fieldset>
    <?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "edit_entity__submit",
            "onclick" => '$("#edit_entity").submit();',
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]); ?>


    <?php //finish writing to modal block in layout

    $this->KMP->endBlock(); ?>

    <?php $this->append(
        "script",
        $this->Html->script(["app/permissions/view.js"]),
    ); ?>