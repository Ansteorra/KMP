<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var list<array<string, mixed>> $tenantBackups
 * @var list<array<string, mixed>> $platformBackups
 * @var list<array<string, mixed>> $tenants
 * @var string $nonce
 */
$this->assign('title', __('Platform Backups'));
?>
<h1 class="h2 mb-3"><?= __('Backups') ?></h1>
<section class="card mb-4" aria-labelledby="platform-backup-actions-heading">
    <div class="card-body">
        <h2 id="platform-backup-actions-heading" class="h5"><?= __('Platform Database Backup') ?></h2>
        <p class="text-muted">
            <?= __('Queues a platform database backup using the shared encrypted .kmpbackup JSON archive model.') ?>
        </p>
        <?= $this->Form->create(null, [
            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'createPlatformBackup'],
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
                <?= $this->Form->button(__('Queue platform backup'), ['class' => 'btn btn-primary']) ?>
            </div>
        </div>
        <?= $this->Form->end() ?>
    </div>
</section>
<section class="card mb-4" aria-labelledby="tenant-backup-actions-heading">
    <div class="card-body">
        <h2 id="tenant-backup-actions-heading" class="h5"><?= __('Tenant Backup Administration') ?></h2>
        <p class="text-muted"><?= __('Open a tenant to manage platform-admin backup operations for that tenant.') ?></p>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th><?= __('Tenant') ?></th><th><?= __('Status') ?></th><th class="text-end"><?= __('Actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($tenants as $tenant) : ?>
                <tr>
                    <td><?= h($tenant['display_name'] ?? $tenant['slug']) ?></td>
                    <td><?= h($tenant['status'] ?? '') ?></td>
                    <td class="text-end">
                        <?= $this->Html->link(
                            __('Manage backups'),
                            ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'backups', $tenant['slug']],
                            ['class' => 'btn btn-outline-primary btn-sm'],
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($tenants === []) :
                ?><tr><td colspan="3" class="text-muted"><?= __('No tenants found.') ?></td></tr><?php
            endif; ?>
            </tbody>
        </table></div>
    </div>
</section>
<section class="card mb-4" aria-labelledby="tenant-backups-heading">
    <div class="card-body">
        <h2 id="tenant-backups-heading" class="h5"><?= __('Tenant Backups') ?></h2>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th><?= __('Tenant') ?></th><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Size') ?></th><th><?= __('Created') ?></th><th><?= __('Completed') ?></th><th><?= __('Retention') ?></th></tr></thead>
            <tbody>
            <?php foreach ($tenantBackups as $backup) : ?>
                <tr><td><?= h($backup['tenant_slug'] ?? '') ?></td><td><?= h($backup['backup_type'] ?? '') ?></td><td><?= h($backup['status'] ?? '') ?></td><td><?= h((string)($backup['object_size_bytes'] ?? '')) ?></td><td><?= h($backup['created_at'] ?? '') ?></td><td><?= h($backup['completed_at'] ?? '') ?></td><td><?= h($backup['retention_until'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if ($tenantBackups === []) :
                ?><tr><td colspan="7" class="text-muted"><?= __('No tenant backups found.') ?></td></tr><?php
            endif; ?>
            </tbody>
        </table></div>
    </div>
</section>
<section class="card" aria-labelledby="platform-backups-heading">
    <div class="card-body">
        <h2 id="platform-backups-heading" class="h5"><?= __('Platform Database Backups') ?></h2>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th><?= __('Connection') ?></th><th><?= __('Database') ?></th><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Size') ?></th><th><?= __('Completed') ?></th><th><?= __('Retention') ?></th><th><?= __('Guarded Actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($platformBackups as $backup) : ?>
                <?php $canUseArchive = ($backup['backup_type'] ?? '') === 'kmpbackup_json' && ($backup['status'] ?? '') === 'completed'; ?>
                <tr>
                    <td><?= h($backup['connection_name'] ?? '') ?></td>
                    <td><?= h($backup['database_name'] ?? '') ?></td>
                    <td><?= h($backup['backup_type'] ?? '') ?></td>
                    <td><?= h($backup['status'] ?? '') ?></td>
                    <td><?= h((string)($backup['object_size_bytes'] ?? '')) ?></td>
                    <td><?= h($backup['completed_at'] ?? '') ?></td>
                    <td><?= h($backup['retention_until'] ?? '') ?></td>
                    <td>
                        <?php if ($canUseArchive) : ?>
                            <div class="d-flex flex-column gap-2">
                                <?= $this->Form->create(null, [
                                    'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'downloadPlatformBackup', $backup['id']],
                                ]) ?>
                                <?= $this->Form->hidden('nonce', ['value' => $nonce]) ?>
                                <?= $this->Form->control('confirmation', ['label' => __('Type DOWNLOAD platform'), 'class' => 'form-control-sm']) ?>
                                <?= $this->Form->control('reason', ['label' => __('Reason'), 'class' => 'form-control-sm']) ?>
                                <?= $this->Form->control('totp', ['label' => __('MFA code'), 'class' => 'form-control-sm', 'autocomplete' => 'one-time-code']) ?>
                                <?= $this->Form->button(__('Download encrypted archive'), ['class' => 'btn btn-outline-primary btn-sm']) ?>
                                <?= $this->Form->end() ?>
                                <?= $this->Form->create(null, [
                                    'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'restorePlatformBackup', $backup['id']],
                                ]) ?>
                                <?= $this->Form->hidden('nonce', ['value' => $nonce]) ?>
                                <?= $this->Form->control('confirmation', ['label' => __('Type RESTORE platform'), 'class' => 'form-control-sm']) ?>
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
            <?php if ($platformBackups === []) :
                ?><tr><td colspan="8" class="text-muted"><?= __('No platform backups found.') ?></td></tr><?php
            endif; ?>
            </tbody>
        </table></div>
    </div>
</section>
