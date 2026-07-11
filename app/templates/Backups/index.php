<?php

/**
 * Backups index — list backups, create new, restore, settings.
 *
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Backup> $backups
 * @var bool $hasKey
 * @var string $schedule
 * @var int $retention
 * @var string $storageType
 * @var array<string, mixed> $restoreStatus
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Backups';
$this->KMP->endBlock();

$this->assign('title', __('Backups'));

$restoreIsLocked = !empty($restoreStatus['locked']);
?>

<div class="container-fluid"
    data-controller="backup-restore-status"
    data-backup-restore-status-url-value="<?= h($this->Url->build(['action' => 'status'])) ?>"
    data-backup-restore-status-interval-value="1000"
    data-backup-restore-status-terminal-window-value="30"
    data-backup-restore-status-auto-reload-value="true">
    <div class="row">
        <!-- Settings Panel -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-gear" aria-hidden="true"></i> <?= __('Backup Settings') ?></h5>
                </div>
                <div class="card-body">
                    <?= $this->Form->create(null, ['url' => ['action' => 'settings']]) ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('Encryption Key') ?></label>
                        <?php if ($hasKey): ?>
                            <div class="alert alert-success py-1 px-2 mb-1">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i> <?= __('Key is set') ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning py-1 px-2 mb-1">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= __('No key set — required for backups') ?>
                            </div>
                        <?php endif; ?>
                        <input type="password" name="encryption_key" class="form-control form-control-sm"
                            placeholder="<?= $hasKey ? __('Enter new key to change') : __('Set your encryption key') ?>"
                            autocomplete="new-password">
                        <div class="form-text"><?= __('Keep this safe — you need it to restore backups.') ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('Scheduled Backups') ?></label>
                        <select name="schedule" class="form-select form-select-sm">
                            <option value="disabled" <?= $schedule === 'disabled' ? 'selected' : '' ?>><?= __('Disabled') ?></option>
                            <option value="daily" <?= $schedule === 'daily' ? 'selected' : '' ?>><?= __('Daily (3:00 AM)') ?></option>
                            <option value="weekly" <?= $schedule === 'weekly' ? 'selected' : '' ?>><?= __('Weekly (Sunday 3:00 AM)') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= __('Retention (days)') ?></label>
                        <input type="number" name="retention_days" class="form-control form-control-sm"
                            value="<?= h($retention) ?>" min="1" max="365">
                        <div class="form-text"><?= __('Backups older than this are automatically deleted.') ?></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold"><?= __('Storage') ?></label>
                        <div class="text-muted small">
                            <i class="bi bi-hdd" aria-hidden="true"></i> <?= h(ucfirst($storageType)) ?>
                        </div>
                    </div>

                    <?= $this->Form->button(__('Save Settings'), ['class' => 'btn btn-primary btn-sm']) ?>
                    <?= $this->Form->end() ?>
                </div>
            </div>
        </div>

        <!-- Backups List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-archive" aria-hidden="true"></i> <?= __('Backups, Export, and Import') ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 border-bottom bg-light">
                        <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-end">
                            <div>
                                <?= $this->Form->create(null, ['url' => ['action' => 'create'], 'class' => 'd-inline']) ?>
                                <?= $this->Form->button(
                                    '<i class="bi bi-download" aria-hidden="true"></i> ' . __('Export Backup'),
                                    [
                                        'class' => 'btn btn-success btn-sm',
                                        'escapeTitle' => false,
                                        'disabled' => !$hasKey || $restoreIsLocked,
                                        'title' => $restoreIsLocked ? __('A restore/import is currently running') : (!$hasKey ? __('Set an encryption key first') : ''),
                                    ],
                                ) ?>
                                <?= $this->Form->end() ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-8">
                                        <label for="backup-file-input" class="form-label mb-1 small fw-bold">
                                            <?= __('Import Backup File') ?>
                                        </label>
                                        <input type="file"
                                            id="backup-file-input"
                                            name="backup_file"
                                            class="form-control form-control-sm"
                                            accept=".kmpbackup,.enc,application/octet-stream"
                                            aria-describedby="backup-file-help"
                                            required>
                                        <div id="backup-file-help" class="form-text">
                                            <?= __('Choose a tenant .kmpbackup file, or a managed .json.gz.enc archive downloaded from Platform Admin.') ?>
                                        </div>
                                        <label for="backup-recovery-key-input" class="form-label mt-2 mb-1 small fw-bold">
                                            <?= __('Managed Backup Recovery Key') ?>
                                            <span class="text-muted fw-normal"><?= __('(required only for .json.gz.enc archives)') ?></span>
                                        </label>
                                        <input type="file"
                                            id="backup-recovery-key-input"
                                            name="recovery_key_file"
                                            class="form-control form-control-sm"
                                            accept=".json,application/json"
                                            aria-describedby="backup-recovery-key-help">
                                        <div id="backup-recovery-key-help" class="form-text">
                                            <?= __('Choose the matching .kmpbackup-key.json file. It works only with its bound archive and must be handled as a secret.') ?>
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <?= $this->Form->create(null, [
                                            'url' => ['action' => 'restore'],
                                            'type' => 'file',
                                            'class' => 'd-inline',
                                            'id' => 'backup-import-restore-form',
                                            'data-action' => 'submit->backup-restore-status#submitRestore',
                                            'data-file-input-id' => 'backup-file-input',
                                            'data-recovery-key-input-id' => 'backup-recovery-key-input',
                                            'data-restore-confirm-message' => __('Import this backup and replace all current data? This action cannot be undone.'),
                                            'data-restore-key-prompt' => __('Enter the encryption key for this backup file:'),
                                            'data-managed-key-required-message' => __('Choose the matching recovery-key file for this managed backup archive.'),
                                        ]) ?>
                                        <?= $this->Form->button(
                                            '<i class="bi bi-upload" aria-hidden="true"></i> ' . __('Import and Restore'),
                                            [
                                                'class' => 'btn btn-outline-warning btn-sm',
                                                'escapeTitle' => false,
                                                'disabled' => $restoreIsLocked,
                                                'title' => $restoreIsLocked ? __('A restore/import is currently running') : '',
                                            ],
                                        ) ?>
                                        <?= $this->Form->end() ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty(iterator_to_array($backups))): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-archive" style="font-size: 3rem;" aria-hidden="true"></i>
                            <p class="mt-2"><?= __('No backups yet. Set your encryption key and create your first backup.') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= __('Filename') ?></th>
                                        <th><?= __('Size') ?></th>
                                        <th><?= __('Tables') ?></th>
                                        <th><?= __('Rows') ?></th>
                                        <th><?= __('Status') ?></th>
                                        <th><?= __('State Details') ?></th>
                                        <th><?= __('Created') ?></th>
                                        <th class="text-end"><?= __('Actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td class="small"><?= h($backup->filename) ?></td>
                                            <td class="small"><?= $backup->size_bytes ? $this->Number->toReadableSize($backup->size_bytes) : '—' ?></td>
                                            <td class="small"><?= $backup->table_count ?? '—' ?></td>
                                            <td class="small"><?= $backup->row_count ? number_format($backup->row_count) : '—' ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = match ($backup->status) {
                                                    'completed' => 'bg-success',
                                                    'running' => 'bg-info',
                                                    'failed' => 'bg-danger',
                                                    default => 'bg-secondary',
                                                };
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= h($backup->status) ?></span>
                                            </td>
                                            <td class="small text-muted">
                                                <?php if (!empty($backup->notes)): ?>
                                                    <?= h($backup->notes) ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?= h($backup->created->nice()) ?></td>
                                            <td class="text-end">
                                                <?php if ($backup->status !== 'running' && !$restoreIsLocked): ?>
                                                    <?= $this->Html->link(
                                                        '<i class="bi bi-download" aria-hidden="true"></i>',
                                                        ['action' => 'download', $backup->id],
                                                        ['escape' => false, 'class' => 'btn btn-outline-primary btn-sm me-1', 'title' => __('Download')],
                                                    ) ?>
                                                    <?= $this->Form->create(null, [
                                                        'url' => ['action' => 'restore', $backup->id],
                                                        'class' => 'd-inline',
                                                        'data-action' => 'submit->backup-restore-status#submitRestore',
                                                        'data-restore-confirm-message' => __('Restore this backup? This will REPLACE all current data. This action cannot be undone.'),
                                                        'data-restore-key-prompt' => __('Enter the encryption key for this backup:'),
                                                    ]) ?>
                                                    <?= $this->Form->button(
                                                        '<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>',
                                                        [
                                                            'escapeTitle' => false,
                                                            'class' => 'btn btn-outline-warning btn-sm me-1',
                                                            'title' => __('Restore from this backup'),
                                                        ],
                                                    ) ?>
                                                    <?= $this->Form->end() ?>
                                                <?php endif; ?>
                                                <?php if (!$restoreIsLocked): ?>
                                                    <?= $this->Form->postLink(
                                                        '<i class="bi bi-trash" aria-hidden="true"></i>',
                                                        ['action' => 'delete', $backup->id],
                                                        [
                                                            'escape' => false,
                                                            'class' => 'btn btn-outline-danger btn-sm',
                                                            'title' => __('Delete'),
                                                            'confirm' => __('Delete this backup?'),
                                                        ],
                                                    ) ?>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="<?= __('A restore/import is currently running') ?>">
                                                        <i class="bi bi-lock" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2">
                            <?= $this->Paginator->numbers() ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="restoreProgressModal" tabindex="-1" aria-labelledby="restoreProgressModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" data-backup-restore-status-target="modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreProgressModalLabel"><?= __('Restore Progress') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>" data-backup-restore-status-target="modalClose"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-secondary" data-backup-restore-status-target="modalBadge"><?= __('idle') ?></span>
                        <div class="spinner-border spinner-border-sm text-warning" role="status" aria-hidden="true" data-backup-restore-status-target="modalSpinner"></div>
                    </div>
                    <div class="fw-semibold" data-backup-restore-status-target="modalMessage"><?= __('Waiting to start restore...') ?></div>
                    <div class="small text-muted mt-2" data-backup-restore-status-target="modalDetails"><?= __('No active restore.') ?></div>
                    <section class="mt-3" aria-labelledby="restore-progress-log-heading">
                        <h6 id="restore-progress-log-heading"><?= __('Restore Log') ?></h6>
                        <ol class="small mb-0 ps-3" data-backup-restore-status-target="modalLog" aria-live="polite" aria-relevant="additions text">
                            <li><?= __('No restore log entries have been written yet.') ?></li>
                        </ol>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-backup-restore-status-target="modalClose"><?= __('Close') ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
