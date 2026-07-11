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
 * @var list<array<string, mixed>> $provisioningEvents
 * @var array<string, int|float|bool> $metrics
 * @var list<array<string, mixed>> $metricRoutes
 * @var list<array<string, mixed>> $metricHours
 * @var string $lifecycleNonce
 */
$this->assign('title', __('Tenant: {0}', $tenant['slug']));
$latestBackup = $backups[0] ?? null;
$backupTimestamp = is_array($latestBackup) ? strtotime((string)($latestBackup['completed_at'] ?? '')) : false;
$backupCurrent = is_array($latestBackup)
    && ($latestBackup['status'] ?? '') === 'completed'
    && $backupTimestamp !== false
    && $backupTimestamp >= time() - 24 * 60 * 60;
?>
<?php if (empty($metrics['available'])) : ?>
    <div class="alert alert-warning" role="status">
        <?= __('Tenant request telemetry is unavailable. Apply the platform operational telemetry migration and verify the platform database connection.') ?>
    </div>
<?php endif; ?>
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

<section class="ops-stat-grid mb-4" aria-label="<?= __('Tenant operating summary for the last 24 hours') ?>">
    <article class="ops-stat ops-stat--traffic">
        <div class="ops-stat__label"><?= __('Requests') ?></div>
        <div class="ops-stat__value"><?= h(number_format((int)$metrics['request_count'])) ?></div>
        <p class="ops-stat__detail"><?= __('Privacy-safe routed request aggregates') ?></p>
    </article>
    <article class="ops-stat ops-stat--fleet">
        <div class="ops-stat__label"><?= __('Error rate') ?></div>
        <div class="ops-stat__value"><?= h(number_format((float)$metrics['error_rate'], 2)) ?><span class="ops-stat__unit">%</span></div>
        <p class="ops-stat__detail"><?= __('{0} errors; {1} were server errors', h(number_format((int)$metrics['error_count'])), h(number_format((int)$metrics['server_error_count']))) ?></p>
    </article>
    <article class="ops-stat ops-stat--latency">
        <div class="ops-stat__label"><?= __('Average response') ?></div>
        <div class="ops-stat__value"><?= h((string)$metrics['average_duration_ms']) ?><span class="ops-stat__unit">ms</span></div>
        <p class="ops-stat__detail"><?= __('Maximum {0} ms; {1} slow requests', h((string)$metrics['duration_max_ms']), h(number_format((int)$metrics['slow_request_count']))) ?></p>
    </article>
    <article class="ops-stat ops-stat--protection">
        <div class="ops-stat__label"><?= __('Backup protection') ?></div>
        <div class="ops-stat__value"><?= $backupCurrent ? __('24h') : __('—') ?></div>
        <p class="ops-stat__detail"><?= $backupCurrent ? __('A retained backup completed in the last day.') : __('No current retained backup is available.') ?></p>
    </article>
</section>

