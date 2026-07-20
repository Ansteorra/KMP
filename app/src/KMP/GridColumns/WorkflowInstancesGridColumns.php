<?php
declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\KMP\TimezoneHelper;
use App\Model\Entity\WorkflowInstance;
use DateTime;
use DateTimeZone;

/**
 * Workflow Instances Grid Column Metadata
 *
 * Defines sortable/filterable columns for workflow instance monitoring.
 */
class WorkflowInstancesGridColumns extends BaseGridColumns
{
    /**
     * Row actions for the workflow instances grid.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [
            'view' => [
                'key' => 'view',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-eye',
                'class' => 'btn btn-sm btn-secondary',
                'title' => 'View instance',
                'ariaLabel' => 'View workflow instance {{id}}',
                'url' => [
                    'controller' => 'WorkflowInstances',
                    'action' => 'viewInstance',
                    'idField' => 'id',
                ],
            ],
        ];
    }

    /**
     * Get workflow instance grid columns.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'id' => [
                'key' => 'id',
                'label' => 'Instance ID',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '110px',
                'alignment' => 'right',
                'clickAction' => 'navigate:/workflows/instance/:id',
            ],

            'workflow_name' => [
                'key' => 'workflow_name',
                'label' => 'Workflow',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '240px',
                'alignment' => 'left',
                'queryField' => 'WorkflowDefinitions.name',
                'filterOptionsSource' => [
                    'table' => 'WorkflowDefinitions',
                    'valueField' => 'name',
                    'labelField' => 'name',
                    'order' => ['name' => 'ASC'],
                ],
                'cellRenderer' => function ($value, $row, $view) {
                    $workflowName = $value;
                    $associatedWorkflowName = $row->workflow_definition?->name ?? null;

                    if (($workflowName === null || $workflowName === '') && $associatedWorkflowName !== null) {
                        $workflowName = $associatedWorkflowName;
                    }

                    if ($workflowName === null || $workflowName === '') {
                        return '<span class="text-muted">—</span>';
                    }

                    return h((string)$workflowName);
                },
                'clickAction' => 'navigate:/workflows/instance/:id',
            ],

            'version_number' => [
                'key' => 'version_number',
                'label' => 'Version',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '95px',
                'alignment' => 'right',
                'queryField' => 'WorkflowVersions.version_number',
                'cellRenderer' => function ($value, $row, $view) {
                    $versionNumber = $value;
                    $associatedVersionNumber = $row->workflow_version?->version_number ?? null;

                    if (($versionNumber === null || $versionNumber === '') && $associatedVersionNumber !== null) {
                        $versionNumber = $associatedVersionNumber;
                    }

                    if ($versionNumber === null || $versionNumber === '') {
                        return '<span class="text-muted">—</span>';
                    }

                    return 'v' . h((string)$versionNumber);
                },
            ],

            'entity_type' => [
                'key' => 'entity_type',
                'label' => 'Entity Type',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '170px',
                'alignment' => 'left',
                'queryField' => 'WorkflowInstances.entity_type',
                'filterOptionsSource' => [
                    'table' => 'WorkflowInstances',
                    'valueField' => 'entity_type',
                    'labelField' => 'entity_type',
                    'conditions' => [
                        'WorkflowInstances.entity_type IS NOT' => null,
                        'WorkflowInstances.entity_type !=' => '',
                    ],
                    'order' => ['entity_type' => 'ASC'],
                ],
            ],

            'entity_id' => [
                'key' => 'entity_id',
                'label' => 'Entity ID',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'right',
                'queryField' => 'WorkflowInstances.entity_id',
            ],

            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '130px',
                'alignment' => 'center',
                'queryField' => 'WorkflowInstances.status',
                'filterOptions' => [
                    ['value' => WorkflowInstance::STATUS_PENDING, 'label' => 'Pending'],
                    ['value' => WorkflowInstance::STATUS_RUNNING, 'label' => 'Running'],
                    ['value' => WorkflowInstance::STATUS_WAITING, 'label' => 'Waiting'],
                    ['value' => WorkflowInstance::STATUS_COMPLETED, 'label' => 'Completed'],
                    ['value' => WorkflowInstance::STATUS_FAILED, 'label' => 'Failed'],
                    ['value' => WorkflowInstance::STATUS_CANCELLED, 'label' => 'Cancelled'],
                ],
                'cellRenderer' => function ($value, $row, $view) {
                    if ($value === null || $value === '') {
                        return '<span class="text-muted">—</span>';
                    }

                    return $view->KMP->workflowStatusBadge((string)$value);
                },
            ],

            'started_at' => [
                'key' => 'started_at',
                'label' => 'Started',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'required' => true,
                'width' => '180px',
                'alignment' => 'left',
            ],

            'completed_at' => [
                'key' => 'completed_at',
                'label' => 'Completed',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * Default system view for recent workflow instances.
     *
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $timezone = TimezoneHelper::getAppTimezone() ?? 'UTC';
        $today = new DateTime('today', new DateTimeZone($timezone));
        $recentStart = (clone $today)->modify('-30 days')->format('Y-m-d');

        return [
            'sys-workflow-instances-recent' => [
                'id' => 'sys-workflow-instances-recent',
                'name' => __('Started Recently'),
                'description' => __('Workflow instances started in the last 30 days'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'started_at', 'operator' => 'gte', 'value' => $recentStart],
                    ],
                ],
            ],
        ];
    }
}
