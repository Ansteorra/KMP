<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Members Grid Column Metadata
 *
 * Defines all available columns for the Members data grid with comprehensive metadata
 * for rendering, sorting, filtering, and user customization. This configuration drives
 * the Dataverse-style grid system for the Members index.
 *
 * ## Column Metadata Structure
 *
 * Each column definition includes:
 * - **key**: Unique identifier and field name
 * - **label**: Display name for column header
 * - **type**: Data type (string, number, date, boolean, badge, etc.)
 * - **sortable**: Whether column can be sorted
 * - **filterable**: Whether column can be filtered
 * - **defaultVisible**: Shown by default or hidden
 * - **required**: Column cannot be hidden (always visible)
 * - **width**: Optional width hint (px or %)
 * - **alignment**: left, center, right
 * - **cellRenderer**: Optional custom cell rendering callable function
 * - **clickAction**: Optional action when cell is clicked
 * - **searchable**: Whether column should be included in text search
 *
 * ## Click Actions
 *
 * The `clickAction` property supports the following formats:
 * - **navigate:<url>** - Navigate to URL (e.g., "navigate:/members/view/:id")
 * - **toggleSubRow:<type>** - Toggle expandable sub-row (e.g., "toggleSubRow:warrantreasons")
 * - **openModal:<type>** - Open modal dialog (e.g., "openModal:authorizations")
 * - **link:<url>** - Simple link without Turbo frame breaking
 * - **callable** - Custom function that receives ($content, $row, $view)
 *
 * URL placeholders like `:id`, `:field_name` are replaced with row values.
 *
 * ### ToggleSubRow Action Details
 *
 * The toggleSubRow action creates an expandable sub-row beneath the main row.
 * When clicked, it:
 * 1. Makes an AJAX request to `/controller/sub-row/{id}/{type}`
 * 2. Inserts returned HTML as a new <tr> beneath the main row
 * 3. Adds visual indicators (chevron icon rotates, row highlight)
 * 4. Clicking again collapses and removes the sub-row
 *
 * **Requirements:**
 * - Controller must have a `subRow($id, $type)` action
 * - Template must exist at `/templates/element/sub_rows/{type}.php`
 * - Route must be configured for the endpoint
 *
 * **Example Implementation:**
 * ```php
 * // In GridColumns class
 * 'warrantable' => [
 *     'key' => 'warrantable',
 *     'label' => 'Warrantable',
 *     'type' => 'boolean',
 *     'clickAction' => 'toggleSubRow:warrantreasons',
 *     // ... other properties
 * ],
 *
 * // In Controller
 * public function subRow(?string $id = null, ?string $type = null) {
 *     $this->viewBuilder()->setLayout(false);
 *     $member = $this->Members->get($id);
 *     
 *     switch ($type) {
 *         case 'warrantreasons':
 *             $this->set('reasons', $member->non_warrantable_reasons);
 *             $this->render('/element/sub_rows/warrant_reasons');
 *             break;
 *     }
 * }
 *
 *
 **/
class MembersGridColumns extends BaseGridColumns
{

    public static function getColumns(): array
    {
        $columns = [
            'id' => [
                'key' => 'id',
                'label' => 'ID',
                'type' => 'number',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '80px',
                'alignment' => 'right',
            ],

            'sca_name' => [
                'key' => 'sca_name',
                'label' => 'SCA Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/members/view/:id',
            ],

            'membership_number' => [
                'key' => 'membership_number',
                'label' => 'Membership #',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '140px',
                'alignment' => 'left',
            ],

            'first_name' => [
                'key' => 'first_name',
                'label' => 'First Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'last_name' => [
                'key' => 'last_name',
                'label' => 'Last Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'email_address' => [
                'key' => 'email_address',
                'label' => 'Email',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
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
                'width' => '120px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'verified', 'label' => 'Verified'],
                    ['value' => 'deactivated', 'label' => 'Deactivated'],
                    ['value' => 'unverified minor', 'label' => 'Unverified Minor'],
                    ['value' => '< 18 member verified', 'label' => 'Minor Verified'],
                    ['value' => '< 18 parent verified', 'label' => 'Minor Parent Verified'],
                    ['value' => 'verified < 18', 'label' => 'Verified Minor'],
                ],
            ],

            'branch_id' => [
                'key' => 'branch_id',
                'label' => 'Branch',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'branches',
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
                'renderField' => 'branch.name',
                'queryField' => 'Branches.name',
            ],

            'phone' => [
                'key' => 'phone',
                'label' => 'Phone',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '140px',
                'alignment' => 'left',
            ],

            'is_minor' => [
                'key' => 'is_minor',
                'label' => 'Minor',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => false,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'width' => '80px',
                'alignment' => 'center',
            ],

            'parent_id' => [
                'key' => 'parent_id',
                'label' => 'Parent',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
                'renderField' => 'parent.sca_name',
                'queryField' => 'Parents.sca_name',
            ],

            'address' => [
                'key' => 'address',
                'label' => 'Address',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '200px',
                'alignment' => 'left',
            ],

            'city' => [
                'key' => 'city',
                'label' => 'City',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '130px',
                'alignment' => 'left',
            ],

            'state_province' => [
                'key' => 'state_province',
                'label' => 'State',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '80px',
                'alignment' => 'left',
            ],

            'zip_postal_code' => [
                'key' => 'zip_postal_code',
                'label' => 'Zip',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '100px',
                'alignment' => 'left',
            ],

            'country' => [
                'key' => 'country',
                'label' => 'Country',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Created',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'modified' => [
                'key' => 'modified',
                'label' => 'Modified',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'last_login' => [
                'key' => 'last_login',
                'label' => 'Last Login',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'warrantable' => [
                'key' => 'warrantable',
                'label' => 'Warrantable',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'width' => '100px',
                'alignment' => 'right',
                'clickAction' => 'toggleSubRow:warrantreasons',
                'subRowTemplate' => 'warrantsSubRow',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
            ],
        ];

        // Add actions column as the last column
        $columns['actions'] = [
            'key' => 'actions',
            'label' => '',
            'type' => 'actions',
            'required' => true,
            'defaultVisible' => true,
            'sortable' => false,
            'exportable' => false,
            'width' => '160px',
            'alignment' => 'right',
            'cellRenderer' => function ($value, $row, $view) {
                $currentMember = $view->get('currentMember');
                $buttons = [];
                // Edit button
                if ($currentMember && $currentMember->can('edit', $row)) {
                    $buttons[] = $view->Html->link('<i class="bi bi-pencil"></i> Edit', [
                        'controller' => 'Members',
                        'action' => 'edit',
                        $row['id']
                    ], [
                        'escape' => false,
                        'class' => 'btn btn-sm btn-outline-primary me-1',
                        'data-turbo-frame' => '_top',
                    ]);
                }
                // Authorize button (example)
                if ($currentMember && $currentMember->can('authorize', $row)) {
                    $buttons[] = $view->Html->link('<i class="bi bi-shield-check"></i> Authorize', [
                        'controller' => 'Authorizations',
                        'action' => 'add',
                        'member_id' => $row['id']
                    ], [
                        'escape' => false,
                        'class' => 'btn btn-sm btn-outline-success me-1',
                        'data-turbo-frame' => '_top',
                    ]);
                }
                // Delete button
                if ($currentMember && $currentMember->can('delete', $row)) {
                    $buttons[] = $view->Form->postLink('<i class="bi bi-trash"></i> Delete', [
                        'controller' => 'Members',
                        'action' => 'delete',
                        $row['id']
                    ], [
                        'escape' => false,
                        'class' => 'btn btn-sm btn-outline-danger',
                        'confirm' => 'Are you sure you want to delete this member?',
                        'data-turbo-frame' => '_top',
                    ]);
                }
                return implode(' ', $buttons);
            },
        ];

        return $columns;
    }
}
