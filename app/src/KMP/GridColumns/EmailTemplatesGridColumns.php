<?php
declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Email Templates Grid Column Metadata
 *
 * Defines all available columns for the Email Templates data grid.
 * Includes slug, subject, and status information.
 */
class EmailTemplatesGridColumns extends BaseGridColumns
{
    /**
     * Get available system views for the Email Templates grid.
     *
     * @param array<string, mixed> $options Optional context (unused)
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [];
    }

    /**
     * Get column metadata for email templates grid
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

            'slug' => [
                'key' => 'slug',
                'label' => 'Slug',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/email-templates/edit/:id',
            ],

            'name' => [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
            ],

            'description' => [
                'key' => 'description',
                'label' => 'Description',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '280px',
                'alignment' => 'left',
            ],

            'subject_template' => [
                'key' => 'subject_template',
                'label' => 'Subject',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '260px',
                'alignment' => 'left',
            ],

            'is_active' => [
                'key' => 'is_active',
                'label' => 'Status',
                'type' => 'badge',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Active'],
                    ['value' => '0', 'label' => 'Inactive'],
                ],
            ],

            'modified' => [
                'key' => 'modified',
                'label' => 'Modified',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
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
        ];
    }
}
