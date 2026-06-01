<?php
declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Grid column definitions for the BestowalStatuses index view.
 */
class BestowalStatusesGridColumns extends BaseGridColumns
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
                'width' => '250px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/awards/bestowal-statuses/view/:id',
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
            'state_count' => [
                'key' => 'state_count',
                'label' => __('States'),
                'type' => 'integer',
                'sortable' => false,
                'filterable' => false,
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
