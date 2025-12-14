<?php

/**
 * Authorization Approvals Grid Table Element
 * 
 * Custom table element for authorization approval queues that handles
 * the complex approve/deny button logic with modals.
 * 
 * @var \App\View\AppView $this
 * @var iterable $data The approval data
 * @var array $gridState Grid state object
 * @var string $tableFrameId Table frame ID
 */

$currentViewId = $gridState['view']['currentId'] ?? 'pending';
$isPendingView = $currentViewId === 'pending';
$isApprovedView = $currentViewId === 'approved';
$isDeniedView = $currentViewId === 'denied';
$visibleColumns = $gridState['columns']['visible'] ?? [];
$allColumns = $gridState['columns']['all'] ?? [];
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
                <?php if ($isPendingView): ?>
                    <th scope="col" class="actions"></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data) || (is_countable($data) && count($data) === 0)): ?>
                <tr>
                    <td colspan="<?= count($visibleColumns) + ($isPendingView ? 1 : 0) ?>" class="text-center text-muted py-4">
                        <?php if ($isPendingView): ?>
                            No pending authorization requests.
                        <?php elseif ($isApprovedView): ?>
                            No approved authorization requests.
                        <?php elseif ($isDeniedView): ?>
                            No denied authorization requests.
                        <?php else: ?>
                            No records found.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($data as $request): ?>
                    <?php
                    // Calculate if more approvals are needed (for pending view)
                    $hasMoreApprovalsToGo = false;
                    if ($isPendingView && isset($request->authorization)) {
                        $authsNeeded = $request->authorization->is_renewal
                            ? $request->authorization->activity->num_required_renewers
                            : $request->authorization->activity->num_required_authorizors;
                        $hasMoreApprovalsToGo = ($authsNeeded - $request->authorization->approval_count) > 1;
                    }
                    ?>
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
                                        // Handle nested field access (e.g., 'authorization.member.sca_name')
                                        $parts = explode('.', $renderField);
                                        $value = $request;
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
                                        $value = $request->{$columnKey} ?? null;
                                    }

                                    // Format the value based on type
                                    $type = $column['type'] ?? 'string';
                                    if ($value === null) {
                                        echo '';
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
                        <?php if ($isPendingView): ?>
                            <td class="actions text-end text-nowrap">
                                <?php if ($hasMoreApprovalsToGo): ?>
                                    <button type="button" class="btn btn-primary approve-btn" data-bs-toggle="modal"
                                        data-bs-target="#approveAndAssignModal"
                                        data-controller="outlet-btn" data-action="click->outlet-btn#fireNotice"
                                        data-outlet-btn-btn-data-value='{"id":<?= $request->id ?>}'>Approve</button>
                                <?php else: ?>
                                    <?= $this->Form->postLink(
                                        __("Approve"),
                                        ["action" => "approve", $request->id],
                                        [
                                            "confirm" => __(
                                                "Are you sure you want to approve {0} for {1}?",
                                                $request->authorization->member->sca_name ?? 'this member',
                                                $request->authorization->activity->name ?? 'this authorization',
                                            ),
                                            "title" => __("Approve"),
                                            "class" => "btn-sm btn btn-primary",
                                        ],
                                    ) ?>
                                <?php endif; ?>
                                <button type="button" class="btn-sm btn btn-secondary deny-btn" data-bs-toggle="modal"
                                    data-bs-target="#denyModal" data-controller="outlet-btn"
                                    data-action="click->outlet-btn#fireNotice"
                                    data-outlet-btn-btn-data-value='{"id":<?= $request->id ?>}'>
                                    Deny</button>
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
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
</div>