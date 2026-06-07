<?php
declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use Awards\Model\Entity\Bestowal;

/**
 * Grid column metadata for award bestowal Dataverse-style grids.
 *
 * Used on the bestowals index and gathering-scoped bestowal tabs.
 */
class BestowalsGridColumns extends BaseGridColumns
{
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
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Bestowals',
                    'action' => 'view',
                    'idField' => 'id',
                ],
            ],
            'edit' => [
                'key' => 'edit',
                'type' => 'modal',
                'label' => '',
                'icon' => 'bi-pencil-fill',
                'class' => 'btn-sm btn btn-primary edit-bestowal',
                'modalTarget' => '#editBestowalModal',
                'permission' => 'edit',
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                    ],
                ],
            ],
        ];
    }

    /**
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
                'width' => '60px',
                'alignment' => 'right',
            ],
            'member_sca_name' => [
                'key' => 'member_sca_name',
                'label' => __('Member'),
                'type' => 'string',
                'sortable' => true,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'member.sca_name',
                'queryField' => 'Members.sca_name',
                'clickAction' => 'navigate:/members/view/:member_id',
                'clickActionPermission' => static function ($row, $identity): bool {
                    $memberId = is_array($row) ? ($row['member_id'] ?? null) : ($row->member_id ?? null);
                    $memberId = is_numeric($memberId) ? (int)$memberId : null;

                    return $memberId !== null
                        && $memberId > 0
                        && $identity !== null
                        && method_exists($identity, 'checkCan')
                        && $identity->checkCan('view', 'Members');
                },
            ],
            'awards' => [
                'key' => 'awards',
                'label' => __('Award'),
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'description' => __('Award selected for this bestowal'),
            ],
            'status' => [
                'key' => 'status',
                'label' => __('Status'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
            ],
            'state' => [
                'key' => 'state',
                'label' => __('State'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
            ],
            'court_slot' => [
                'key' => 'court_slot',
                'label' => __('Court Slot'),
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '160px',
                'alignment' => 'left',
                'description' => __('Event Schedule court session linked for heralds'),
            ],
            'stack_rank' => [
                'key' => 'stack_rank',
                'label' => __('Stack Rank'),
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '90px',
                'alignment' => 'center',
                'description' => __('Reserved for future court stack ordering'),
            ],
            'herald_notes_preview' => [
                'key' => 'herald_notes_preview',
                'label' => __('Herald Notes'),
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '220px',
                'alignment' => 'left',
                'queryField' => 'Bestowals.herald_notes',
                'description' => __('Preview of herald notes'),
            ],
            'gathering_name' => [
                'key' => 'gathering_name',
                'label' => __('Gathering'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'gathering.name',
                'queryField' => 'Gatherings.name',
            ],
            'source' => [
                'key' => 'source',
                'label' => __('Source'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '110px',
                'alignment' => 'left',
            ],
            'created' => [
                'key' => 'created',
                'label' => __('Created'),
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => false,
                'width' => '110px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $context = $options['context'] ?? 'index';
        $gatheringColumns = [
            'member_sca_name',
            'awards',
            'state',
            'court_slot',
            'herald_notes_preview',
        ];

        if ($context === 'gatheringBestowals') {
            return [
                'sys-bestowals-gathering' => [
                    'id' => 'sys-bestowals-gathering',
                    'name' => __('Gathering Bestowals'),
                    'description' => __('Bestowals scheduled for this gathering'),
                    'canManage' => false,
                    'config' => [
                        'filters' => [],
                        'columns' => $gatheringColumns,
                    ],
                ],
            ];
        }

        $views = [
            'sys-bestowals-active' => [
                'id' => 'sys-bestowals-active',
                'name' => __('Active Bestowals'),
                'description' => __(
                    'Bestowals still moving through planning, preparation, scheduling, or court readiness',
                ),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'in', 'value' => self::activeStatusNames()],
                    ],
                    'columns' => array_merge($gatheringColumns, ['gathering_name', 'source']),
                ],
            ],
            'sys-bestowals-all' => [
                'id' => 'sys-bestowals-all',
                'name' => __('All / Audit'),
                'description' => __('All bestowals, including archival state and status data'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => array_merge($gatheringColumns, ['status', 'gathering_name', 'source']),
                ],
            ],
        ];

        foreach (Bestowal::getStatuses() as $statusName => $states) {
            $key = 'sys-bestowals-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $statusName));
            $views[$key] = [
                'id' => $key,
                'name' => __($statusName),
                'description' => __('Bestowals in the {0} workflow queue', $statusName),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => $statusName],
                    ],
                    'columns' => array_merge($gatheringColumns, ['status']),
                ],
            ];
        }

        $views['sys-bestowals-completed'] = [
            'id' => 'sys-bestowals-completed',
            'name' => __('Completed'),
            'description' => __('Bestowals marked as given'),
            'canManage' => false,
            'config' => [
                'filters' => [
                    ['field' => 'state', 'operator' => 'in', 'value' => self::configuredStates(['Given'])],
                ],
                'columns' => array_merge($gatheringColumns, ['status', 'gathering_name', 'source', 'created']),
            ],
        ];

        $views['sys-bestowals-cancelled'] = [
            'id' => 'sys-bestowals-cancelled',
            'name' => __('Cancelled / Not Given'),
            'description' => __('Bestowals that were cancelled or announced as not given'),
            'canManage' => false,
            'config' => [
                'filters' => [
                    ['field' => 'state', 'operator' => 'in', 'value' => self::configuredStates([
                        'Cancelled',
                        'Announced Not Given',
                    ])],
                ],
                'columns' => array_merge($gatheringColumns, ['status', 'gathering_name', 'source', 'created']),
            ],
        ];

        return $views;
    }

    /**
     * @return array<int, string>
     */
    private static function activeStatusNames(): array
    {
        return array_values(array_filter(
            array_keys(Bestowal::getStatuses()),
            static fn(string $status): bool => $status !== 'Closed',
        ));
    }

    /**
     * @param array<int, string> $preferredStates
     * @return array<int, string>
     */
    private static function configuredStates(array $preferredStates): array
    {
        $availableStates = array_flip(Bestowal::getStates());
        $states = [];
        foreach ($preferredStates as $state) {
            if (isset($availableStates[$state])) {
                $states[] = $state;
            }
        }

        return $states !== [] ? $states : $preferredStates;
    }

    /**
     * @param bool $canViewHidden Whether hidden states should be included
     * @return array<string, string>
     */
    public static function getStateFilterOptions(bool $canViewHidden = false): array
    {
        $states = Bestowal::getStates();
        if (!$canViewHidden) {
            $hidden = Bestowal::getHiddenStates();
            $states = array_values(array_diff($states, $hidden));
        }

        $options = [];
        foreach ($states as $state) {
            $options[$state] = $state;
        }

        return $options;
    }
}
