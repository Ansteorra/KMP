<?php

declare(strict_types=1);

namespace Officers\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use App\Model\Entity\ActiveWindowBaseEntity;
use Cake\I18n\Date;
use Officers\Model\Entity\Officer;

/**
 * Grid column definitions for the Offices index view
 * Used by Officers\Controller\OfficesController::gridData()
 */
class OfficesGridColumns extends BaseGridColumns
{
    /**
     * Get available system views for the Offices grid.
     *
     * @param array<string, mixed> $options Optional context (unused)
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [];
    }

    /**
     * Get column metadata for the offices grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'name' => [
                'key' => 'name',
                'label' => __('Name'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '180px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/officers/offices/view/:id',
            ],
            'department_id' => [
                'key' => 'department_id',
                'label' => __('Department'),
                'type' => 'relation',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterDataSource' => 'Officers.Departments',
                'searchable' => false,
                'defaultVisible' => true,
                'renderField' => 'department.name',
                'queryField' => 'Departments.id',
                'width' => '150px',
                'alignment' => 'left',
            ],
            'term_length' => [
                'key' => 'term_length',
                'label' => __('Term (Month)'),
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
            ],
            'required_office' => [
                'key' => 'required_office',
                'label' => __('Required'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '80px',
                'alignment' => 'center',
            ],
            'can_skip_report' => [
                'key' => 'can_skip_report',
                'label' => __('Skip Report'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => false,
                'defaultVisible' => false,
                'width' => '90px',
                'alignment' => 'center',
            ],
            'requires_warrant' => [
                'key' => 'requires_warrant',
                'label' => __('Warrant'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '80px',
                'alignment' => 'center',
            ],
            'only_one_per_branch' => [
                'key' => 'only_one_per_branch',
                'label' => __('One Per Branch'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => false,
                'defaultVisible' => false,
                'width' => '100px',
                'alignment' => 'center',
            ],
            'reports_to_name' => [
                'key' => 'reports_to_name',
                'label' => __('Reports To'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],
            'deputy_to_name' => [
                'key' => 'deputy_to_name',
                'label' => __('Deputy To'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],
            'grants_role_name' => [
                'key' => 'grants_role_name',
                'label' => __('Grants Role'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],
        ];
    }
}