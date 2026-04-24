<?php

declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Grid column definitions for the Awards index view
 * Used by Awards\Controller\AwardsController::gridData()
 */
class AwardsGridColumns extends BaseGridColumns
{
    /**
     * Get row action configurations for awards shown in a gathering activity context.
     *
     * @param int $activityId Gathering activity identifier.
     * @return array<string, array<string, mixed>>
     */
    public static function getActivityRowActions(int $activityId): array
    {
        return [
            'removeActivity' => [
                'key' => 'removeActivity',
                'type' => 'postLink',
                'label' => '',
                'icon' => 'bi-x-circle-fill',
                'class' => 'btn btn-sm btn-danger',
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Awards',
                    'action' => 'remove-activity',
                    'idField' => 'id',
                    'extraArgs' => [$activityId],
                ],
                'confirmMessage' => 'Remove "{{name}}" from this activity?',
                'turbo' => true,
            ],
        ];
    }

    /**
     * Get column metadata for the awards grid
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
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/awards/awards/view/:id',
            ],
            'abbreviation' => [
                'key' => 'abbreviation',
                'label' => __('Abbreviation'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '200px',
                'alignment' => 'left',
            ],
            'domain_name' => [
                'key' => 'domain_name',
                'label' => __('Domain'),
                'type' => 'relation',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterDataSource' => 'Awards.Domains',
                'searchable' => false,
                'defaultVisible' => true,
                'queryField' => 'Domains.id',
                'renderField' => 'domain.name',
                'width' => '150px',
                'alignment' => 'left',
            ],
            'level_name' => [
                'key' => 'level_name',
                'label' => __('Level'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterDataSource' => 'Awards.Levels',
                'queryField' => 'Levels.id',
                'renderField' => 'awardLevel.name',
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],
            'branch_id' => [
                'key' => 'branch_id',
                'label' => __('Branch'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterDataSource' => 'Branches',
                'searchable' => false,
                'defaultVisible' => true,
                'queryField' => 'Branches.id',
                'renderField' => 'branch.name',
                'width' => '150px',
                'alignment' => 'left',
            ],
            'disabled' => [
                'key' => 'disabled',
                'label' => __('Disabled'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'queryField' => 'Awards.is_active',
                'renderField' => 'disabled',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '0', 'label' => 'Yes'],
                    ['value' => '1', 'label' => 'No'],
                ],
            ],
            'description' => [
                'key' => 'description',
                'label' => __('Description'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '250px',
                'alignment' => 'left',
            ],
        ];
    }
}
