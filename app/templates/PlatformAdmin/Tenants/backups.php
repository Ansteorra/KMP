<?php
declare(strict_types=1);

use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;

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
            <?= __('Creates an encrypted, gzip-compressed JSON logical archive in the configured backup storage backend.') ?>
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
                <thead><tr><th scope="col"><?= __('Type') ?></th><th scope="col"><?= __('Status') ?></th><th scope="col"><?= __('Created') ?></th><th scope="col"><?= __('Started') ?></th><th scope="col"><?= __('Finished') ?></th><th scope="col"><?= __('Error') ?></th></tr></thead>
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

<?php $tenantBackupModalId = 'tenant-backup-action-modal'; ?>
<section
    class="card"
    aria-labelledby="recorded-tenant-backups-heading"
    data-controller="guarded-action-modal"
>
    <div class="card-body">
        <h2 id="recorded-tenant-backups-heading" class="h5"><?= __('Recorded Tenant Backups') ?></h2>
        <div class="alert alert-warning" role="note" aria-labelledby="tenant-recovery-key-warning-heading">
            <h3 id="tenant-recovery-key-warning-heading" class="h6 alert-heading">
                <?= __('Recovery keys are sensitive') ?>
            </h3>
            <p class="mb-0">
                <?= __('A recovery-key file decrypts only its matching archive; it never contains the reusable tenant key-encryption key. Download the two files separately, store them in separate protected locations, and delete browser copies when custody is complete.') ?>
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle text-nowrap">
                <thead><tr><th scope="col"><?= __('Type') ?></th><th scope="col"><?= __('Status') ?></th><th scope="col"><?= __('Size') ?></th><th scope="col"><?= __('Created') ?></th><th scope="col"><?= __('Completed') ?></th><th scope="col"><?= __('Retention') ?></th><th scope="col"><?= __('Guarded Actions') ?></th></tr></thead>
                <tbody>
                <?php foreach ($backups as $backup) : ?>
                    <?php
                    $backupId = (string)($backup['id'] ?? '');
                    $domBackupId = (string)preg_replace('/[^A-Za-z0-9_-]/', '-', $backupId);
                    $backupType = (string)($backup['backup_type'] ?? '');
                    $backupTypeLabel = match ($backupType) {
                        'json' => __('JSON logical archive'),
                        'pg_dump' => __('Legacy PostgreSQL dump'),
                        TenantBackupService::LEGACY_BACKUP_TYPE => __('Legacy .kmpbackup archive'),
                        default => __('Unknown format'),
                    };
                    $retentionTimestamp = strtotime((string)($backup['retention_until'] ?? ''));
                    $canDownloadArchive = in_array(
                        $backupType,
                        ['json', 'pg_dump', TenantBackupService::LEGACY_BACKUP_TYPE],
                        true,
                    )
                        && ($backup['status'] ?? '') === 'completed'
                        && ($retentionTimestamp === false || $retentionTimestamp > time());
                    $canRestoreArchive = $canDownloadArchive
                        && in_array($backupType, ['json', 'pg_dump'], true);
                    $canExportRecoveryKey = $canDownloadArchive
                        && $backupType === 'json'
                        && ($backup['encryption_algorithm'] ?? '') ===
                            TenantBackupEncryptor::DATA_ALGORITHM;
                    $canDeleteArchive = (string)($backup['object_uri'] ?? '') !== ''
                        && in_array(
                            $backupType,
                            ['json', 'pg_dump', TenantBackupService::LEGACY_BACKUP_TYPE],
                            true,
                        )
                        && in_array((string)($backup['status'] ?? ''), ['completed', 'failed', 'deleting'], true);
                    ?>
                    <tr>
                        <td><?= h($backupTypeLabel) ?></td>
                        <td><?= h($backup['status'] ?? '') ?></td>
                        <td><?= h((string)($backup['object_size_bytes'] ?? '')) ?></td>
                        <td><?= h($backup['created_at'] ?? '') ?></td>
                        <td><?= h($backup['completed_at'] ?? '') ?></td>
                        <td><?= h($backup['retention_until'] ?? '') ?></td>
                        <td>
                            <?php if ($canDownloadArchive || $canDeleteArchive) : ?>
                                <div
                                    class="d-inline-flex flex-nowrap gap-1"
                                    role="group"
                                    aria-label="<?= h(__('Actions for tenant backup {0}', $backupId)) ?>"
                                >
                                    <?php if ($canDownloadArchive) : ?>
                                        <?= $this->element('PlatformAdmin/guarded_backup_action', [
                                            'modalId' => $tenantBackupModalId,
                                            'templateId' => 'tenant-backup-' . $domBackupId . '-download',
                                            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'downloadBackup', $tenant['slug'], $backupId],
                                            'nonce' => $nonce,
                                            'buttonLabel' => __('Download encrypted archive'),
                                            'modalTitle' => __('Download tenant backup'),
                                            'description' => __('Download the encrypted archive for tenant backup {0}.', $backupId),
                                            'confirmation' => 'DOWNLOAD ' . $tenant['slug'],
                                            'submitLabel' => __('Download encrypted archive'),
                                            'tone' => 'primary',
                                        ]) ?>
                                    <?php endif; ?>
                                    <?php if ($canExportRecoveryKey) : ?>
                                        <?= $this->element('PlatformAdmin/guarded_backup_action', [
                                            'modalId' => $tenantBackupModalId,
                                            'templateId' => 'tenant-backup-' . $domBackupId . '-recovery-key',
                                            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'downloadBackupRecoveryKey', $tenant['slug'], $backupId],
                                            'nonce' => $nonce,
                                            'buttonLabel' => __('Download recovery key'),
                                            'modalTitle' => __('Download tenant recovery key'),
                                            'description' => __('Export the recovery key for tenant backup {0}.', $backupId),
                                            'confirmation' => 'DOWNLOAD KEY ' . $tenant['slug'],
                                            'submitLabel' => __('Download recovery key'),
                                            'warning' => __('This file is a high-sensitivity secret. Store it separately from the encrypted archive.'),
                                            'tone' => 'warning',
                                        ]) ?>
                                    <?php endif; ?>
                                    <?php if ($canRestoreArchive) : ?>
                                        <?= $this->element('PlatformAdmin/guarded_backup_action', [
                                            'modalId' => $tenantBackupModalId,
                                            'templateId' => 'tenant-backup-' . $domBackupId . '-restore',
                                            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'restoreBackup', $tenant['slug'], $backupId],
                                            'nonce' => $nonce,
                                            'buttonLabel' => __('Queue destructive restore'),
                                            'modalTitle' => __('Queue tenant restore'),
                                            'description' => __('Queue tenant backup {0} to replace the current tenant database.', $backupId),
                                            'confirmation' => 'RESTORE ' . $tenant['slug'],
                                            'submitLabel' => __('Queue destructive restore'),
                                            'warning' => __('Restore is destructive. The tenant must be suspended before this request can be queued.'),
                                            'tone' => 'danger',
                                        ]) ?>
                                    <?php endif; ?>
                                    <?php if ($canDeleteArchive) : ?>
                                        <?= $this->element('PlatformAdmin/guarded_backup_action', [
                                            'modalId' => $tenantBackupModalId,
                                            'templateId' => 'tenant-backup-' . $domBackupId . '-delete',
                                            'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'deleteBackup', $tenant['slug'], $backupId],
                                            'nonce' => $nonce,
                                            'buttonLabel' => __('Delete backup'),
                                            'modalTitle' => __('Delete tenant backup'),
                                            'description' => __('Delete the encrypted archive for tenant backup {0}.', $backupId),
                                            'confirmation' => 'DELETE BACKUP ' . $tenant['slug'],
                                            'submitLabel' => __('Delete backup'),
                                            'warning' => __('This permanently removes the encrypted archive. Operational and audit metadata will be retained.'),
                                            'tone' => 'danger',
                                        ]) ?>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <span class="text-muted"><?= __('No guarded actions are available for this backup.') ?></span>
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
    <?= $this->element('PlatformAdmin/guarded_backup_action_modal', [
        'modalId' => $tenantBackupModalId,
    ]) ?>
</section>
