<?php

declare(strict_types=1);

namespace Activities\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Activities Grid Column Metadata
 *
 * Defines all available columns for the Activities data grid.
 * Activities represent authorization types within the KMP system (e.g., "Marshal", "Water Bearer").
 */
class ActivitiesGridColumns extends BaseGridColumns
{
    /**
     * Get available system views for the Activities grid.
     *
     * @param array<string, mixed> $options May contain 'activityGroups' for dropdown filtering
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [];
    }

    /**
     * Get column metadata for activities grid
     *
     * @return array<string, array<string, mixed>>
     */
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

            'name' => [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/activities/activities/view/:id',
            ],

            'activity_group_name' => [
                'key' => 'activity_group_name',
                'label' => 'Activity Group',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
            ],

            'grants_role_name' => [
                'key' => 'grants_role_name',
                'label' => 'Grants Role',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'term_length' => [
                'key' => 'term_length',
                'label' => 'Duration (Months)',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '130px',
                'alignment' => 'center',
            ],

            'minimum_age' => [
                'key' => 'minimum_age',
                'label' => 'Min Age',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '90px',
                'alignment' => 'center',
            ],

            'maximum_age' => [
                'key' => 'maximum_age',
                'label' => 'Max Age',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '90px',
                'alignment' => 'center',
            ],

            'num_required_authorizors' => [
                'key' => 'num_required_authorizors',
                'label' => '# for Auth',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '90px',
                'alignment' => 'center',
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
