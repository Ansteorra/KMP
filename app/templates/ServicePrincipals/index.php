<?php

/**
 * Service Principals Index Template
 * 
 * Lists all service principals for API integration management.
 * 
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\ServicePrincipal> $servicePrincipals
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Service Principals';
$this->KMP->endBlock();

$this->assign('title', __('Service Principals'));
?>

<div class="service-principals index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Service Principals') ?></h3>
        <div>
            <?php if ($user->checkCan("add", "ServicePrincipals")) : ?>
                <?= $this->Html->link(
                    __(' Add Service Principal'),
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary bi bi-plus-circle']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-3">
                <i class="bi bi-info-circle"></i>
                <?= __('Service principals are non-human identities for third-party API integrations. They authenticate using Bearer tokens and can be assigned roles to control access.') ?>
            </p>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Client ID') ?></th>
                            <th><?= __('Status') ?></th>
                            <th><?= __('Last Used') ?></th>
                            <th><?= __('Created') ?></th>
                            <th class="text-end"><?= __('Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicePrincipals as $sp) : ?>
                            <tr>
                                <td>
                                    <?= $this->Html->link(
                                        h($sp->name),
                                        ['action' => 'view', $sp->id],
                                        ['class' => 'fw-bold']
                                    ) ?>
                                    <?php if ($sp->description) : ?>
                                        <br><small class="text-muted"><?= h(\Cake\Utility\Text::truncate($sp->description, 60)) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= h($sp->client_id) ?></code></td>
                                <td>
                                    <?php if ($sp->is_active) : ?>
                                        <span class="badge bg-success"><?= __('Active') ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?= __('Inactive') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $sp->last_used_at ? $sp->last_used_at->timeAgoInWords() : __('Never') ?>
                                </td>
                                <td>
                                    <?= $sp->created->toDateString() ?>
                                    <?php if ($sp->created_by_member) : ?>
                                        <br><small class="text-muted"><?= __('by') ?> <?= h($sp->created_by_member->sca_name) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye"></i>',
                                        ['action' => 'view', $sp->id],
                                        ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => __('View')]
                                    ) ?>
                                    <?php if ($user->checkCan("edit", $sp)) : ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil"></i>',
                                            ['action' => 'edit', $sp->id],
                                            ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => __('Edit')]
                                        ) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($servicePrincipals->toArray())) : ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-key h1 d-block mb-2"></i>
                                    <?= __('No service principals configured yet.') ?>
                                    <?php if ($user->checkCan("add", "ServicePrincipals")) : ?>
                                        <br>
                                        <?= $this->Html->link(__('Create your first service principal'), ['action' => 'add']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="paginator">
                <?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} of {{count}} service principals')) ?>
                <ul class="pagination">
                    <?= $this->Paginator->first('«', ['class' => 'page-link']) ?>
                    <?= $this->Paginator->prev('‹', ['class' => 'page-link']) ?>
                    <?= $this->Paginator->numbers(['class' => 'page-link']) ?>
                    <?= $this->Paginator->next('›', ['class' => 'page-link']) ?>
                    <?= $this->Paginator->last('»', ['class' => 'page-link']) ?>
                </ul>
            </div>
        </div>
    </div>
</div>
