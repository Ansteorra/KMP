<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Branches Grid Column Metadata
 * 
 * Provides tree-aware rendering with visual hierarchy indicators.
 */
class BranchesGridColumns extends BaseGridColumns
{
    /**
     * Get available system views for the Branches grid.
     *
     * @param array<string, mixed> $options Optional context (unused)
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [];
    }

    public static function getColumns(): array
    {
        return [
            'id' => [
                'key' => 'id',
                'label' => 'ID',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '80px',
                'alignment' => 'right',
            ],

            'lft' => [
                'key' => 'lft',
                'label' => 'Hierarchy',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '80px',
                'alignment' => 'right',
                'description' => 'Tree left value - sort by this to maintain hierarchical order',
            ],

            'path' => [
                'key' => 'path',
                'label' => 'Branch',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '300px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/branches/view/:public_id',
                'exportable' => true,
                'cellRenderer' => [self::class, 'renderTreePath'],
            ],

            'name' => [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '200px',
                'alignment' => 'left',
            ],

            'type' => [
                'key' => 'type',
                'label' => 'Type',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'left',
                'filterOptionsSource' => [
                    'appSetting' => 'Branches.Types',
                ],
            ],

            'location' => [
                'key' => 'location',
                'label' => 'Location',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
            ],

            'parent_id' => [
                'key' => 'parent_id',
                'label' => 'Parent Branch',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'parent.name',
                'queryField' => 'ParentBranches.name',
            ],

            'can_have_members' => [
                'key' => 'can_have_members',
                'label' => 'Has Members',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Created',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'modified' => [
                'key' => 'modified',
                'label' => 'Modified',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * Custom cell renderer for tree path display.
     * 
     * Renders the branch hierarchy with visual indentation.
     *
     * @param mixed $value The cell value (path string)
     * @param object|array $row The full row data
     * @param \App\View\AppView $view The view instance
     * @return string Rendered HTML
     */
    public static function renderTreePath($value, $row, $view): string
    {
        $depth = $row->tree_depth ?? 0;
        $name = $row->name ?? '';

        $html = '';

        // Add indentation based on depth
        if ($depth > 0) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
            $html .= '<span class="text-muted">' . $indent . '└ </span>';
        }

        // Show branch name
        $html .= htmlspecialchars($name);

        return $html;
    }
}