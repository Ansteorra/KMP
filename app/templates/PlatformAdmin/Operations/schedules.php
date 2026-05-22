<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/** @var list<array<string, mixed>> $schedules */
$this->assign('title', __('Platform Schedules'));
?>
<h1 class="h2 mb-3"><?= __('Platform Schedules') ?></h1>
<div class="card"><div class="card-body"><div class="table-responsive">
<table class="table table-sm align-middle">
    <thead><tr><th><?= __('Name') ?></th><th><?= __('Cron') ?></th><th><?= __('Command') ?></th><th><?= __('Enabled') ?></th><th><?= __('Scope') ?></th><th><?= __('Tenant') ?></th><th><?= __('Status') ?></th><th><?= __('Next Run') ?></th><th><?= __('Error') ?></th></tr></thead>
    <tbody>
    <?php foreach ($schedules as $schedule) : ?>
        <tr>
            <td><?= h($schedule['name'] ?? '') ?></td>
            <td><code><?= h($schedule['cron_expression'] ?? '') ?></code></td>
            <td><code><?= h($schedule['command'] ?? '') ?></code></td>
            <td><?= !empty($schedule['enabled']) ? __('Yes') : __('No') ?></td>
            <td><?= h($schedule['tenant_scope'] ?? '') ?></td>
            <td><?= h($schedule['tenant_slug'] ?? __('platform')) ?></td>
            <td><?= h($schedule['status'] ?? '') ?></td>
            <td><?= h($schedule['next_run_at'] ?? '') ?></td>
            <td><?= !empty($schedule['has_error']) ? __('Yes') : __('No') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($schedules === []) :
        ?><tr><td colspan="9" class="text-muted"><?= __('No schedules found.') ?></td></tr><?php
    endif; ?>
    </tbody>
</table>
</div></div></div>
