<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var bool $canManageAssignmentPermissions
 * @var bool $canViewPermissions
 * @var array $permissionOptions
 */
?>
<?php if ($canManageAssignmentPermissions && $permissionOptions !== []) : ?>
    <button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
        data-bs-target="#addPermissionModal"><?= __('Add Permission') ?></button>
<?php endif; ?>
<?php if ($gatheringActivity->assignment_permissions !== []) : ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <caption class="visually-hidden"><?= __('Permissions allowed to assign this activity') ?></caption>
            <thead>
                <tr>
                    <th scope="col" rowspan="2"><?= __('Name') ?></th>
                    <th scope="col" rowspan="2"><?= __('Scope') ?></th>
                    <th scope="colgroup" colspan="4" class="text-center table-active"><?= __('Requirements') ?></th>
                    <th scope="col" rowspan="2" class="text-center"><?= __('Super User') ?></th>
                    <th scope="col" rowspan="2" class="text-center"><?= __('System') ?></th>
                    <?php if ($canManageAssignmentPermissions) : ?>
                        <th scope="col" rowspan="2" class="actions"><?= __('Actions') ?></th>
                    <?php endif; ?>
                </tr>
                <tr>
                    <th scope="col" class="text-center"><?= __('Membership') ?></th>
                    <th scope="col" class="text-center"><?= __('Background Check') ?></th>
                    <th scope="col" class="text-center"><?= __('Minimum Age') ?></th>
                    <th scope="col" class="text-center"><?= __('Warrant') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gatheringActivity->assignment_permissions as $permission) : ?>
                    <tr data-assignment-permission-id="<?= (int)$permission->id ?>">
                        <td>
                            <?php if ($canViewPermissions) : ?>
                                <?= $this->Html->link($permission->name, [
                                    'controller' => 'Permissions', 'action' => 'view', $permission->id,
                                ]) ?>
                            <?php else : ?>
                                <?= h($permission->name) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= h($permission->scoping_rule) ?></td>
                        <?php foreach (['require_active_membership', 'require_active_background_check'] as $field) : ?>
                            <td class="text-center"><?= $this->KMP->bool((bool)$permission->{$field}, $this->Html, [
                                'role' => 'img', 'aria-label' => $permission->{$field} ? __('Yes') : __('No'),
                            ]) ?></td>
                        <?php endforeach; ?>
                        <td class="text-center"><?= h($permission->require_min_age) ?></td>
                        <?php foreach (['requires_warrant', 'is_super_user', 'is_system'] as $field) : ?>
                            <td class="text-center"><?= $this->KMP->bool((bool)$permission->{$field}, $this->Html, [
                                'role' => 'img', 'aria-label' => $permission->{$field} ? __('Yes') : __('No'),
                            ]) ?></td>
                        <?php endforeach; ?>
                        <?php if ($canManageAssignmentPermissions) : ?>
                            <td class="actions text-end text-nowrap">
                                <?= $this->Form->postLink('', [
                                    'action' => 'deleteAssignmentPermission', $gatheringActivity->id,
                                ], [
                                    'class' => 'btn btn-sm btn-danger', 'icon' => 'trash',
                                    'aria-label' => __('Remove {0}', $permission->name),
                                    'title' => __('Remove {0}', $permission->name),
                                    'confirm' => __(
                                        'Remove {0} from this activity’s assignment permissions?',
                                        $permission->name,
                                    ),
                                    'data' => ['permission_id' => $permission->id],
                                ]) ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else : ?>
    <p><?= __('No permissions configured. Gathering editors and stewards may assign this activity.') ?></p>
<?php endif; ?>