<details class="ops-panel mb-4">
    <summary class="ops-panel__header fw-semibold"><?= __('Inspect hourly request trend') ?></summary>
    <div class="table-responsive">
        <table class="table ops-table align-middle mb-0">
            <caption class="visually-hidden"><?= __('Hourly tenant request, error, and average response totals') ?></caption>
            <thead><tr><th scope="col"><?= __('Hour (UTC)') ?></th><th scope="col" class="text-end"><?= __('Requests') ?></th><th scope="col" class="text-end"><?= __('Errors') ?></th><th scope="col" class="text-end"><?= __('Server errors') ?></th><th scope="col" class="text-end"><?= __('Average') ?></th></tr></thead>
            <tbody>
            <?php foreach ($metricHours as $hour) : ?>
                <?php $hourRequests = max(1, (int)($hour['request_count'] ?? 0)); ?>
                <tr>
                    <td><?= h($hour['metric_hour'] ?? '') ?></td>
                    <td class="text-end ops-number"><?= h(number_format((int)($hour['request_count'] ?? 0))) ?></td>
                    <td class="text-end ops-number"><?= h(number_format((int)($hour['error_count'] ?? 0))) ?></td>
                    <td class="text-end ops-number"><?= h(number_format((int)($hour['server_error_count'] ?? 0))) ?></td>
                    <td class="text-end ops-number"><?= h((string)(int)round((int)($hour['duration_total_ms'] ?? 0) / $hourRequests)) ?> ms</td>
                </tr>
            <?php endforeach; ?>
            <?php if ($metricHours === []) : ?>
                <tr><td colspan="5" class="ops-empty"><?= __('No hourly traffic aggregates are available.') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</details>

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
                <?php if (($tenant['status'] ?? '') === 'active') : ?>
                    <hr>
                    <?= $this->Form->create(null, [
                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'suspend', $tenant['slug']],
                    ]) ?>
                    <?= $this->Form->hidden('nonce', ['value' => $lifecycleNonce]) ?>
                    <?= $this->Form->control('confirmation', ['label' => __('Type SUSPEND {0}', $tenant['slug'])]) ?>
                    <?= $this->Form->control('reason', ['label' => __('Operator reason'), 'required' => true]) ?>
                    <?= $this->Form->control('totp', ['label' => __('MFA code'), 'autocomplete' => 'one-time-code']) ?>
                    <?= $this->Form->button(__('Suspend tenant'), ['class' => 'btn btn-warning']) ?>
                    <?= $this->Form->end() ?>
                <?php elseif (($tenant['status'] ?? '') === 'suspended') : ?>
                    <hr>
                    <div class="d-grid gap-3">
                        <?= $this->Form->create(null, [
                            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'reactivate', $tenant['slug']],
                        ]) ?>
                        <?= $this->Form->hidden('nonce', ['value' => $lifecycleNonce]) ?>
                        <?= $this->Form->control('confirmation', ['label' => __('Type REACTIVATE {0}', $tenant['slug'])]) ?>
                        <?= $this->Form->control('reason', ['label' => __('Reactivation reason'), 'required' => true]) ?>
                        <?= $this->Form->control('totp', ['label' => __('Reactivation MFA code'), 'autocomplete' => 'one-time-code']) ?>
                        <?= $this->Form->button(__('Reactivate tenant'), ['class' => 'btn btn-success']) ?>
                        <?= $this->Form->end() ?>

                        <?= $this->Form->create(null, [
                            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'archive', $tenant['slug']],
                        ]) ?>
                        <?= $this->Form->hidden('nonce', ['value' => $lifecycleNonce]) ?>
                        <?= $this->Form->control('confirmation', ['label' => __('Type ARCHIVE {0}', $tenant['slug'])]) ?>
                        <?= $this->Form->control('reason', ['label' => __('Archival reason'), 'required' => true]) ?>
                        <?= $this->Form->control('totp', ['label' => __('Archival MFA code'), 'autocomplete' => 'one-time-code']) ?>
                        <?= $this->Form->button(__('Archive tenant'), ['class' => 'btn btn-outline-danger']) ?>
                        <?= $this->Form->end() ?>
                    </div>
                <?php elseif (($tenant['status'] ?? '') === 'provisioning') : ?>
                    <hr>
                    <?= $this->Form->create(null, [
                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'archive', $tenant['slug']],
                    ]) ?>
                    <?= $this->Form->hidden('nonce', ['value' => $lifecycleNonce]) ?>
                    <?= $this->Form->control('confirmation', ['label' => __('Type ARCHIVE {0}', $tenant['slug'])]) ?>
                    <?= $this->Form->control('reason', ['label' => __('Archival reason'), 'required' => true]) ?>
                    <?= $this->Form->control('totp', ['label' => __('Archival MFA code'), 'autocomplete' => 'one-time-code']) ?>
                    <?= $this->Form->button(__('Archive incomplete tenant'), ['class' => 'btn btn-outline-danger']) ?>
                    <?= $this->Form->end() ?>
                <?php endif; ?>
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
        <?php if ($provisioningEvents !== []) : ?>
            <ol class="list-group list-group-numbered mt-3" aria-label="<?= __('Provisioning progress') ?>">
                <?php foreach ($provisioningEvents as $event) : ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <strong><?= h($event['message'] ?? '') ?></strong>
                            <div class="small text-muted"><?= h(str_replace('.', ' ', (string)($event['event_code'] ?? ''))) ?></div>
                        </div>
                        <span class="ops-inline-state <?= ($event['event_level'] ?? '') === 'error' ? 'ops-inline-state--bad' : 'ops-inline-state--good' ?>">
                            <?= h($event['event_level'] ?? '') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>

<section class="ops-panel mb-4" aria-labelledby="route-analytics-heading">
    <div class="ops-panel__header">
        <div>
            <p class="ops-panel__kicker mb-1"><?= __('Privacy-safe request analytics') ?></p>
            <h2 id="route-analytics-heading" class="h4 mb-0"><?= __('Routes needing support review') ?></h2>
        </div>
        <span class="text-muted small"><?= __('Last 24 hours · no URLs, users, or request bodies stored') ?></span>
    </div>
    <div class="table-responsive">
        <table class="table ops-table align-middle mb-0">
            <thead><tr><th scope="col"><?= __('Routed action') ?></th><th scope="col" class="text-end"><?= __('Requests') ?></th><th scope="col" class="text-end"><?= __('Errors') ?></th><th scope="col" class="text-end"><?= __('Server errors') ?></th><th scope="col" class="text-end"><?= __('Average') ?></th><th scope="col" class="text-end"><?= __('Maximum') ?></th></tr></thead>
            <tbody>
            <?php foreach ($metricRoutes as $route) : ?>
                <?php $routeRequests = max(1, (int)($route['request_count'] ?? 0)); ?>
                <tr>
                    <td class="fw-semibold"><?= h($route['route_name'] ?? '') ?></td>
                    <td class="text-end ops-number"><?= h(number_format((int)($route['request_count'] ?? 0))) ?></td>
                    <td class="text-end ops-number"><?= h(number_format((int)($route['error_count'] ?? 0))) ?></td>
                    <td class="text-end ops-number"><?= h(number_format((int)($route['server_error_count'] ?? 0))) ?></td>
                    <td class="text-end ops-number"><?= h((string)(int)round((int)($route['duration_total_ms'] ?? 0) / $routeRequests)) ?> ms</td>
                    <td class="text-end ops-number"><?= h((string)($route['duration_max_ms'] ?? 0)) ?> ms</td>
                </tr>
            <?php endforeach; ?>
            <?php if ($metricRoutes === []) : ?>
                <tr><td colspan="6" class="ops-empty"><?= __('No routed tenant traffic was recorded during this window.') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
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
