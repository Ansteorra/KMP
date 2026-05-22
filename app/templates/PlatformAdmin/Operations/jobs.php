<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/** @var list<array<string, mixed>> $jobs */
$this->assign('title', __('Platform Jobs'));
?>
<h1 class="h2 mb-3"><?= __('Platform Jobs') ?></h1>
<div class="card"><div class="card-body"><div class="table-responsive">
<table class="table table-sm align-middle">
    <thead><tr><th><?= __('Type') ?></th><th><?= __('Tenant') ?></th><th><?= __('Status') ?></th><th><?= __('Created') ?></th><th><?= __('Started') ?></th><th><?= __('Finished') ?></th><th><?= __('Error') ?></th></tr></thead>
    <tbody>
    <?php foreach ($jobs as $job) : ?>
        <tr>
            <td><?= h($job['job_type'] ?? '') ?></td>
            <td><?= h($job['tenant_slug'] ?? __('platform')) ?></td>
            <td><?= h($job['status'] ?? '') ?></td>
            <td><?= h($job['created_at'] ?? '') ?></td>
            <td><?= h($job['started_at'] ?? '') ?></td>
            <td><?= h($job['finished_at'] ?? '') ?></td>
            <td><?= !empty($job['has_error']) ? __('Yes') : __('No') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($jobs === []) :
        ?><tr><td colspan="7" class="text-muted"><?= __('No jobs found.') ?></td></tr><?php
    endif; ?>
    </tbody>
</table>
</div></div></div>
