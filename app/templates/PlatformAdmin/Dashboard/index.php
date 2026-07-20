<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var array<string, mixed> $health
 * @var array<string, mixed> $fleet
 * @var bool $operationalDataAvailable
 * @var array{ready: bool, checks: list<array{name: string, ready: bool, detail: string}>} $backupReadiness
 * @var array<string, mixed> $releaseStatus
 */
$this->assign('title', __('Platform Operations'));
$summary = (array)($fleet['summary'] ?? []);
$tenants = (array)($fleet['tenants'] ?? []);
$attentionTenants = array_values(array_filter(
    $tenants,
    static fn(array $tenant): bool => in_array($tenant['risk_level'] ?? '', ['critical', 'warning'], true),
));
$attentionTenants = array_slice($attentionTenants, 0, 12);
$operationIssues = (array)($fleet['operation_issues'] ?? []);
$scheduleIssues = (array)($fleet['schedule_issues'] ?? []);
$platformBackup = is_array($fleet['platform_backup'] ?? null) ? $fleet['platform_backup'] : null;
$errorRate = (float)($summary['error_rate_24h'] ?? 0);
$backupCoverage = (float)($summary['backup_coverage_percent'] ?? 0);
?>

<header class="ops-hero mb-4">
    <div class="ops-hero__eyebrow"><?= __('Kingdom fleet control') ?></div>
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-4">
        <div>
            <h1 class="ops-hero__title"><?= __('Platform operations') ?></h1>
            <p class="ops-hero__lead mb-0">
                <?= __('Tenant readiness, traffic quality, data protection, and worker health in one support queue.') ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?= $this->Html->link(__('Onboard a kingdom'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'add'], ['class' => 'btn btn-warning']) ?>
            <?= $this->Html->link(__('Review all tenants'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'], ['class' => 'btn btn-outline-light']) ?>
        </div>
    </div>
    <div class="ops-hero__status mt-4" role="status">
        <span class="ops-signal <?= ($health['state'] ?? '') === 'healthy' ? 'ops-signal--good' : 'ops-signal--critical' ?>" aria-hidden="true"></span>
        <strong><?= __('Metadata plane: {0}', h($health['state'] ?? __('unknown'))) ?></strong>
        <span><?= h($health['message'] ?? '') ?></span>
        <?php if (!empty($fleet['generated_at'])) : ?>
            <span class="ms-xl-auto"><?= __('Snapshot {0} UTC', h((string)$fleet['generated_at'])) ?></span>
        <?php endif; ?>
    </div>
</header>

<?php if (!$operationalDataAvailable) : ?>
    <div class="alert alert-danger" role="alert">
        <strong><?= __('Operational data is unavailable.') ?></strong>
        <?= __('Platform metadata is reachable, but the fleet snapshot could not be assembled. Check platform migrations and the scheduler.') ?>
    </div>
<?php endif; ?>
<?php if ($operationalDataAvailable && empty($fleet['telemetry_available'])) : ?>
    <div class="alert alert-warning" role="status">
        <?= __('Tenant request telemetry is unavailable. Fleet lifecycle and backup signals remain visible, but usage, errors, and performance are not being reported.') ?>
    </div>
<?php endif; ?>

<section class="ops-stat-grid mb-4" aria-label="<?= __('Fleet summary for the last 24 hours') ?>">
    <article class="ops-stat ops-stat--fleet">
        <div class="ops-stat__label"><?= __('Fleet attention') ?></div>
        <div class="ops-stat__value"><?= h((string)($summary['attention_tenants'] ?? 0)) ?></div>
        <p class="ops-stat__detail">
            <?= __('{0} critical / {1} active tenants', h((string)($summary['critical_tenants'] ?? 0)), h((string)($summary['active_tenants'] ?? 0))) ?>
        </p>
    </article>
    <article class="ops-stat ops-stat--traffic">
        <div class="ops-stat__label"><?= __('Traffic quality') ?></div>
        <div class="ops-stat__value"><?= h(number_format((int)($summary['requests_24h'] ?? 0))) ?></div>
        <p class="ops-stat__detail">
            <?= __('requests / {0}% errors / {1} ms average', h(number_format($errorRate, 2)), h((string)($summary['average_duration_ms_24h'] ?? 0))) ?>
        </p>
    </article>
    <article class="ops-stat ops-stat--protection">
        <div class="ops-stat__label"><?= __('Backup coverage') ?></div>
        <div class="ops-stat__value"><?= h(number_format($backupCoverage, 1)) ?><span class="ops-stat__unit">%</span></div>
        <p class="ops-stat__detail">
            <?= __('{0} active tenants protected within 24 hours', h((string)($summary['fresh_backups'] ?? 0))) ?>
        </p>
    </article>
    <article class="ops-stat ops-stat--latency">
        <div class="ops-stat__label"><?= __('Performance ceiling') ?></div>
        <div class="ops-stat__value"><?= h((string)($summary['max_duration_ms_24h'] ?? 0)) ?><span class="ops-stat__unit">ms</span></div>
        <p class="ops-stat__detail">
            <?= __('{0} slow requests / {1} server errors', h(number_format((int)($summary['slow_requests_24h'] ?? 0))), h(number_format((int)($summary['server_errors_24h'] ?? 0)))) ?>
        </p>
    </article>
</section>

<div class="row g-4 mb-4">
    <section class="col-12 col-xxl-8" aria-labelledby="tenant-attention-heading">
        <div class="ops-panel h-100">
            <div class="ops-panel__header">
                <div>
                    <p class="ops-panel__kicker mb-1"><?= __('Prioritized support queue') ?></p>
                    <h2 id="tenant-attention-heading" class="h4 mb-0"><?= __('Kingdoms needing attention') ?></h2>
                </div>
                <?= $this->Html->link(__('Fleet view'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <div class="table-responsive">
                <table class="table ops-table align-middle mb-0">
                    <caption class="visually-hidden"><?= __('Tenants ordered by operational risk') ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= __('Kingdom') ?></th>
                            <th scope="col"><?= __('Risk') ?></th>
                            <th scope="col" class="text-end"><?= __('Requests') ?></th>
                            <th scope="col" class="text-end"><?= __('Errors') ?></th>
                            <th scope="col" class="text-end"><?= __('Avg.') ?></th>
                            <th scope="col"><?= __('Protection') ?></th>
                            <th scope="col"><?= __('Why it is here') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attentionTenants as $tenant) : ?>
                        <?php $risk = (string)($tenant['risk_level'] ?? 'warning'); ?>
                        <tr>
                            <td>
                                <?= $this->Html->link(
                                    h((string)($tenant['display_name'] ?? $tenant['slug'] ?? '')),
                                    ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']],
                                    ['class' => 'fw-semibold', 'escape' => false],
                                ) ?>
                                <div class="text-muted small"><?= h($tenant['slug'] ?? '') ?></div>
                            </td>
                            <td><span class="ops-risk ops-risk--<?= h($risk) ?>"><?= h($risk) ?></span></td>
                            <td class="text-end ops-number"><?= h(number_format((int)($tenant['request_count'] ?? 0))) ?></td>
                            <td class="text-end ops-number"><?= h(number_format((int)($tenant['error_count'] ?? 0))) ?> <span class="text-muted small">(<?= h(number_format((float)($tenant['error_rate'] ?? 0), 1)) ?>%)</span></td>
                            <td class="text-end ops-number"><?= h((string)($tenant['average_duration_ms'] ?? 0)) ?> ms</td>
                            <td>
                                <?php if (!empty($tenant['backup_fresh'])) : ?>
                                    <span class="ops-inline-state ops-inline-state--good"><?= __('Current') ?></span>
                                <?php else : ?>
                                    <span class="ops-inline-state ops-inline-state--bad"><?= h($tenant['backup_status'] ?? __('Missing')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ops-reason"><?= h(implode(' ', (array)($tenant['attention'] ?? []))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($attentionTenants === []) : ?>
                        <tr><td colspan="7" class="ops-empty"><?= __('No tenants currently cross the support thresholds.') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <aside class="col-12 col-xxl-4" aria-labelledby="backup-readiness-heading">
        <div class="ops-panel h-100">
            <div class="ops-panel__header">
                <div>
                    <p class="ops-panel__kicker mb-1"><?= __('Resilience preflight') ?></p>
                    <h2 id="backup-readiness-heading" class="h4 mb-0"><?= __('Backup execution') ?></h2>
                </div>
                <span class="ops-risk <?= $backupReadiness['ready'] ? 'ops-risk--healthy' : 'ops-risk--critical' ?>">
                    <?= $backupReadiness['ready'] ? __('ready') : __('blocked') ?>
                </span>
            </div>
            <ul class="ops-checklist">
                <?php foreach ($backupReadiness['checks'] as $check) : ?>
                    <li>
                        <span class="ops-checklist__mark <?= $check['ready'] ? 'ops-checklist__mark--good' : 'ops-checklist__mark--bad' ?>" aria-hidden="true">
                            <?= $check['ready'] ? 'OK' : '!' ?>
                        </span>
                        <div>
                            <strong><?= h(str_replace('_', ' ', $check['name'])) ?></strong>
                            <span><?= h($check['detail']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="ops-panel__footer">
                <?= $this->Html->link(__('Open backup operations'), ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups'], ['class' => 'btn btn-dark w-100']) ?>
            </div>
        </div>
    </aside>
</div>

<div class="row g-4">
    <section class="col-12 col-xl-6" aria-labelledby="operation-issues-heading">
        <div class="ops-panel h-100">
            <div class="ops-panel__header">
                <div>
                    <p class="ops-panel__kicker mb-1"><?= __('Async data plane') ?></p>
                    <h2 id="operation-issues-heading" class="h4 mb-0"><?= __('Failed or stalled jobs') ?></h2>
                </div>
                <?= $this->Html->link(__('All jobs'), ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'jobs'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <div class="table-responsive">
                <table class="table ops-table align-middle mb-0">
                    <thead><tr><th scope="col"><?= __('Operation') ?></th><th scope="col"><?= __('Scope') ?></th><th scope="col"><?= __('Status') ?></th><th scope="col"><?= __('Queued') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($operationIssues as $job) : ?>
                        <tr>
                            <td class="fw-semibold"><?= h(str_replace('_', ' ', (string)($job['job_type'] ?? ''))) ?></td>
                            <td><?= h($job['tenant_slug'] ?? __('platform')) ?></td>
                            <td><span class="ops-inline-state ops-inline-state--bad"><?= h($job['status'] ?? '') ?></span></td>
                            <td><?= h($job['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($operationIssues === []) : ?>
                        <tr><td colspan="4" class="ops-empty"><?= __('No failed or stalled jobs.') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="col-12 col-xl-6" aria-labelledby="schedule-issues-heading">
        <div class="ops-panel h-100">
            <div class="ops-panel__header">
                <div>
                    <p class="ops-panel__kicker mb-1"><?= __('Automation heartbeat') ?></p>
                    <h2 id="schedule-issues-heading" class="h4 mb-0"><?= __('Schedules needing review') ?></h2>
                </div>
                <?= $this->Html->link(__('All schedules'), ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'schedules'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <div class="table-responsive">
                <table class="table ops-table align-middle mb-0">
                    <thead><tr><th scope="col"><?= __('Schedule') ?></th><th scope="col"><?= __('Status') ?></th><th scope="col"><?= __('Last success') ?></th><th scope="col"><?= __('Next run') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($scheduleIssues as $schedule) : ?>
                        <tr>
                            <td class="fw-semibold"><?= h($schedule['name'] ?? '') ?></td>
                            <td><span class="ops-inline-state ops-inline-state--bad"><?= h($schedule['status'] ?? '') ?></span></td>
                            <td><?= h($schedule['last_success_at'] ?? __('never')) ?></td>
                            <td><?= h($schedule['next_run_at'] ?? __('not set')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($scheduleIssues === []) : ?>
                        <tr><td colspan="4" class="ops-empty"><?= __('No schedule failures or missed run windows.') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<section class="ops-footnote mt-4" aria-label="<?= __('Release and platform backup status') ?>">
    <div>
        <strong><?= __('Release') ?></strong>
        <?php if (!empty($releaseStatus['available'])) : ?>
            <?= __('{0}; {1} incompatible active tenants', h($releaseStatus['appVersion'] ?? ''), h((string)($releaseStatus['incompatibleCount'] ?? 0))) ?>
        <?php else : ?>
            <?= h($releaseStatus['message'] ?? __('Unavailable')) ?>
        <?php endif; ?>
    </div>
    <div>
        <strong><?= __('Platform backup') ?></strong>
        <?php if ($platformBackup !== null) : ?>
            <?= __('{0}; completed {1}', h($platformBackup['status'] ?? ''), h($platformBackup['completed_at'] ?? __('not yet'))) ?>
        <?php else : ?>
            <?= __('No platform database backup is recorded.') ?>
        <?php endif; ?>
    </div>
</section>
