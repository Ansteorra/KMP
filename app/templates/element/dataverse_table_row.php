<?php
declare(strict_types=1);

use Cake\I18n\DateTime;

/**
 * Single Dataverse grid table row (Turbo Stream replace target).
 *
 * @var \App\View\AppView $this
 * @var object|array $row
 * @var array $columns
 * @var array $visibleColumns
 * @var string $controllerName
 * @var string $primaryKey
 * @var string $gridKey
 * @var array $rowActions
 * @var \Authorization\Identity|null $user
 * @var bool $enableBulkSelection
 * @var array $bulkSelectionDataFields
 * @var string|null $bulkSelectionDisabledField
 * @var string|null $bulkSelectionDisabledLabel
 * @var bool $bulkSelectionHideDisabledControl
 * @var string|null $bulkSelectionLabel
 * @var string|null $rowDomIdPrefix
 * @var bool $showActionsColumn
 */

$rowId = is_array($row) ? ($row[$primaryKey] ?? null) : ($row->{$primaryKey} ?? null);
$bulkSelectionLabel = $bulkSelectionLabel ?? __('Select row {0}', $rowId);
$bulkSelectionDisabledLabel = $bulkSelectionDisabledLabel ?? __('This row cannot be selected for bulk actions');
$bulkSelectionHideDisabledControl = $bulkSelectionHideDisabledControl ?? false;
$rowDomId = null;
if ($rowDomIdPrefix !== null && $rowDomIdPrefix !== '' && $rowId !== null) {
    $rowDomId = $rowDomIdPrefix . '-row-' . $rowId;
}
?>
<tr<?= $rowDomId !== null ? ' id="' . h($rowDomId) . '"' : '' ?> data-id="<?= h($rowId) ?>">
    <?php if ($enableBulkSelection) : ?>
        <?php
        $bulkRowDisabled = false;
        if ($bulkSelectionDisabledField !== null && $bulkSelectionDisabledField !== '') {
            $disabledValue = is_array($row)
                ? ($row[$bulkSelectionDisabledField] ?? null)
                : ($row->{$bulkSelectionDisabledField} ?? null);
            $bulkRowDisabled = !empty($disabledValue);
        }
        ?>
        <td style="text-align: center;">
            <?php if ($bulkRowDisabled && $bulkSelectionHideDisabledControl) : ?>
                <span class="visually-hidden"><?= h($bulkSelectionDisabledLabel) ?></span>
            <?php else : ?>
                <input type="checkbox"
                       class="form-check-input"
                       value="<?= h($rowId) ?>"
                       data-<?= h($controllerName) ?>-target="rowCheckbox"
                       data-action="change-><?= h($controllerName) ?>#toggleRowSelection"
                       aria-label="<?= h($bulkSelectionLabel) ?>"
                       <?php if ($bulkRowDisabled) : ?>
                       disabled
                       title="<?= h($bulkSelectionDisabledLabel) ?>"
                       <?php endif; ?>
                       <?php foreach ($bulkSelectionDataFields as $attr => $field) : ?>
                       data-<?= h($attr) ?>="<?= h(is_array($row) ? ($row[$field] ?? '') : ($row->{$field} ?? '')) ?>"
                       <?php endforeach; ?>>
            <?php endif; ?>
        </td>
    <?php endif; ?>
    <?php foreach ($visibleColumns as $columnKey) : ?>
        <?php if (!isset($columns[$columnKey])) {
            continue;
        } ?>
        <?php $column = $columns[$columnKey]; ?>
        <td style="text-align: <?= h($column['alignment'] ?? 'left') ?>;">
            <?php
            $renderField = is_string($column['renderField'] ?? null) ? $column['renderField'] : $columnKey;
            $value = $row;
            foreach (explode('.', $renderField) as $part) {
                if (is_array($value)) {
                    $value = $value[$part] ?? null;
                } elseif (is_object($value)) {
                    $value = $value->{$part} ?? null;
                } else {
                    $value = null;
                    break;
                }
            }
            $hasClickAction = !empty($column['clickAction']);
            $clickAction = $column['clickAction'] ?? null;

            if (!empty($column['cellRenderer']) && is_callable($column['cellRenderer'])) {
                $renderedContent = $column['cellRenderer']($value, $row, $this);

                if ($hasClickAction && $renderedContent !== '') {
                    echo $this->element('dataverse_table_cell_action', [
                        'content' => $renderedContent,
                        'clickAction' => $clickAction,
                        'clickActionPermission' => $column['clickActionPermission'] ?? null,
                        'clickActionPermissionArgs' => $column['clickActionPermissionArgs'] ?? [],
                        'clickActionUrl' => $column['clickActionUrl'] ?? null,
                        'row' => $row,
                        'user' => $user,
                        'primaryKey' => $primaryKey,
                        'columnKey' => $columnKey,
                    ]);
                } else {
                    echo $renderedContent;
                }
            } else {
                $type = $column['type'] ?? 'string';

                ob_start();
                switch ($type) {
                    case 'relation':
                        echo $value !== null ? h($value) : '<span class="text-muted">—</span>';
                        break;
                    case 'boolean':
                        echo $value ? '<kmp-icon name="circle-check" class="text-success" aria-hidden="true"></kmp-icon>' :
                            '<kmp-icon name="circle-x" class="text-danger" aria-hidden="true"></kmp-icon>';
                        break;
                    case 'badge':
                        $badgeConfig = $column['badgeConfig'] ?? null;
                        if ($badgeConfig) {
                            if ($value === null || $value === '') {
                                $config = $badgeConfig['nullValue'] ?? ['text' => 'None', 'class' => 'bg-secondary'];
                            } else {
                                $config = $badgeConfig['hasValue'] ?? ['text' => 'Set', 'class' => 'bg-primary'];
                            }
                            echo '<span class="badge ' . h($config['class']) . '">' . h($config['text']) . '</span>';
                        } else {
                            if ($value) {
                                echo '<span class="badge bg-success">Active</span>';
                            } else {
                                echo '<span class="badge bg-secondary">Inactive</span>';
                            }
                        }
                        break;
                    case 'date':
                        if ($value instanceof DateTime) {
                            echo h($this->Timezone->date($value));
                        } elseif ($value) {
                            echo h($value);
                        } else {
                            echo '<span class="text-muted">—</span>';
                        }
                        break;
                    case 'datetime':
                        if ($value instanceof DateTime) {
                            echo h($this->Timezone->format($value));
                        } elseif ($value) {
                            echo h($value);
                        } else {
                            echo '<span class="text-muted">—</span>';
                        }
                        break;
                    case 'number':
                    case 'integer':
                        echo $value !== null ? h($value) : '<span class="text-muted">—</span>';
                        break;
                    case 'html':
                        if ($value !== null && $value !== '') {
                            echo $value;
                        } else {
                            echo '<span class="text-muted">—</span>';
                        }
                        break;
                    case 'email':
                        if ($value !== null && $value !== '') {
                            echo '<a href="mailto:' . h($value) . '">' . h($value) . '</a>';
                        } else {
                            echo '<span class="text-muted">—</span>';
                        }
                        break;
                    case 'string':
                    default:
                        if ($value !== null && $value !== '') {
                            echo h($value);
                        } else {
                            echo '<span class="text-muted">—</span>';
                        }
                        break;
                }
                $renderedContent = ob_get_clean();

                if ($hasClickAction) {
                    echo $this->element('dataverse_table_cell_action', [
                        'content' => $renderedContent,
                        'clickAction' => $clickAction,
                        'clickActionPermission' => $column['clickActionPermission'] ?? null,
                        'clickActionPermissionArgs' => $column['clickActionPermissionArgs'] ?? [],
                        'clickActionUrl' => $column['clickActionUrl'] ?? null,
                        'row' => $row,
                        'user' => $user,
                        'primaryKey' => $primaryKey,
                        'columnKey' => $columnKey,
                    ]);
                } else {
                    echo $renderedContent;
                }
            }
            ?>
        </td>
    <?php endforeach; ?>
    <?php if ($showActionsColumn) : ?>
        <td class="text-end text-nowrap">
            <?php
            if (!empty($rowActions)) {
                echo $this->element('dataverse_table_row_actions', [
                    'actions' => $rowActions,
                    'row' => $row,
                    'user' => $user,
                ]);
            }
            ?>
        </td>
    <?php endif; ?>
</tr>
