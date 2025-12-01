<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Branches Grid Column Metadata
 */
class BranchesGridColumns extends BaseGridColumns
{
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
                'width' => '350px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/branches/view/:id',
                'exportable' => true,
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
                'filterOptionsSource' => 'branchTypes',
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
                'defaultVisible' => true,
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

            'member_count' => [
                'key' => 'member_count',
                'label' => 'Member Count',
                'type' => 'number',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'right',
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
}
