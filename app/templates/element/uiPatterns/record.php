<?php
declare(strict_types=1);

/** The same record structure supports branch, member, and sparse configuration records. */
$recordExamples = [
    'branch' => [
        'title' => 'Barony of Oakmere', 'type' => 'Branch',
        'description' => 'North Texas · Kingdom of Ansteorra',
        'facts' => ['Type' => 'Barony', 'Region' => 'North Texas', 'Parent branch' => 'Ansteorra'],
        'sections' => ['officers' => 'Officers', 'members' => 'Members', 'gatherings' => 'Gatherings'],
    ],
    'member' => [
        'title' => 'Elian of Oakmere', 'type' => 'Member',
        'description' => 'Barony of Oakmere · they/them',
        'facts' => ['Membership' => 'Current through May 31, 2027', 'Verification' => 'Membership verified',
            'Account' => 'Active', 'Branch' => 'Barony of Oakmere'],
        'sections' => ['authorizations' => 'Authorizations', 'roles' => 'Roles', 'gatherings' => 'Gatherings'],
    ],
    'role' => [
        'title' => 'Branch Seneschal', 'type' => 'Role',
        'description' => 'Permissions and member assignments for branch administration.',
        'facts' => ['Assignments' => '3 active members', 'Branch scope' => 'Set by each assignment'],
        'sections' => ['permissions' => 'Permissions', 'members' => 'Members'],
    ],
];
$example = $recordExamples[$recordKind];
$sections = $example['sections'];
$canManage = $showcaseState !== 'restricted';
if (!$canManage) {
    unset($sections['members'], $sections['roles']);
}
$firstSection = array_key_first($sections);
$readerUrl = $this->Url->build(['controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'reader']]);
echo $this->element('uiPatterns/pageHeader', [
    'back' => true,
    'eyebrow' => __($example['type']),
    'title' => $example['title'],
    'description' => $example['description'],
    'badges' => $recordKind === 'member' ? [['label' => __('Membership current'), 'tone' => 'success']] : [],
    'actions' => $canManage ? [[
        'label' => __('Edit {0}', strtolower($example['type'])), 'icon' => 'pencil', 'primary' => true,
        'modal' => 'record-edit',
    ]] : [],
]);
?>
<dl class="ui-summary" aria-label="<?= __('Record summary') ?>">
    <?php foreach ($example['facts'] as $label => $value) : ?>
        <div><dt><?= h(__($label)) ?></dt><dd><?= h($value) ?></dd></div>
    <?php endforeach; ?>
</dl>
<?php if ($recordKind === 'member' && $canManage) : ?>
    <details class="ui-surface ui-contact-details mb-4">
        <summary><?= $this->Icon->render('lock') ?> <?= __('Contact information') ?></summary>
        <p class="small text-muted mt-3">
            <?= __('Visible to this member and staff with access to personal information.') ?>
        </p>
        <dl class="ui-summary mb-0">
            <div><dt><?= __('Email') ?></dt><dd>elian@example.test</dd></div>
            <div><dt><?= __('Phone') ?></dt><dd>(555) 010-4821</dd></div>
        </dl>
    </details>
<?php endif; ?>
<div class="ui-section-nav">
    <div class="nav nav-tabs" role="tablist" aria-label="<?= h(__('{0} sections', $example['type'])) ?>">
        <?php foreach ($sections as $key => $label) : ?>
            <?php $selected = $key === $firstSection; ?>
            <button class="nav-link<?= $selected ? ' active' : '' ?>"
                id="record-<?= h($recordKind . '-' . $key) ?>-tab" type="button" role="tab"
                data-bs-toggle="tab" data-bs-target="#record-<?= h($recordKind . '-' . $key) ?>"
                aria-controls="record-<?= h($recordKind . '-' . $key) ?>"
                aria-selected="<?= $selected ? 'true' : 'false' ?>">
                <?= h(__($label)) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>
<div class="tab-content">
    <?php foreach ($sections as $key => $label) : ?>
        <section class="tab-pane<?= $key === $firstSection ? ' show active' : '' ?>"
            id="record-<?= h($recordKind . '-' . $key) ?>" role="tabpanel"
            aria-labelledby="record-<?= h($recordKind . '-' . $key) ?>-tab" tabindex="0">
            <div class="ui-section-heading">
                <h2><?= h(__($label)) ?></h2>
                <?php if ($key === 'permissions' && $canManage && $showcaseState !== 'error') : ?>
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal"
                        data-bs-target="#record-add-permission">
                        <?= $this->Icon->render('plus') ?> <?= __('Add permission') ?>
                    </button>
                <?php elseif ($key === 'members' && $canManage) : ?>
                    <button class="btn btn-outline-primary" type="button" data-action="ui-patterns#notice"
                        data-message="<?= __('Choose a member and branch scope to create an assignment.') ?>">
                        <?= $this->Icon->render('user-plus') ?> <?= __('Assign member') ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($key === 'gatherings' && $recordKind === 'branch') : ?>
                <?= $this->element('uiPatterns/gatherings_grid') ?>
            <?php elseif ($showcaseState === 'error') : ?>
                <div class="alert alert-danger" role="alert">
                    <p class="mb-2">
                        <?= h(__('{0} could not be loaded. The record summary is still available.', $label)) ?>
                    </p>
                    <a class="btn btn-outline-danger" href="<?= h($this->Url->build([
                        'controller' => 'UiPatterns', 'action' => 'index',
                        '?' => ['pattern' => 'record', 'record' => $recordKind],
                    ])) ?>"><?= __('Try again') ?></a>
                </div>
            <?php elseif ($showcaseState === 'empty' && $key !== 'permissions') : ?>
                <div class="ui-surface ui-empty">
                    <h3><?= h(__('No {0} yet', strtolower($label))) ?></h3>
                    <p><?= __('New entries will appear here when they are added.') ?></p>
                </div>
            <?php elseif ($key === 'permissions') : ?>
                <p class="text-muted">
                    <?= __('Permissions apply within the branch scope of each member’s role assignment.') ?>
                </p>
                <div class="ui-surface table-responsive">
                    <table class="table ui-table mb-0">
                        <caption class="visually-hidden"><?= __('Permissions granted by this role') ?></caption>
                        <thead><tr>
                            <th scope="col"><?= __('Permission') ?></th><th scope="col"><?= __('Scope') ?></th>
                        </tr></thead>
                        <tbody data-ui-patterns-target="permissions">
                            <?php if ($showcaseState !== 'empty') : ?>
                                <tr><th scope="row"><?= __('Manage gatherings') ?></th>
                                    <td><?= __('Assigned branch') ?></td></tr>
                                <tr><th scope="row"><?= __('View members') ?></th>
                                    <td><?= __('Assigned branch') ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($key === 'officers') : ?>
                <div class="ui-related-list ui-surface">
                    <?php foreach (
                    [['Seneschal', 'Alys of Oakmere', 'Sep 1, 2027'],
                        ['Exchequer', 'Rowan of Willowford', 'Mar 31, 2027'],
                        ['Knight Marshal', 'Elian of Oakmere', 'Jun 30, 2027']] as [$office, $name, $through]
) : ?>
                        <div class="ui-related-row">
                            <div><h3 class="h6 mb-1"><?= h($office) ?></h3><p class="mb-0"><?= h($name) ?></p></div>
                            <div><span class="ui-status ui-status-success"><?= __('Active') ?></span>
                                <p class="small text-muted mt-1 mb-0"><?= h(__('Through {0}', $through)) ?></p></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($key === 'members') : ?>
                <div class="ui-related-list ui-surface">
                    <?php foreach (['Alys of Oakmere', 'Elian of Oakmere', 'Rowan of Willowford'] as $name) : ?>
                        <div class="ui-related-row">
                            <div><h3 class="h6 mb-1"><?= h($name) ?></h3>
                                <p class="small text-muted mb-0"><?= __('Barony of Oakmere') ?></p></div>
                            <span class="ui-status ui-status-success">
                                <?= $recordKind === 'role' ? __('Active assignment') : __('Member') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($key === 'authorizations') : ?>
                <div class="ui-related-list ui-surface">
                    <?php foreach (
                    [['Armored combat', 'armored-combat', 'Feb 28, 2027'],
                        ['Archery', 'archery', 'May 31, 2027']] as [$activity, $icon, $through]
) : ?>
                        <div class="ui-related-row">
                            <div><h3 class="h6 mb-1"><?= $this->Icon->render($icon) ?> <?= h($activity) ?></h3>
                                <p class="small text-muted mb-0"><?= h(__('Expires {0}', $through)) ?></p></div>
                            <span class="ui-status ui-status-success"><?= __('Current') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($key === 'roles') : ?>
                <div class="ui-related-list ui-surface">
                    <div class="ui-related-row">
                        <div><h3 class="h6 mb-1"><?= __('Branch Marshal') ?></h3>
                            <p class="small text-muted mb-0"><?= __('Barony of Oakmere · assigned branch') ?></p></div>
                        <span class="ui-status ui-status-success"><?= __('Active') ?></span>
                    </div>
                </div>
            <?php elseif ($key === 'gatherings') : ?>
                <div class="ui-related-list ui-surface">
                    <div class="ui-related-row">
                        <div><h3 class="h6 mb-1">
                            <a href="<?= h($readerUrl) ?>"><?= __('Oakmere Winter Court') ?></a></h3>
                            <p class="small text-muted mb-0"><?= __('Dec 5, 2026 · Barony of Oakmere') ?></p></div>
                        <span class="ui-status ui-status-neutral"><?= __('Upcoming') ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php if ($canManage) : ?>
    <div class="modal fade" id="record-edit" tabindex="-1" aria-labelledby="record-edit-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
            <form class="modal-content" data-action="submit->ui-patterns#savePreview"
                data-update-target=".ui-page-header h1" data-update-field="name"
                data-message="<?= __('Name updated in this review session.') ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="record-edit-title">
                        <?= h(__('Edit {0}', strtolower($example['type']))) ?>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?= __('Close') ?>"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="record-edit-name">
                        <?= $recordKind === 'member' ? __('SCA name') : __('Name') ?>
                    </label>
                    <input class="form-control" id="record-edit-name" name="name"
                        value="<?= h($example['title']) ?>" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                        <?= __('Cancel') ?>
                    </button>
                    <button class="btn btn-primary" type="submit"><?= __('Save changes') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php if ($recordKind === 'role' && $canManage) : ?>
    <div class="modal fade" id="record-add-permission" tabindex="-1"
        aria-labelledby="record-add-permission-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
            <form class="modal-content" data-action="submit->ui-patterns#addPermission">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="record-add-permission-title"><?= __('Add permission') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?= __('Close') ?>"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="record-permission"><?= __('Permission') ?></label>
                    <select class="form-select mb-3" id="record-permission" name="permission" required>
                        <option value=""><?= __('Choose a permission') ?></option>
                        <option data-scope="<?= __('Assigned branch') ?>">
                            <?= __('Manage gathering activities') ?>
                        </option>
                        <option data-scope="<?= __('Assigned branch') ?>">
                            <?= __('View member authorizations') ?>
                        </option>
                        <option data-scope="<?= __('Assigned branch') ?>">
                            <?= __('View officer warrants') ?>
                        </option>
                    </select>
                    <p class="small text-muted mt-3 mb-0">
                        <?= __('Scope comes from the permission’s settings; these examples use the assigned branch.') ?>
                        <?= __('The member’s role assignment supplies the branch for this scope.') ?>
                    </p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                        <?= __('Cancel') ?>
                    </button>
                    <button class="btn btn-primary" type="submit"><?= __('Add permission') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
