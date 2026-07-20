<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var array<string, array{label: string, description: string}> $queries
 * @var array{query: string, label: string, description: string, columns: list<string>, rows: list<array<string, mixed>>, page: int, limit: int, hasNext: bool} $result
 */
$this->assign('title', __('Platform Data Console'));
?>
<h1 class="h2 mb-3"><?= __('Platform Data Console') ?></h1>
<p class="text-muted"><?= __('Read-only, allowlisted platform metadata queries. Sensitive fields and secret-like values are redacted before display.') ?></p>

<section class="card mb-4" aria-labelledby="data-console-query-heading">
    <div class="card-body">
        <h2 id="data-console-query-heading" class="h5"><?= __('Select query') ?></h2>
        <?= $this->Form->create(null, ['type' => 'get', 'valueSources' => ['query']]) ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <?= $this->Form->control('query', [
                    'type' => 'select',
                    'label' => __('Allowlisted query'),
                    'options' => array_map(static fn(array $query): string => $query['label'], $queries),
                    'value' => $result['query'],
                    'class' => 'form-select',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('limit', [
                    'type' => 'number',
                    'label' => __('Rows per page'),
                    'min' => 1,
                    'max' => 100,
                    'value' => $result['limit'],
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->button(__('Run query'), ['class' => 'btn btn-primary']) ?>
            </div>
        </div>
        <?= $this->Form->end() ?>
    </div>
</section>

<section class="card" aria-labelledby="data-console-results-heading">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <div>
                <h2 id="data-console-results-heading" class="h5 mb-1"><?= h($result['label']) ?></h2>
                <p class="text-muted mb-0"><?= h($result['description']) ?></p>
            </div>
            <p class="mb-0 small text-muted"><?= __('Page {0}; limit {1}', $result['page'], $result['limit']) ?></p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <caption class="visually-hidden"><?= __('Results for {0}', $result['label']) ?></caption>
                <thead>
                    <tr>
                        <?php foreach ($result['columns'] as $column) : ?>
                            <th scope="col"><?= h($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row) : ?>
                    <tr>
                        <?php foreach ($result['columns'] as $column) : ?>
                            <td><?= h((string)($row[$column] ?? '')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($result['rows'] === []) : ?>
                    <tr><td colspan="<?= count($result['columns']) ?>" class="text-muted"><?= __('No rows found.') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav aria-label="<?= h(__('Data console pagination')) ?>">
            <ul class="pagination mb-0">
                <li class="page-item <?= $result['page'] <= 1 ? 'disabled' : '' ?>">
                    <?= $this->Html->link(__('Previous'), ['?' => ['query' => $result['query'], 'limit' => $result['limit'], 'page' => max(1, $result['page'] - 1)]], ['class' => 'page-link', 'aria-disabled' => $result['page'] <= 1 ? 'true' : null]) ?>
                </li>
                <li class="page-item <?= !$result['hasNext'] ? 'disabled' : '' ?>">
                    <?= $this->Html->link(__('Next'), ['?' => ['query' => $result['query'], 'limit' => $result['limit'], 'page' => $result['page'] + 1]], ['class' => 'page-link', 'aria-disabled' => !$result['hasNext'] ? 'true' : null]) ?>
                </li>
            </ul>
        </nav>
    </div>
</section>
