<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Email Templates Grid Column Metadata
 *
 * Defines all available columns for the Email Templates data grid.
 * Includes mailer class, action method, subject, and status information.
 */
class EmailTemplatesGridColumns extends BaseGridColumns
{
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

            'mailer_class' => [
                'key' => 'mailer_class',
                'label' => 'Mailer Class',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/email-templates/edit/:id',
            ],

            'action_method' => [
                'key' => 'action_method',
                'label' => 'Action Method',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
            ],

            'subject' => [
                'key' => 'subject',
                'label' => 'Subject',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '300px',
                'alignment' => 'left',
            ],

            'has_text_template' => [
                'key' => 'has_text_template',
                'label' => 'Text',
                'type' => 'boolean',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '80px',
                'alignment' => 'center',
                'exportable' => false,
            ],

            'has_html_template' => [
                'key' => 'has_html_template',
                'label' => 'HTML',
                'type' => 'boolean',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '80px',
                'alignment' => 'center',
                'exportable' => false,
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
