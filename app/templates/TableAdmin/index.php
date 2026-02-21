<?php

/**
 * @var \App\View\AppView $this
 * @var array<int, string> $tables
 * @var string $selectedTable
 * @var int $page
 * @var int $limit
 * @var int $totalRows
 * @var int $totalPages
 * @var array<int, string> $tableColumns
 * @var array<int, array<string, mixed>> $tableRows
 * @var string|null $tableError
 * @var string $sqlInput
 * @var string|null $sqlMessage
 * @var string|null $sqlError
 * @var bool $sqlHasResultSet
 * @var array<int, string> $sqlResultColumns
 * @var array<int, array<string, mixed>> $sqlResultRows
 * @var bool $sqlTruncated
 * @var int $sqlMaxRows
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Table Admin';
$this->KMP->endBlock();

$this->assign('title', __('Table Admin'));
?>

<div class="container-fluid">
    <div class="alert alert-warning py-2">
        <strong><?= __('Super User Only:') ?></strong>
        <?= __('This tool executes raw SQL directly against the active database.') ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><strong><?= __('Tables') ?></strong></div>
                <div class="card-body p-0">
                    <?php if (empty($tables)): ?>
                        <div class="p-3 text-muted small"><?= __('No tables found.') ?></div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($tables as $tableName): ?>
                                <?=
                                $this->Html->link(
                                    h($tableName),
                                    ['action' => 'index', '?' => ['table' => $tableName, 'page' => 1, 'limit' => $limit]],
                                    ['class' => 'list-group-item list-group-item-action' . ($selectedTable === $tableName ? ' active' : '')],
                                )
                                ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card mb-3">
                <div class="card-header"><strong><?= __('Ad Hoc SQL') ?></strong></div>
                <div class="card-body">
                    <?= $this->Form->create(null, ['url' => ['action' => 'index'], 'id' => 'table-admin-sql-form']) ?>
                    <?= $this->Form->hidden('selected_table', ['value' => $selectedTable]) ?>
                    <?= $this->Form->hidden('selected_page', ['value' => $page]) ?>
                    <?= $this->Form->hidden('selected_limit', ['value' => $limit]) ?>
                    <?= $this->Form->hidden('confirm_mutation', ['value' => '0']) ?>
                    <div class="mb-2">
                        <?= $this->Form->control('sql_query', [
                            'type' => 'textarea',
                            'label' => false,
                            'rows' => 5,
                            'value' => $sqlInput,
                            'placeholder' => 'SELECT * FROM members LIMIT 20;',
                            'class' => 'form-control font-monospace',
                        ]) ?>
                    </div>
                    <div class="form-text mb-2"><?= __('INSERT, UPDATE, DELETE, and TRUNCATE statements require confirmation. INSERT/UPDATE/DELETE run in transactions.') ?></div>
                    <?= $this->Form->button(__('Run SQL'), ['class' => 'btn btn-danger btn-sm']) ?>
                    <?= $this->Form->end() ?>

                    <?php if ($sqlError): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <strong><?= __('SQL Error:') ?></strong> <?= h($sqlError) ?>
                        </div>
                    <?php elseif ($sqlMessage): ?>
                        <div class="alert alert-success mt-3 mb-0">
                            <?= h($sqlMessage) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sqlHasResultSet): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <?php foreach ($sqlResultColumns as $column): ?>
                                            <th><?= h($column) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sqlResultRows as $row): ?>
                                        <tr>
                                            <?php foreach ($sqlResultColumns as $column): ?>
                                                <?php $value = $row[$column] ?? null; ?>
                                                <td class="small"><?= $value === null ? 'NULL' : h((string)$value) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($sqlTruncated): ?>
                                <div class="small text-muted"><?= __('Showing first {0} rows only.', $sqlMaxRows) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= __('Table Records') ?></strong>
                    <?php if ($selectedTable !== ''): ?>
                        <form method="get" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="table" value="<?= h($selectedTable) ?>">
                            <input type="hidden" name="page" value="1">
                            <label for="limit" class="small mb-0"><?= __('Rows per page') ?></label>
                            <select id="limit" name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ([25, 50, 100, 200] as $limitOption): ?>
                                    <option value="<?= $limitOption ?>" <?= $limit === $limitOption ? 'selected' : '' ?>><?= $limitOption ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($tableError): ?>
                        <div class="alert alert-danger mb-0"><?= h($tableError) ?></div>
                    <?php elseif ($selectedTable === ''): ?>
                        <div class="text-muted"><?= __('Select a table to browse records.') ?></div>
                    <?php else: ?>
                        <div class="mb-2 small text-muted">
                            <?= __('Table: {0}', h($selectedTable)) ?> |
                            <?= __('Rows: {0}', number_format($totalRows)) ?> |
                            <?= __('Page {0} of {1}', $page, $totalPages) ?>
                        </div>

                        <div class="mb-2">
                            <?php foreach ($tableColumns as $column): ?>
                                <span class="badge bg-secondary me-1 mb-1"><?= h($column) ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <?php foreach ($tableColumns as $column): ?>
                                            <th><?= h($column) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tableRows)): ?>
                                        <tr>
                                            <td colspan="<?= max(1, count($tableColumns)) ?>" class="text-muted small"><?= __('No records found for this page.') ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tableRows as $row): ?>
                                            <tr>
                                                <?php foreach ($tableColumns as $column): ?>
                                                    <?php $value = $row[$column] ?? null; ?>
                                                    <td class="small"><?= $value === null ? 'NULL' : h((string)$value) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between">
                            <?= $this->Html->link(
                                __('Previous'),
                                ['action' => 'index', '?' => ['table' => $selectedTable, 'page' => max(1, $page - 1), 'limit' => $limit]],
                                ['class' => 'btn btn-outline-secondary btn-sm' . ($page <= 1 ? ' disabled' : '')],
                            ) ?>
                            <?= $this->Html->link(
                                __('Next'),
                                ['action' => 'index', '?' => ['table' => $selectedTable, 'page' => min($totalPages, $page + 1), 'limit' => $limit]],
                                ['class' => 'btn btn-outline-secondary btn-sm' . ($page >= $totalPages ? ' disabled' : '')],
                            ) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('table-admin-sql-form');
    if (!form) {
        return;
    }

    const queryInput = form.querySelector('[name="sql_query"]');
    const confirmationInput = form.querySelector('[name="confirm_mutation"]');
    const mutationPattern = /^\s*(insert|update|delete|truncate)\b/i;

    form.addEventListener('submit', function (event) {
        if (!queryInput || !confirmationInput) {
            return;
        }

        confirmationInput.value = '0';
        const query = String(queryInput.value || '').trim();
        const match = query.match(mutationPattern);
        if (!match) {
            return;
        }

        const statementType = match[1].toUpperCase();
        const confirmMessage = statementType === 'TRUNCATE'
            ? 'This will execute a TRUNCATE statement and remove all rows from the target table. Continue?'
            : `This will execute a ${statementType} statement and modify data. Continue?`;
        const confirmed = window.confirm(confirmMessage);
        if (!confirmed) {
            event.preventDefault();
            return;
        }

        confirmationInput.value = '1';
    });
});
</script>
