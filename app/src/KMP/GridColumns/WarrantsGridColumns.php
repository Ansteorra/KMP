<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\Model\Entity\ActiveWindowBaseEntity;
use App\Model\Entity\Warrant;

/**
 * Warrants Grid Column Metadata
 *
 * Defines the column configuration for the warrants Dataverse-style grid view POC.
 * Columns follow the same structure as MembersGridColumns to reuse shared helpers
 * from BaseGridColumns.
 */
class WarrantsGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for warrants grid
     */
    public static function getColumns(): array
    {
        $columns = [
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
                'label' => 'Warrant',
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '220px',
                'alignment' => 'left',
            ],

            'member_id' => [
                'key' => 'member_id',
                'label' => 'Member',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'member.sca_name',
                'queryField' => 'Members.sca_name',
                'description' => 'Member receiving the warrant',
            ],

            'entity_type' => [
                'key' => 'entity_type',
                'label' => 'Type',
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '160px',
                'alignment' => 'left',
            ],

            'start_on' => [
                'key' => 'start_on',
                'label' => 'Starts',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'filterType' => 'date-range',
                'width' => '140px',
                'alignment' => 'left',
            ],

            'expires_on' => [
                'key' => 'expires_on',
                'label' => 'Expires',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
            ],

            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'badge',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => ActiveWindowBaseEntity::CURRENT_STATUS, 'label' => ActiveWindowBaseEntity::CURRENT_STATUS],
                    ['value' => ActiveWindowBaseEntity::UPCOMING_STATUS, 'label' => ActiveWindowBaseEntity::UPCOMING_STATUS],
                    ['value' => ActiveWindowBaseEntity::EXPIRED_STATUS, 'label' => ActiveWindowBaseEntity::EXPIRED_STATUS],
                    ['value' => ActiveWindowBaseEntity::DEACTIVATED_STATUS, 'label' => ActiveWindowBaseEntity::DEACTIVATED_STATUS],
                    ['value' => ActiveWindowBaseEntity::RELEASED_STATUS, 'label' => ActiveWindowBaseEntity::RELEASED_STATUS],
                    ['value' => ActiveWindowBaseEntity::REPLACED_STATUS, 'label' => ActiveWindowBaseEntity::REPLACED_STATUS],
                    ['value' => ActiveWindowBaseEntity::CANCELLED_STATUS, 'label' => ActiveWindowBaseEntity::CANCELLED_STATUS],
                    ['value' => Warrant::PENDING_STATUS, 'label' => Warrant::PENDING_STATUS],
                    ['value' => Warrant::DECLINED_STATUS, 'label' => Warrant::DECLINED_STATUS],
                ],
            ],

            'revoker_id' => [
                'key' => 'revoker_id',
                'label' => 'Revoked By',
                'type' => 'relation',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'revoked_by.sca_name',
                'queryField' => 'RevokedBy.sca_name',
            ],

            'revoked_reason' => [
                'key' => 'revoked_reason',
                'label' => 'Revoked Reason',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '240px',
                'alignment' => 'left',
            ],

            'actions' => [
                'key' => 'actions',
                'label' => '',
                'type' => 'actions',
                'required' => true,
                'defaultVisible' => true,
                'sortable' => false,
                'exportable' => false,
                'width' => '160px',
                'alignment' => 'right',
            ],
        ];

        return $columns;
    }

    /**
     * Get default visible columns
     */
    public static function getDefaultVisibleColumns(): array
    {
        return array_filter(
            static::getColumns(),
            fn($col) => !empty($col['defaultVisible'])
        );
    }

    /**
     * Get required columns (cannot be hidden)
     */
    public static function getRequiredColumns(): array
    {
        $required = [];
        foreach (static::getColumns() as $key => $col) {
            if (!empty($col['required'])) {
                $required[] = $key;
            }
        }
        return $required;
    }
}
