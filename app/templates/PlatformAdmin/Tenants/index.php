<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var list<array<string, mixed>> $tenants
 */
$this->assign('title', __('Platform Tenants'));
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h2 mb-0"><?= __('Platform Tenants') ?></h1>
    <?= $this->Html->link(__('Create tenant'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'add'], ['class' => 'btn btn-primary']) ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th><?= __('Tenant') ?></th>
                        <th><?= __('Display Name') ?></th>
                        <th><?= __('Status') ?></th>
                        <th><?= __('Region') ?></th>
                        <th><?= __('Primary Host') ?></th>
                        <th><?= __('Schema') ?></th>
                        <th><?= __('Created') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tenants as $tenant) : ?>
                    <tr>
                        <td>
                            <?= $this->Html->link(
                                h((string)$tenant['slug']),
                                ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']],
                                ['escape' => false],
                            ) ?>
                        </td>
                        <td><?= h($tenant['display_name'] ?? '') ?></td>
                        <td><?= h($tenant['status'] ?? '') ?></td>
                        <td><?= h($tenant['region'] ?? '') ?></td>
                        <td><?= h($tenant['primary_host'] ?? '') ?></td>
                        <td><?= h($tenant['schema_version'] ?? '') ?></td>
                        <td><?= h($tenant['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($tenants === []) : ?>
                    <tr><td colspan="7" class="text-muted"><?= __('No tenants found or platform metadata is unavailable.') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
