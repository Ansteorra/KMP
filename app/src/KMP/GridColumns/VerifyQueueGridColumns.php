<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Verification Queue Grid Column Metadata
 *
 * Defines column configuration for the Member Verification Queue Dataverse grid.
 * Supports viewing members pending verification: youth, members with card uploads,
 * and unverified members without cards.
 */
class VerifyQueueGridColumns extends BaseGridColumns
{
    public static function getColumns(): array
    {
        return [
            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'unverified minor', 'label' => 'Unverified Minor'],
                    ['value' => '< 18 member verified', 'label' => 'Minor Membership Verified'],
                    ['value' => '< 18 parent verified', 'label' => 'Minor Parent Verified'],
                ],
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
                'clickActionPermission' => 'view',
            ],

            'branch_id' => [
                'key' => 'branch_id',
                'label' => 'Branch',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'Branches',
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
                'renderField' => 'branch.name',
                'queryField' => 'Branches.name',
            ],

            'first_name' => [
                'key' => 'first_name',
                'label' => 'First Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
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
                'defaultVisible' => true,
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

            'has_membership_card' => [
                'key' => 'has_membership_card',
                'label' => 'Card',
                'type' => 'boolean',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'is-populated',
                'filterQueryField' => 'membership_card_path',
                'filterOptions' => [
                    ['value' => 'yes', 'label' => 'Has Card'],
                    ['value' => 'no', 'label' => 'No Card'],
                ],
                'defaultVisible' => true,
                'width' => '80px',
                'alignment' => 'center',
                // Custom rendering handled by cellRenderer
                'cellRenderer' => function ($value, $row, $view) {
                    $cardPath = $row['membership_card_path'] ?? null;
                    if ($cardPath && strlen($cardPath) > 0) {
                        return '<i class="bi bi-card-heading" title="Membership card uploaded"></i>';
                    }
                    return '';
                },
            ],

            'birth_year' => [
                'key' => 'birth_year',
                'label' => 'Birth Year',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '100px',
                'alignment' => 'right',
            ],

            'birth_month' => [
                'key' => 'birth_month',
                'label' => 'Birth Month',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '100px',
                'alignment' => 'right',
            ],
        ];
    }

    /**
     * Get system views for verify queue grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [
            'sys-verify-youth' => [
                'id' => 'sys-verify-youth',
                'name' => __('Youth'),
                'description' => __('Minors requiring verification'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        [
                            'field' => 'status',
                            'operator' => 'in',
                            'value' => ['unverified minor', '< 18 member verified'],
                        ],
                    ],
                ],
            ],
            'sys-verify-with-card' => [
                'id' => 'sys-verify-with-card',
                'name' => __('Card Uploaded'),
                'description' => __('Members who uploaded membership card'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        [
                            'field' => 'has_membership_card',
                            'operator' => 'is-populated',
                            'value' => 'yes',
                        ],
                    ],
                ],
            ],
            'sys-verify-without-card' => [
                'id' => 'sys-verify-without-card',
                'name' => __('Without Card'),
                'description' => __('Unverified members without membership card'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        [
                            'field' => 'status',
                            'operator' => 'in',
                            'value' => ['active'],
                        ],
                        [
                            'field' => 'has_membership_card',
                            'operator' => 'is-populated',
                            'value' => 'no',
                        ],
                    ],
                ],
            ],
        ];
    }
}
