<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var list<array<string, mixed>> $tenants
 */
$this->assign('title', __('Tenant Fleet'));
$attentionCount = count(array_filter(
    $tenants,
    static fn(array $tenant): bool => in_array($tenant['risk_level'] ?? '', ['critical', 'warning'], true),
));
?>
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
    <div>
        <p class="ops-panel__kicker mb-1"><?= __('Kingdom support inventory') ?></p>
        <h1 class="display-5 fw-bold mb-1"><?= __('Tenant fleet') ?></h1>
        <p class="text-muted mb-0">
            <?= __('{0} registered kingdoms; {1} currently need operator attention.', h((string)count($tenants)), h((string)$attentionCount)) ?>
        </p>
    </div>
    <?= $this->Html->link(__('Onboard a kingdom'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'add'], ['class' => 'btn btn-warning btn-lg']) ?>
</header>

<section class="ops-panel" aria-labelledby="tenant-fleet-heading">
    <div class="ops-panel__header">
        <div>
            <p class="ops-panel__kicker mb-1"><?= __('24-hour operating window') ?></p>
            <h2 id="tenant-fleet-heading" class="h4 mb-0"><?= __('Fleet comparison') ?></h2>
        </div>
        <span class="text-muted small"><?= __('Ordered by risk, then kingdom') ?></span>
    </div>
    <div class="table-responsive">
        <table class="table ops-table align-middle mb-0">
            <caption class="visually-hidden"><?= __('Tenant fleet operational comparison') ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?= __('Kingdom') ?></th>
                    <th scope="col"><?= __('Lifecycle') ?></th>
                    <th scope="col"><?= __('Risk') ?></th>
                    <th scope="col" class="text-end"><?= __('Requests') ?></th>
                    <th scope="col" class="text-end"><?= __('Error rate') ?></th>
                    <th scope="col" class="text-end"><?= __('Average') ?></th>
                    <th scope="col"><?= __('Latest backup') ?></th>
                    <th scope="col"><?= __('Support signal') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tenants as $tenant) : ?>
                <?php $risk = (string)($tenant['risk_level'] ?? 'inactive'); ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            h((string)($tenant['display_name'] ?? $tenant['slug'] ?? '')),
                            ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']],
                            ['class' => 'fw-semibold', 'escape' => false],
                        ) ?>
                        <div class="small text-muted"><?= h($tenant['slug'] ?? '') ?> · <?= h($tenant['region'] ?? '') ?></div>
                    </td>
                    <td>
                        <span class="ops-inline-state <?= ($tenant['status'] ?? '') === 'active' ? 'ops-inline-state--good' : 'ops-inline-state--bad' ?>">
                            <?= h($tenant['status'] ?? __('unknown')) ?>
                        </span>
                        <div class="small text-muted mt-1"><?= h($tenant['schema_version'] ?? __('schema unknown')) ?></div>
                    </td>
                    <td><span class="ops-risk ops-risk--<?= h($risk) ?>"><?= h($risk) ?></span></td>
                    <td class="text-end ops-number"><?= h(number_format((int)($tenant['request_count'] ?? 0))) ?></td>
                    <td class="text-end ops-number"><?= h(number_format((float)($tenant['error_rate'] ?? 0), 2)) ?>%</td>
                    <td class="text-end ops-number"><?= h((string)($tenant['average_duration_ms'] ?? 0)) ?> ms</td>
                    <td>
                        <?php if (!empty($tenant['backup_fresh'])) : ?>
                            <span class="ops-inline-state ops-inline-state--good"><?= __('current') ?></span>
                        <?php else : ?>
                            <span class="ops-inline-state ops-inline-state--bad"><?= h($tenant['backup_status'] ?? __('missing')) ?></span>
                        <?php endif; ?>
                        <div class="small text-muted mt-1"><?= h($tenant['backup_completed_at'] ?? __('never')) ?></div>
                    </td>
                    <td class="ops-reason">
                        <?= h(implode(' ', (array)($tenant['attention'] ?? [])) ?: __('No threshold alerts.')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($tenants === []) : ?>
                <tr><td colspan="8" class="ops-empty"><?= __('No tenants were found, or operational telemetry has not been migrated.') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
