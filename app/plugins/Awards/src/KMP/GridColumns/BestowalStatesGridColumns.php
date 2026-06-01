<?php
declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Grid column definitions for the BestowalStates index view.
 */
class BestowalStatesGridColumns extends BaseGridColumns
{
    /**
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
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/awards/bestowal-states/view/:id',
            ],
            'status_id' => [
                'key' => 'status_id',
                'label' => __('Status'),
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'bestowal_status.name',
            ],
            'sort_order' => [
                'key' => 'sort_order',
                'label' => __('Sort Order'),
                'type' => 'integer',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
            ],
            'supports_gathering' => [
                'key' => 'supports_gathering',
                'label' => __('Gathering'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '110px',
                'alignment' => 'center',
            ],
            'locks_recommendations' => [
                'key' => 'locks_recommendations',
                'label' => __('Locks Recs'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '110px',
                'alignment' => 'center',
            ],
            'is_hidden' => [
                'key' => 'is_hidden',
                'label' => __('Hidden'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
            ],
            'created' => [
                'key' => 'created',
                'label' => __('Created'),
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [
            'view' => [
                'key' => 'view',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-eye-fill',
                'class' => 'btn-sm btn btn-secondary',
                'permission' => 'view',
            ],
        ];
    }
}
