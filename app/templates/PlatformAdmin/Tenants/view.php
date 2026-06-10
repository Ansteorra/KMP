<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var array<string, mixed> $tenant
 * @var list<array<string, mixed>> $hosts
 * @var list<array<string, mixed>> $jobs
 * @var list<array<string, mixed>> $backups
 * @var array<string, mixed>|null $provisioningJob
 */
$this->assign('title', __('Tenant: {0}', $tenant['slug']));
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1"><?= h($tenant['display_name'] ?? $tenant['slug']) ?></h1>
        <p class="text-muted mb-0"><?= __('Tenant slug: {0}', h($tenant['slug'])) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?= $this->Html->link(__('Edit tenant'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'edit', $tenant['slug']], ['class' => 'btn btn-primary']) ?>
        <?= $this->Html->link(__('Edit config'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'config', $tenant['slug']], ['class' => 'btn btn-outline-primary']) ?>
        <?= $this->Html->link(__('Backups'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'backups', $tenant['slug']], ['class' => 'btn btn-outline-primary']) ?>
        <?= $this->Html->link(__('Back to tenants'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>
</div>

<section class="row g-3 mb-4" aria-label="Tenant summary">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5"><?= __('Registry') ?></h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?= __('Status') ?></dt><dd class="col-sm-8"><?= h($tenant['status'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Region') ?></dt><dd class="col-sm-8"><?= h($tenant['region'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Primary Host') ?></dt><dd class="col-sm-8"><?= h($tenant['primary_host'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Schema') ?></dt><dd class="col-sm-8"><?= h($tenant['schema_version'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Queue Limit') ?></dt><dd class="col-sm-8"><?= h((string)($tenant['queue_concurrency_limit'] ?? '')) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5"><?= __('Lifecycle') ?></h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?= __('Created') ?></dt><dd class="col-sm-8"><?= h($tenant['created_at'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Activated') ?></dt><dd class="col-sm-8"><?= h($tenant['activated_at'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Suspended') ?></dt><dd class="col-sm-8"><?= h($tenant['suspended_at'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Archived') ?></dt><dd class="col-sm-8"><?= h($tenant['archived_at'] ?? '') ?></dd>
                    <dt class="col-sm-4"><?= __('Modified') ?></dt><dd class="col-sm-8"><?= h($tenant['modified_at'] ?? '') ?></dd>
                </dl>
            </div>
        </div>
    </div>
</section>

<section class="card mb-4" aria-labelledby="provisioning-status-heading">
    <div class="card-body">
        <h2 id="provisioning-status-heading" class="h5"><?= __('Provisioning Status') ?></h2>
        <div role="status" aria-live="polite">
            <?php if ($provisioningJob !== null) : ?>
                <p class="mb-1">
                    <?= __('Latest provisioning job: {0}', h((string)$provisioningJob['status'])) ?>
                    <?php if (!empty($provisioningJob['has_error'])) : ?>
                        <span class="badge text-bg-danger ms-2"><?= __('Needs attention') ?></span>
                    <?php endif; ?>
                </p>
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?= __('Queued') ?></dt><dd class="col-sm-9"><?= h($provisioningJob['created_at'] ?? '') ?></dd>
                    <dt class="col-sm-3"><?= __('Started') ?></dt><dd class="col-sm-9"><?= h($provisioningJob['started_at'] ?? '') ?></dd>
                    <dt class="col-sm-3"><?= __('Finished') ?></dt><dd class="col-sm-9"><?= h($provisioningJob['finished_at'] ?? '') ?></dd>
                </dl>
            <?php elseif (($tenant['status'] ?? '') === 'provisioning') : ?>
                <p class="text-warning mb-0"><?= __('This tenant is provisioning, but no provisioning job was found. Queue a new tenant through the create flow or inspect platform jobs.') ?></p>
            <?php else : ?>
                <p class="text-muted mb-0"><?= __('No recent provisioning job is recorded for this tenant.') ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="card mb-4" aria-labelledby="hosts-heading">
    <div class="card-body">
        <h2 id="hosts-heading" class="h5"><?= __('Hosts') ?></h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th><?= __('Host') ?></th><th><?= __('Primary') ?></th><th><?= __('Status') ?></th><th><?= __('Created') ?></th></tr></thead>
                <tbody>
                <?php foreach ($hosts as $host) : ?>
                    <tr><td><?= h($host['host'] ?? '') ?></td><td><?= !empty($host['is_primary']) ? __('Yes') : __('No') ?></td><td><?= h($host['status'] ?? '') ?></td><td><?= h($host['created_at'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                <?php if ($hosts === []) :
                    ?><tr><td colspan="4" class="text-muted"><?= __('No hosts found.') ?></td></tr><?php
                endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="row g-4">
    <section class="col-12 col-xl-6" aria-labelledby="tenant-jobs-heading">
        <div class="card h-100"><div class="card-body">
            <h2 id="tenant-jobs-heading" class="h5"><?= __('Recent Jobs') ?></h2>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Created') ?></th><th><?= __('Error') ?></th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $job) : ?>
                    <tr><td><?= h($job['job_type'] ?? '') ?></td><td><?= h($job['status'] ?? '') ?></td><td><?= h($job['created_at'] ?? '') ?></td><td><?= !empty($job['has_error']) ? __('Yes') : __('No') ?></td></tr>
                <?php endforeach; ?>
                <?php if ($jobs === []) :
                    ?><tr><td colspan="4" class="text-muted"><?= __('No jobs found.') ?></td></tr><?php
                endif; ?>
                </tbody>
            </table></div>
        </div></div>
    </section>

    <section class="col-12 col-xl-6" aria-labelledby="tenant-backups-heading">
        <div class="card h-100"><div class="card-body">
            <h2 id="tenant-backups-heading" class="h5"><?= __('Recent Backups') ?></h2>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Completed') ?></th><th><?= __('Retention') ?></th></tr></thead>
                <tbody>
                <?php foreach ($backups as $backup) : ?>
                    <tr><td><?= h($backup['backup_type'] ?? '') ?></td><td><?= h($backup['status'] ?? '') ?></td><td><?= h($backup['completed_at'] ?? '') ?></td><td><?= h($backup['retention_until'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                <?php if ($backups === []) :
                    ?><tr><td colspan="4" class="text-muted"><?= __('No backups found.') ?></td></tr><?php
                endif; ?>
                </tbody>
            </table></div>
        </div></div>
    </section>
</div>
