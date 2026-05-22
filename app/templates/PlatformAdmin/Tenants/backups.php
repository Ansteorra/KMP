<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var array<string, mixed> $tenant
 * @var list<array<string, mixed>> $jobs
 * @var list<array<string, mixed>> $backups
 * @var string $nonce
 */
$this->assign('title', __('Tenant Backups: {0}', $tenant['slug']));
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1"><?= __('Tenant Backups') ?></h1>
        <p class="text-muted mb-0"><?= __('Tenant: {0}', h($tenant['display_name'] ?? $tenant['slug'])) ?></p>
    </div>
    <?= $this->Html->link(
        __('Back to tenant'),
        ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']],
        ['class' => 'btn btn-outline-secondary'],
    ) ?>
</div>

<section class="card mb-4" aria-labelledby="tenant-backup-request-heading">
    <div class="card-body">
        <h2 id="tenant-backup-request-heading" class="h5"><?= __('Create Tenant Backup') ?></h2>
        <p class="text-muted">
            <?= __('Queues a platform-admin tenant backup using the shared encrypted .kmpbackup JSON archive model.') ?>
        </p>
        <?= $this->Form->create(null, [
            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'createBackup', $tenant['slug']],
        ]) ?>
        <?= $this->Form->hidden('nonce', ['value' => $nonce]) ?>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <?= $this->Form->control('retention_days', [
                    'type' => 'number',
                    'label' => __('Retention days'),
                    'value' => 30,
                    'min' => 1,
                    'max' => 365,
                ]) ?>
            </div>
            <div class="col-12 col-md-auto">
                <?= $this->Form->button(__('Queue backup'), ['class' => 'btn btn-primary']) ?>
            </div>
        </div>
        <?= $this->Form->end() ?>
    </div>
</section>

<section class="card mb-4" aria-labelledby="tenant-backup-jobs-heading">
    <div class="card-body">
        <h2 id="tenant-backup-jobs-heading" class="h5"><?= __('Queued Backup and Restore Operations') ?></h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Created') ?></th><th><?= __('Started') ?></th><th><?= __('Finished') ?></th><th><?= __('Error') ?></th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $job) : ?>
                    <tr>
                        <td><?= h($job['job_type'] ?? '') ?></td>
                        <td><?= h($job['status'] ?? '') ?></td>
                        <td><?= h($job['created_at'] ?? '') ?></td>
                        <td><?= h($job['started_at'] ?? '') ?></td>
                        <td><?= h($job['finished_at'] ?? '') ?></td>
                        <td><?= !empty($job['has_error']) ? __('Yes') : __('No') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($jobs === []) :
                    ?><tr><td colspan="6" class="text-muted"><?= __('No platform-admin backup operations have been queued for this tenant.') ?></td></tr><?php
                endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card" aria-labelledby="legacy-tenant-backups-heading">
    <div class="card-body">
        <h2 id="legacy-tenant-backups-heading" class="h5"><?= __('Recorded Tenant Backups') ?></h2>
        <p class="text-muted"><?= __('Existing tenant backup metadata is shown here until the shared .kmpbackup metadata view is fully unified.') ?></p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Size') ?></th><th><?= __('Created') ?></th><th><?= __('Completed') ?></th><th><?= __('Retention') ?></th><th><?= __('Guarded Actions') ?></th></tr></thead>
                <tbody>
                <?php foreach ($backups as $backup) : ?>
                    <?php $canUseArchive = ($backup['backup_type'] ?? '') === 'kmpbackup_json' && ($backup['status'] ?? '') === 'completed'; ?>
                    <tr>
                        <td><?= h($backup['backup_type'] ?? '') ?></td>
                        <td><?= h($backup['status'] ?? '') ?></td>
                        <td><?= h((string)($backup['object_size_bytes'] ?? '')) ?></td>
                        <td><?= h($backup['created_at'] ?? '') ?></td>
                        <td><?= h($backup['completed_at'] ?? '') ?></td>
                        <td><?= h($backup['retention_until'] ?? '') ?></td>
                        <td>
                            <?php if ($canUseArchive) : ?>
                                <div class="d-flex flex-column gap-2">
                                    <?= $this->Form->create(null, [
                                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'downloadBackup', $tenant['slug'], $backup['id']],
                                    ]) ?>
                                    <?= $this->Form->hidden('nonce', ['value' => $nonce]) ?>
                                    <?= $this->Form->control('confirmation', ['label' => __('Type DOWNLOAD {0}', $tenant['slug']), 'class' => 'form-control-sm']) ?>
                                    <?= $this->Form->control('reason', ['label' => __('Reason'), 'class' => 'form-control-sm']) ?>
                                    <?= $this->Form->control('totp', ['label' => __('MFA code'), 'class' => 'form-control-sm', 'autocomplete' => 'one-time-code']) ?>
                                    <?= $this->Form->button(__('Download encrypted archive'), ['class' => 'btn btn-outline-primary btn-sm']) ?>
                                    <?= $this->Form->end() ?>
                                    <?= $this->Form->create(null, [
                                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'restoreBackup', $tenant['slug'], $backup['id']],
                                    ]) ?>
                                    <?= $this->Form->hidden('nonce', ['value' => $nonce]) ?>
                                    <?= $this->Form->control('confirmation', ['label' => __('Type RESTORE {0}', $tenant['slug']), 'class' => 'form-control-sm']) ?>
                                    <?= $this->Form->control('reason', ['label' => __('Reason'), 'class' => 'form-control-sm']) ?>
                                    <?= $this->Form->control('totp', ['label' => __('MFA code'), 'class' => 'form-control-sm', 'autocomplete' => 'one-time-code']) ?>
                                    <?= $this->Form->button(__('Queue destructive restore'), ['class' => 'btn btn-outline-danger btn-sm']) ?>
                                    <?= $this->Form->end() ?>
                                </div>
                            <?php else : ?>
                                <span class="text-muted"><?= __('Only completed .kmpbackup archives can be downloaded or restored.') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($backups === []) :
                    ?><tr><td colspan="7" class="text-muted"><?= __('No tenant backup records found.') ?></td></tr><?php
                endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
