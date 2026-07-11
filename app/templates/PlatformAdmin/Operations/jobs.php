<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/** @var list<array<string, mixed>> $jobs */
$this->assign('title', __('Platform Jobs'));
?>
<header class="mb-4">
    <p class="ops-panel__kicker mb-1"><?= __('Asynchronous control plane') ?></p>
    <h1 class="display-5 fw-bold mb-1"><?= __('Platform jobs') ?></h1>
    <p class="text-muted mb-0"><?= __('Inspect lifecycle execution and open a job to review its sanitized progress timeline.') ?></p>
</header>
<div class="ops-panel"><div class="table-responsive">
<table class="table ops-table align-middle mb-0">
    <thead><tr><th scope="col"><?= __('Type') ?></th><th scope="col"><?= __('Tenant') ?></th><th scope="col"><?= __('Status') ?></th><th scope="col"><?= __('Created') ?></th><th scope="col"><?= __('Started') ?></th><th scope="col"><?= __('Finished') ?></th><th scope="col"><?= __('Failure') ?></th></tr></thead>
    <tbody>
    <?php foreach ($jobs as $job) : ?>
        <tr>
            <td><?= $this->Html->link(
                h(str_replace('_', ' ', (string)($job['job_type'] ?? ''))),
                ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'job', $job['id']],
                ['class' => 'fw-semibold', 'escape' => false],
            ) ?></td>
            <td><?= h($job['tenant_slug'] ?? __('platform')) ?></td>
            <td><span class="ops-inline-state <?= ($job['status'] ?? '') === 'completed' ? 'ops-inline-state--good' : 'ops-inline-state--bad' ?>"><?= h($job['status'] ?? '') ?></span></td>
            <td><?= h($job['created_at'] ?? '') ?></td>
            <td><?= h($job['started_at'] ?? '') ?></td>
            <td><?= h($job['finished_at'] ?? '') ?></td>
            <td><?= !empty($job['has_error']) ? __('Recorded') : __('None') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($jobs === []) :
        ?><tr><td colspan="7" class="text-muted"><?= __('No jobs found.') ?></td></tr><?php
    endif; ?>
    </tbody>
</table>
</div></div>
