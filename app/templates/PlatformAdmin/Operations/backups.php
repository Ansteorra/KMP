<?php
declare(strict_types=1);

use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\TenantBackupService;

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var list<array<string, mixed>> $tenantBackups
 * @var list<array<string, mixed>> $platformBackups
 * @var list<array<string, mixed>> $tenants
 * @var array{cadence: string, retention_days: int}|null $backupPolicy
 * @var string $nonce
 */
$this->assign('title', __('Platform Backups'));
?>
<h1 class="h2 mb-3"><?= __('Backups') ?></h1>
<section class="card mb-4" aria-labelledby="backup-policy-heading">
    <div class="card-body">
        <h2 id="backup-policy-heading" class="h5"><?= __('Backup Policy') ?></h2>
        <p class="text-muted">
            <?= __('One global policy governs every tenant: how often managed backups are taken, how long they are retained, and when fleet health flags a tenant\'s backups as stale. Tenants see this policy read-only.') ?>
        </p>
        <?php if ($backupPolicy === null) : ?>
            <div class="alert alert-warning" role="note">
                <?= __('The backup policy store is unavailable. Run the platform migrations to create platform_settings.') ?>
            </div>
        <?php else : ?>
            <?= $this->Form->create(null, [
                'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'saveBackupPolicy'],
            ]) ?>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <?= $this->Form->control('cadence', [
                        'type' => 'select',
                        'label' => __('Cadence'),
                        'options' => ['daily' => __('Daily'), 'weekly' => __('Weekly')],
                        'value' => $backupPolicy['cadence'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <?= $this->Form->control('retention_days', [
                        'type' => 'number',
                        'label' => __('Retention days'),
                        'value' => $backupPolicy['retention_days'],
                        'min' => 1,
                        'max' => 365,
                    ]) ?>
                </div>
                <div class="col-12 col-md-auto">
                    <?= $this->Form->button(__('Save policy'), ['class' => 'btn btn-primary']) ?>
                </div>
            </div>
            <?= $this->Form->end() ?>
        <?php endif; ?>
    </div>
</section>
<section class="card mb-4" aria-labelledby="platform-backup-actions-heading">
    <div class="card-body">
        <h2 id="platform-backup-actions-heading" class="h5"><?= __('Platform Database Backup') ?></h2>
        <p class="text-muted">
            <?= __('Creates an encrypted PostgreSQL dump in the configured backup storage backend. Platform database restores are deliberately performed through the disaster-recovery runbook, outside the live portal process.') ?>
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
            <thead><tr><th><?= __('Tenant') ?></th><th><?= __('Type') ?></th><th><?= __('Status') ?></th><th><?= __('Size') ?></th><th><?= __('Created') ?></th><th><?= __('Completed') ?></th><th><?= __('Retention') ?></th><th><?= __('Failure') ?></th></tr></thead>
            <tbody>
            <?php foreach ($tenantBackups as $backup) : ?>
                <tr><td><?= h($backup['tenant_slug'] ?? '') ?></td><td><?= h($backup['backup_type'] ?? '') ?></td><td><?= h($backup['status'] ?? '') ?></td><td><?= h((string)($backup['object_size_bytes'] ?? '')) ?></td><td><?= h($backup['created_at'] ?? '') ?></td><td><?= h($backup['completed_at'] ?? '') ?></td><td><?= h($backup['retention_until'] ?? '') ?></td><td><?= h($backup['error_summary'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if ($tenantBackups === []) :
                ?><tr><td colspan="8" class="text-muted"><?= __('No tenant backups found.') ?></td></tr><?php
            endif; ?>
            </tbody>
        </table></div>
    </div>
</section>
<?php $platformBackupModalId = 'platform-backup-action-modal'; ?>
<section
    class="card"
    aria-labelledby="platform-backups-heading"
    data-controller="guarded-action-modal"
>
    <div class="card-body">
        <h2 id="platform-backups-heading" class="h5"><?= __('Platform Database Backups') ?></h2>
        <div class="alert alert-warning" role="note" aria-labelledby="platform-recovery-key-warning-heading">
            <h3 id="platform-recovery-key-warning-heading" class="h6 alert-heading">
                <?= __('Keep recovery keys separate from archives') ?>
            </h3>
            <p class="mb-0">
                <?= __('Each recovery-key file decrypts one matching platform archive and does not expose the reusable platform key-encryption key. Store the files in separate protected locations for disaster recovery.') ?>
            </p>
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle text-nowrap">
            <thead><tr><th scope="col"><?= __('Connection') ?></th><th scope="col"><?= __('Database') ?></th><th scope="col"><?= __('Type') ?></th><th scope="col"><?= __('Status') ?></th><th scope="col"><?= __('Size') ?></th><th scope="col"><?= __('Completed') ?></th><th scope="col"><?= __('Retention') ?></th><th scope="col"><?= __('Failure') ?></th><th scope="col"><?= __('Actions') ?></th></tr></thead>
            <tbody>
            <?php foreach ($platformBackups as $backup) : ?>
                <?php
                $backupId = (string)($backup['id'] ?? '');
                $domBackupId = (string)preg_replace('/[^A-Za-z0-9_-]/', '-', $backupId);
                $backupType = (string)($backup['backup_type'] ?? '');
                $retentionTimestamp = strtotime((string)($backup['retention_until'] ?? ''));
                $canDownloadArchive = in_array(
                    $backupType,
                    ['pg_dump', TenantBackupService::LEGACY_BACKUP_TYPE],
                    true,
                )
                    && ($backup['status'] ?? '') === 'completed'
                    && ($retentionTimestamp === false || $retentionTimestamp > time());
                $canExportRecoveryKey = $canDownloadArchive
                    && $backupType === 'pg_dump'
                    && ($backup['encryption_algorithm'] ?? '') ===
                        PlatformDatabaseBackupEncryptor::DATA_ALGORITHM;
                $canDeleteArchive = (string)($backup['object_uri'] ?? '') !== ''
                    && in_array(
                        $backupType,
                        ['pg_dump', TenantBackupService::LEGACY_BACKUP_TYPE],
                        true,
                    )
                    && in_array((string)($backup['status'] ?? ''), ['completed', 'failed', 'deleting'], true);
                ?>
                <tr>
                    <td><?= h($backup['connection_name'] ?? '') ?></td>
                    <td><?= h($backup['database_name'] ?? '') ?></td>
                    <td><?= h($backup['backup_type'] ?? '') ?></td>
                    <td><?= h($backup['status'] ?? '') ?></td>
                    <td><?= h((string)($backup['object_size_bytes'] ?? '')) ?></td>
                    <td><?= h($backup['completed_at'] ?? '') ?></td>
                    <td><?= h($backup['retention_until'] ?? '') ?></td>
                    <td class="text-wrap"><?= h($backup['error_summary'] ?? '') ?></td>
                    <td>
                        <?php if ($canDownloadArchive || $canDeleteArchive) : ?>
                            <div
                                class="d-inline-flex flex-nowrap gap-1"
                                role="group"
                                aria-label="<?= h(__('Actions for platform backup {0}', $backupId)) ?>"
                            >
                                <?php if ($canDownloadArchive) : ?>
                                    <?= $this->element('PlatformAdmin/guarded_backup_action', [
                                        'modalId' => $platformBackupModalId,
                                        'templateId' => 'platform-backup-' . $domBackupId . '-download',
                                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'downloadPlatformBackup', $backupId],
                                        'nonce' => $nonce,
                                        'buttonLabel' => __('Download encrypted dump'),
                                        'modalTitle' => __('Download platform backup'),
                                        'description' => $backupType === TenantBackupService::LEGACY_BACKUP_TYPE
                                            ? __('Download the legacy encrypted .kmpbackup archive for backup {0}.', $backupId)
                                            : __('Download the encrypted PostgreSQL dump for backup {0}.', $backupId),
                                        'confirmation' => 'DOWNLOAD platform',
                                        'submitLabel' => __('Download encrypted dump'),
                                        'tone' => 'primary',
                                    ]) ?>
                                <?php endif; ?>
                                <?php if ($canExportRecoveryKey) : ?>
                                    <?= $this->element('PlatformAdmin/guarded_backup_action', [
                                        'modalId' => $platformBackupModalId,
                                        'templateId' => 'platform-backup-' . $domBackupId . '-recovery-key',
                                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'downloadPlatformBackupRecoveryKey', $backupId],
                                        'nonce' => $nonce,
                                        'buttonLabel' => __('Download recovery key'),
                                        'modalTitle' => __('Download platform recovery key'),
                                        'description' => __('Export the recovery key for platform backup {0}.', $backupId),
                                        'confirmation' => 'DOWNLOAD KEY platform',
                                        'submitLabel' => __('Download recovery key'),
                                        'warning' => __('This file is a high-sensitivity secret. Store it separately from the encrypted dump.'),
                                        'tone' => 'warning',
                                    ]) ?>
                                <?php endif; ?>
                                <?php if ($canDeleteArchive) : ?>
                                    <?= $this->element('PlatformAdmin/guarded_backup_action', [
                                        'modalId' => $platformBackupModalId,
                                        'templateId' => 'platform-backup-' . $domBackupId . '-delete',
                                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'deletePlatformBackup', $backupId],
                                        'nonce' => $nonce,
                                        'buttonLabel' => __('Delete backup'),
                                        'modalTitle' => __('Delete platform backup'),
                                        'description' => __('Delete the encrypted archive for platform backup {0}.', $backupId),
                                        'confirmation' => 'DELETE BACKUP platform',
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
            <?php if ($platformBackups === []) :
                ?><tr><td colspan="9" class="text-muted"><?= __('No platform backups found.') ?></td></tr><?php
            endif; ?>
            </tbody>
        </table></div>
    </div>
    <?= $this->element('PlatformAdmin/guarded_backup_action_modal', [
        'modalId' => $platformBackupModalId,
    ]) ?>
</section>
