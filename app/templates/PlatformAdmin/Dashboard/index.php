<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var array<string, mixed> $health
 * @var array<string, int> $tenantCounts
 * @var list<array<string, mixed>> $recentTenants
 * @var list<array<string, mixed>> $failedOperations
 * @var list<array<string, mixed>> $backupIssues
 * @var array<string, mixed> $releaseStatus
 */
$this->assign('title', __('Platform Admin Dashboard'));
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1"><?= __('Platform Admin Dashboard') ?></h1>
        <p class="text-muted mb-0"><?= __('Focus on tenants, backups, and platform issues that need attention.') ?></p>
    </div>
    <?= $this->Html->link(__('Manage tenants'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'], ['class' => 'btn btn-primary']) ?>
</div>

<section class="row g-3 mb-4" aria-label="Platform summary">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5"><?= __('Platform Health') ?></h2>
                <p class="fs-4 mb-1">
                    <span class="badge <?= ($health['state'] ?? '') === 'healthy' ? 'text-bg-success' : 'text-bg-warning' ?>">
                        <?= h($health['state'] ?? __('unknown')) ?>
                    </span>
                </p>
                <p class="text-muted mb-0"><?= h($health['message'] ?? '') ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5"><?= __('Tenants by Status') ?></h2>
                <?php if ($tenantCounts === []) : ?>
                    <p class="text-muted mb-0"><?= __('No tenant counts are available.') ?></p>
                <?php else : ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($tenantCounts as $status => $count) : ?>
                            <li><strong><?= h($status) ?>:</strong> <?= h((string)$count) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5"><?= __('Release Compatibility') ?></h2>
                <?php if (!empty($releaseStatus['available'])) : ?>
                    <?php $incompatibleCount = (int)($releaseStatus['incompatibleCount'] ?? 0); ?>
                    <p class="fs-4 mb-1">
                        <span class="badge <?= $incompatibleCount === 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                            <?= $incompatibleCount === 0 ? __('compatible') : __('attention needed') ?>
                        </span>
                    </p>
                    <p class="mb-1"><?= __('Version: {0}', h($releaseStatus['appVersion'])) ?></p>
                    <p class="mb-0">
                        <?= __('Active tenants: {0}; incompatible schemas: {1}', h((string)$releaseStatus['activeTenantCount']), h((string)$incompatibleCount)) ?>
                    </p>
                <?php else : ?>
                    <p class="text-muted mb-0"><?= h($releaseStatus['message'] ?? __('Unavailable')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="card mb-4" aria-labelledby="recent-tenants-heading">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <h2 id="recent-tenants-heading" class="h5 mb-0"><?= __('Recent Tenants') ?></h2>
            <?= $this->Html->link(__('View all tenants'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th><?= __('Tenant') ?></th><th><?= __('Display Name') ?></th><th><?= __('Status') ?></th><th><?= __('Region') ?></th><th><?= __('Host') ?></th><th><?= __('Schema') ?></th></tr></thead>
                <tbody>
                <?php foreach ($recentTenants as $tenant) : ?>
                    <tr>
                        <td><?= h($tenant['slug'] ?? '') ?></td>
                        <td><?= h($tenant['display_name'] ?? '') ?></td>
                        <td><?= h($tenant['status'] ?? '') ?></td>
                        <td><?= h($tenant['region'] ?? '') ?></td>
                        <td><?= h($tenant['primary_host'] ?? '') ?></td>
                        <td><?= h($tenant['schema_version'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentTenants === []) :
                    ?><tr><td colspan="6" class="text-muted"><?= __('No tenants found.') ?></td></tr><?php
                endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="row g-4">
    <section class="col-12 col-xl-6" aria-labelledby="failed-operations-heading">
        <div class="card h-100">
            <div class="card-body">
                <h2 id="failed-operations-heading" class="h5"><?= __('Failed or Stuck Operations') ?></h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th><?= __('Type') ?></th><th><?= __('Tenant') ?></th><th><?= __('Status') ?></th><th><?= __('Created') ?></th><th><?= __('Error') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($failedOperations as $job) : ?>
                            <tr>
                                <td><?= h($job['job_type'] ?? '') ?></td>
                                <td><?= h($job['tenant_slug'] ?? __('platform')) ?></td>
                                <td><?= h($job['status'] ?? '') ?></td>
                                <td><?= h($job['created_at'] ?? '') ?></td>
                                <td><?= !empty($job['has_error']) ? __('Yes') : __('No') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($failedOperations === []) :
                            ?><tr><td colspan="5" class="text-muted"><?= __('No failed or stuck operations found.') ?></td></tr><?php
                        endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="col-12 col-xl-6" aria-labelledby="backup-issues-heading">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <h2 id="backup-issues-heading" class="h5 mb-0"><?= __('Backup Issues') ?></h2>
                    <?= $this->Html->link(__('View backups'), ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th><?= __('Tenant') ?></th><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Completed') ?></th><th><?= __('Retention') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($backupIssues as $backup) : ?>
                            <tr>
                                <td><?= h($backup['tenant_slug'] ?? '') ?></td>
                                <td><?= h($backup['backup_type'] ?? '') ?></td>
                                <td><?= h($backup['status'] ?? '') ?></td>
                                <td><?= h($backup['completed_at'] ?? '') ?></td>
                                <td><?= h($backup['retention_until'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($backupIssues === []) :
                            ?><tr><td colspan="5" class="text-muted"><?= __('No backup issues found.') ?></td></tr><?php
                        endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
