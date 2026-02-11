<?php

/**
 * Member Authorizations Grid Table Element
 * 
 * Custom table element for member authorization lists that handles
 * the renew/revoke/retract button logic with modals.
 * 
 * @var \App\View\AppView $this
 * @var iterable $data The authorization data
 * @var array $gridState Grid state object
 * @var string $tableFrameId Table frame ID
 * @var int $member_id Member ID for action buttons
 */

$currentViewId = $gridState['view']['currentId'] ?? 'current';
$isCurrentView = $currentViewId === 'current';
$isPendingView = $currentViewId === 'pending';
$isPreviousView = $currentViewId === 'previous';
$visibleColumns = $gridState['columns']['visible'] ?? [];
$allColumns = $gridState['columns']['all'] ?? [];
$hasActions = $isCurrentView || $isPendingView;
?>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <?php foreach ($visibleColumns as $columnKey): ?>
                <?php $column = $allColumns[$columnKey] ?? null; ?>
                <?php if ($column): ?>
                <th scope="col">
                    <?= h($column['label'] ?? $columnKey) ?>
                </th>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($hasActions): ?>
                <th scope="col" class="actions"></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data) || (is_countable($data) && count($data) === 0)): ?>
            <tr>
                <td colspan="<?= count($visibleColumns) + ($hasActions ? 1 : 0) ?>" class="text-center text-muted py-4">
                    <?php if ($isCurrentView): ?>
                    No active authorizations.
                    <?php elseif ($isPendingView): ?>
                    No pending authorization requests.
                    <?php elseif ($isPreviousView): ?>
                    No previous authorizations.
                    <?php else: ?>
                    No records found.
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($data as $authorization): ?>
            <tr>
                <?php foreach ($visibleColumns as $columnKey): ?>
                <?php $column = $allColumns[$columnKey] ?? null; ?>
                <?php if ($column): ?>
                <td>
                    <?php
                                    // Get the value based on column configuration
                                    $value = null;
                                    $renderField = $column['renderField'] ?? null;

                                    if ($renderField) {
                                        // Handle nested field access (e.g., 'activity.name')
                                        $parts = explode('.', $renderField);
                                        $value = $authorization;
                                        foreach ($parts as $part) {
                                            if (is_object($value) && isset($value->{$part})) {
                                                $value = $value->{$part};
                                            } elseif (is_array($value) && isset($value[$part])) {
                                                $value = $value[$part];
                                            } else {
                                                $value = null;
                                                break;
                                            }
                                        }
                                    } else {
                                        $value = $authorization->{$columnKey} ?? null;
                                    }

                                    // Format the value based on type
                                    $type = $column['type'] ?? 'string';
                                    if ($value === null) {
                                        echo '-';
                                    } elseif ($type === 'date' || $type === 'datetime') {
                                        if ($value instanceof \DateTimeInterface) {
                                            echo $this->Timezone->format($value, null, null, \IntlDateFormatter::SHORT);
                                        } else {
                                            echo h($value);
                                        }
                                    } elseif ($type === 'boolean') {
                                        echo $value ? __('Yes') : __('No');
                                    } else {
                                        echo h($value);
                                    }
                                    ?>
                </td>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($isCurrentView): ?>
                <td class="actions text-end text-nowrap">
                    <button type="button" class="btn-sm btn btn-primary renew-btn" data-bs-toggle="modal"
                        data-bs-target="#renewalModal" data-controller="outlet-btn"
                        data-action="click->outlet-btn#fireNotice"
                        data-outlet-btn-btn-data-value='{"id":<?= $authorization->id ?>,"activity":<?= $authorization->activity->id ?? 0 ?>}'>
                        <?= __('Renew') ?>
                    </button>
                    <?php if ($user->can('revoke', $authorization)): ?>
                    <button type="button" class="btn-sm btn btn-danger revoke-btn" data-bs-toggle="modal"
                        data-bs-target="#revokeModal" data-controller="outlet-btn"
                        data-action="click->outlet-btn#fireNotice"
                        data-outlet-btn-btn-data-value='{"id":<?= $authorization->id ?>,"activity":<?= $authorization->activity->id ?? 0 ?>}'>
                        <?= __('Revoke') ?>
                    </button>
                    <?php endif; ?>
                </td>
                <?php elseif ($isPendingView): ?>
                <td class="actions text-end text-nowrap">
                    <?= $this->Form->create(null, [
                        'url' => ["controller" => "Authorizations", "action" => "retract", $authorization->id],
                        'data-turbo-frame' => '_top',
                        'style' => 'display:inline;',
                    ]) ?>
                    <button type="submit" class="btn-sm btn btn-warning retract-btn"
                        onclick="return confirm('<?= h(__("Are you sure you want to retract this authorization request?")) ?>');">
                        <?= __("Retract") ?>
                    </button>
                    <?= $this->Form->end() ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first('<< ' . __('first')) ?>
        <?= $this->Paginator->prev('< ' . __('previous')) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next(__('next') . ' >') ?>
        <?= $this->Paginator->last(__('last') . ' >>') ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
    </p>
</div>