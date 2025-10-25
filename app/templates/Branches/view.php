<?php


/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Branch $branch
 */

$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': View Branch - ' . $branch->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($branch->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock('recordActions') ?>
<?php if ($user->checkCan('edit', $branch)) : ?>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php endif; ?>
<?php if (empty($branch->children) && empty($branch->members)) {
    echo $this->Form->postLink(
        __('Delete'),
        ['action' => 'delete', $branch->id],
        [
            'confirm' => __(
                'Are you sure you want to delete {0}?',
                $branch->name,
            ),
            'title' => __('Delete'),
            'class' => 'btn btn-danger btn-sm',
        ],
    );
} ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock('recordDetails') ?>
<tr scope="row">
    <th class="col"><?= __('Type') ?></th>
    <td class="col-10"><?= h($branch->type) ?></td>
<tr scope="row">
    <th class="col"><?= __('Location') ?></th>
    <td class="col-10"><?= h($branch->location) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Parent') ?></th>
    <td class="col-10"><?= $branch->parent === null
                            ? 'Root'
                            : $this->Html->link(
                                __($branch->parent->name),
                                ['action' => 'view', $branch->parent_id],
                                ['title' => __('View')],
                            ) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Can Have Members') ?></th>
    <td class="col-10"><?= $this->KMP->bool($branch->can_have_members, $this->Html) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Email Domain') ?></th>
    <td class="col-10"><?= $branch->domain ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('links') ?></th>
    <td class="col-10">
        <?php if (!empty($branch->links)) : ?>
            <ul class='list-group'>
                <?php foreach ($branch->links as $linkItem) : ?>
                    <li class='list-group-item'>
                        <span class="bi bi-<?= $linkItem['type'] ?>"></span>
                        <a href="<?= h($linkItem['url']) ?>" title="<?= h($linkItem['url']) ?>" target="_blank">
                            <?= $linkItem['url'] ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p><?= __('No links found') ?></p>
        <?php endif; ?>
    </td>
</tr>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock('tabButtons') ?>
<!-- Branch view tabs with ordering:
     Order 1: Officers plugin tab (if enabled)
     Order 10: Members tab (primary data)
     Order 20: Sub-branches tab (secondary data) -->
<?php if ($branch->can_have_members) : ?>
    <button class="nav-link"
        id="nav-members-tab"
        data-bs-toggle="tab"
        data-bs-target="#nav-members"
        type="button"
        role="tab"
        aria-controls="nav-members"
        aria-selected="false"
        data-detail-tabs-target='tabBtn'
        data-tab-order="10"
        style="order: 10;"><?= __('Members') ?>
    </button>
<?php endif; ?>
<button class="nav-link"
    id="nav-branches-tab"
    data-bs-toggle="tab"
    data-bs-target="#nav-branches"
    type="button"
    role="tab"
    aria-controls="nav-branches"
    aria-selected="false"
    data-detail-tabs-target='tabBtn'
    data-tab-order="20"
    style="order: 20;"><?= __('Branches') ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock('tabContent') ?>
<?php if ($branch->can_have_members) : ?>
    <div class="related tab-pane fade m-3"
        id="nav-members"
        role="tabpanel"
        aria-labelledby="nav-members-tab"
        data-detail-tabs-target="tabContent"
        data-tab-order="10"
        style="order: 10;">
        <?php if (!empty($branch->members)) : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <tr>
                        <th scope="col"><?= __('Name') ?></th>
                        <th scope="col"><?= __('Minor') ?></th>
                        <th scope="col"><?= __('Membership Number') ?></th>
                        <th scope="col"><?= __('Membership Exp. Date') ?></th>
                        <th scope="col"><?= __('Status') ?></th>
                        <th scope="col" class="actions"></th>
                    </tr>
                    <?php foreach ($branch->members as $member) : ?>
                        <tr>
                            <td><?= h($member->sca_name) ?></td>
                            <td><?= $this->KMP->bool($member->age < 18, $this->Html) ?></td>
                            <td><?= h($member->membership_number) ?></td>
                            <td><?= h($member->membership_expires_on) ?>
                            </td>
                            <td><?= h($member->status) ?></td>
                            <td class="actions text-end text-nowrap">
                                <?php if ($user->checkCan('view', $member)) : ?>
                                    <?= $this->Html->link(
                                        __(''),
                                        [
                                            'controller' => 'members',
                                            'action' => 'view',
                                            $member->id,
                                        ],
                                        [
                                            'title' => __('View'),
                                            'class' => 'btn-sm btn btn-secondary bi bi-binoculars-fill',
                                        ],
                                    ) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php else : ?>
            <p><?= __('No members found') ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="related tab-pane fade m-3"
    id="nav-branches"
    role="tabpanel"
    aria-labelledby="nav-branches-tab"
    data-detail-tabs-target="tabContent"
    data-tab-order="20"
    style="order: 20;">
    <?php if (!empty($branch->children)) : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col"><?= __('Location') ?></th>
                    <th scope="col" class="actions"></th>
                </tr>
                <?php foreach ($branch->children as $child) : ?>
                    <tr>
                        <td><?= h($child->name) ?></td>
                        <td><?= h($child->location) ?></td>
                        <td class="actions text-end text-nowrap">
                            <?= $this->Html->link(
                                __(''),
                                ['action' => 'view', $child->id],
                                [
                                    'title' => __('View'),
                                    'class' => 'btn-sm btn btn-secondary bi bi-binoculars-fill',
                                ],
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php else : ?>
        <p><?= __('No branches found') ?></p>
    <?php endif; ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock('modals');

echo $this->element('branches/editModal', [
    'user' => $user,
]);

$this->KMP->endBlock(); ?>