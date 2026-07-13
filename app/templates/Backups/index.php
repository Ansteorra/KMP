<?php

/**
 * Backups index — platform-managed backup status, list, create, download.
 *
 * Scheduling, retention, and restores are managed by the platform; this
 * page is the tenant's window into that system plus read-only access to
 * legacy self-service archives.
 *
 * @var \App\View\AppView $this
 * @var list<array<string, mixed>> $managedBackups
 * @var array<string, mixed>|null $backupStatus
 * @var bool $managedAvailable
 * @var iterable<\App\Model\Entity\Backup> $legacyBackups
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Backups';
$this->KMP->endBlock();

$this->assign('title', __('Backups'));

$formatBytes = static function ($bytes): string {
    $bytes = (int)$bytes;
    if ($bytes <= 0) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);

    return sprintf('%.1f %s', $bytes / (1024 ** $power), $units[$power]);
};
?>

<div class="container-fluid">
    <div class="row">
        <!-- Backup Status Panel -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= __('Backup Status') ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!$managedAvailable || $backupStatus === null): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <?= __('Managed backup status is currently unavailable. Contact your platform administrator.') ?>
                        </div>
                    <?php else: ?>
                        <?php $latest = $backupStatus['latest_completed'] ?? null; ?>
                        <?php if ($latest === null): ?>
                            <div class="alert alert-danger py-2 px-2">
                                <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                                <?= __('No completed backup exists yet.') ?>
                            </div>
                        <?php elseif (!empty($backupStatus['stale'])): ?>
                            <div class="alert alert-warning py-2 px-2">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                <?= __('The latest backup is older than the scheduled cadence.') ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success py-2 px-2">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                <?= __('Backups are up to date.') ?>
                            </div>
                        <?php endif; ?>
                        <dl class="row mb-0 small">
                            <dt class="col-6"><?= __('Last completed') ?></dt>
                            <dd class="col-6"><?= h($latest['completed_at'] ?? __('never')) ?></dd>
                            <dt class="col-6"><?= __('Schedule') ?></dt>
                            <dd class="col-6"><?= h(ucfirst((string)($backupStatus['cadence'] ?? 'daily'))) ?></dd>
                            <dt class="col-6"><?= __('Retention') ?></dt>
                            <dd class="col-6"><?= __('{0} days', (int)($backupStatus['retention_days'] ?? 30)) ?></dd>
                        </dl>
                        <div class="form-text mt-2">
                            <?= __('Backup schedule and retention are managed by your platform administrator. Restores are performed by platform administrators.') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Managed Backups -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-archive" aria-hidden="true"></i> <?= __('Backups') ?></h5>
                    <?php if ($managedAvailable): ?>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-plus-circle" aria-hidden="true"></i> ' . __('Request Backup'),
                            ['action' => 'create'],
                            [
                                'class' => 'btn btn-primary btn-sm',
                                'escape' => false,
                                'confirm' => __('Queue a new managed backup now?'),
                            ],
                        ) ?>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 px-2">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <?= __('Downloads use two files: the encrypted archive and a recovery key. The recovery key can be exported only once per backup — store both files in separate safe locations.') ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th><?= __('Created') ?></th>
                                    <th><?= __('Status') ?></th>
                                    <th><?= __('Size') ?></th>
                                    <th><?= __('Kept until') ?></th>
                                    <th class="text-end"><?= __('Actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managedBackups as $backup): ?>
                                    <?php
                                    $status = (string)($backup['status'] ?? '');
                                    $badge = match ($status) {
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'running', 'queued' => 'info',
                                        'expired' => 'secondary',
                                        default => 'secondary',
                                    };
                                    $retentionTimestamp = strtotime((string)($backup['retention_until'] ?? '') . ' UTC');
                                    $downloadable = $status === 'completed'
                                        && (string)($backup['object_uri'] ?? '') !== ''
                                        && ($retentionTimestamp === false || $retentionTimestamp > time());
                                    $keyExported = !empty($backup['recovery_key_exported_at']);
                                    ?>
                                    <tr>
                                        <td><?= h($backup['created_at'] ?? '') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $badge ?>"><?= h($status) ?></span>
                                            <?php if ($status === 'failed' && !empty($backup['error_summary'])): ?>
                                                <i class="bi bi-info-circle" title="<?= h($backup['error_summary']) ?>" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $formatBytes($backup['object_size_bytes'] ?? 0) ?></td>
                                        <td><?= h($backup['retention_until'] ?? '') ?></td>
                                        <td class="text-end">
                                            <?php if ($downloadable): ?>
                                                <div class="d-inline-flex flex-nowrap gap-1">
                                                    <?= $this->Form->postLink(
                                                        '<i class="bi bi-download" aria-hidden="true"></i> ' . __('Archive'),
                                                        ['action' => 'download', (string)$backup['id']],
                                                        ['class' => 'btn btn-outline-primary btn-sm', 'escape' => false],
                                                    ) ?>
                                                    <?php if ($keyExported): ?>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled
                                                            title="<?= h(__('The recovery key was already exported on {0}.', (string)$backup['recovery_key_exported_at'])) ?>">
                                                            <i class="bi bi-key" aria-hidden="true"></i> <?= __('Key exported') ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <?= $this->Form->postLink(
                                                            '<i class="bi bi-key" aria-hidden="true"></i> ' . __('Recovery key'),
                                                            ['action' => 'downloadRecoveryKey', (string)$backup['id']],
                                                            [
                                                                'class' => 'btn btn-outline-warning btn-sm',
                                                                'escape' => false,
                                                                'confirm' => __('The recovery key can be exported only once. Download and store it securely now?'),
                                                            ],
                                                        ) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small"><?= __('Not downloadable') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($managedBackups === []): ?>
                                    <tr><td colspan="5" class="text-muted"><?= __('No managed backups yet.') ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($legacyBackups) > 0 || !$managedAvailable): ?>
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= __('Legacy Backups') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-secondary py-2 px-2">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <?= __('These older .kmpbackup files are passphrase-encrypted with your previous backup encryption key. They are read-only; new backups are created and retained by the managed system above. To restore one, contact your platform administrator.') ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th><?= __('Filename') ?></th>
                                        <th><?= __('Created') ?></th>
                                        <th><?= __('Size') ?></th>
                                        <th><?= __('Status') ?></th>
                                        <th class="text-end"><?= __('Actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $legacyCount = 0; ?>
                                    <?php foreach ($legacyBackups as $backup): ?>
                                        <?php $legacyCount++; ?>
                                        <tr>
                                            <td><code><?= h($backup->filename) ?></code></td>
                                            <td><?= h($backup->created) ?></td>
                                            <td><?= $formatBytes($backup->size_bytes) ?></td>
                                            <td><?= h($backup->status) ?></td>
                                            <td class="text-end">
                                                <?php if ($backup->status === 'completed'): ?>
                                                    <?= $this->Form->postLink(
                                                        '<i class="bi bi-download" aria-hidden="true"></i> ' . __('Download'),
                                                        ['action' => 'legacyDownload', $backup->id],
                                                        ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false],
                                                    ) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($legacyCount === 0): ?>
                                        <tr><td colspan="5" class="text-muted"><?= __('No legacy backups.') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
