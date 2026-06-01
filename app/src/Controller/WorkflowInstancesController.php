<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\WorkflowInstancesGridColumns;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Cake\Controller\ComponentRegistry;
use Cake\Http\ServerRequest;

/**
 * WorkflowInstances Controller
 *
 * Manages workflow instance monitoring and viewing.
 *
 * @property \App\Model\Table\WorkflowInstancesTable $WorkflowInstances
 */
class WorkflowInstancesController extends AppController
{
    use DataverseGridTrait;

    protected ?string $defaultTable = 'WorkflowInstances';

    private WorkflowEngineInterface $engine;

    public function __construct(
        ServerRequest $request,
        WorkflowEngineInterface $engine,
        ?ComponentRegistry $components = null,
    ) {
        parent::__construct($request, null, null, $components);
        $this->engine = $engine;
    }

    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'instances',
            'gridData',
            'viewInstance',
        );
    }

    /**
     * List workflow instances, optionally filtered by definition.
     *
     * @param int|null $definitionId Filter by workflow definition
     * @return \Cake\Http\Response|null|void
     */
    public function instances(?int $definitionId = null)
    {
        $workflowDefinition = null;
        if ($definitionId !== null) {
            $workflowDefinition = $this->fetchTable('WorkflowDefinitions')
                ->find()
                ->select(['id', 'name'])
                ->where(['WorkflowDefinitions.id' => $definitionId])
                ->first();
        }

        $this->set(compact('definitionId', 'workflowDefinition'));
    }

    /**
     * Grid data endpoint for workflow instances Dataverse grid.
     *
     * @param int|null $definitionId Optional workflow definition filter
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(?int $definitionId = null)
    {
        $gridKey = $definitionId !== null
            ? "Workflows.instances.definition.{$definitionId}"
            : 'Workflows.instances.main';
        $systemViews = WorkflowInstancesGridColumns::getSystemViews();
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => $gridKey,
            'gridColumnsClass' => WorkflowInstancesGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-workflow-instances-recent',
            'defaultSort' => [
                'WorkflowInstances.started_at' => 'DESC',
                'WorkflowInstances.id' => 'DESC',
            ],
        ]);
        $contain = [];
        if ($queryContext->loadsColumn('workflow_name')) {
            $contain[] = 'WorkflowDefinitions';
        }
        if ($queryContext->loadsColumn('version_number')) {
            $contain[] = 'WorkflowVersions';
        }

        $baseQuery = $this->fetchTable('WorkflowInstances')->find();
        if ($contain !== []) {
            $baseQuery->contain($contain);
        }

        if ($definitionId !== null) {
            $baseQuery->where(['WorkflowInstances.workflow_definition_id' => $definitionId]);
        }

        $result = $this->processDataverseGrid([
            'gridKey' => $gridKey,
            'gridColumnsClass' => WorkflowInstancesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'WorkflowInstances',
            'defaultSort' => [
                'WorkflowInstances.started_at' => 'DESC',
                'WorkflowInstances.id' => 'DESC',
            ],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-workflow-instances-recent',
            'showAllTab' => true,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'showFilterPills' => true,
        ]);

        $rowActions = WorkflowInstancesGridColumns::getRowActions();
        $this->set([
            'data' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => WorkflowInstancesGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'rowActions' => $rowActions,
        ]);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'workflow-instances-grid-table') {
            $this->set('tableFrameId', 'workflow-instances-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');

            return;
        }

        $this->set('frameId', 'workflow-instances-grid');
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('../element/dv_grid_content');
    }

    /**
     * View a single workflow instance with execution log and approvals.
     *
     * @param int $id Instance ID
     * @return \Cake\Http\Response|null|void
     */
    public function viewInstance(int $id)
    {
        $instance = $this->fetchTable('WorkflowInstances')->get($id, contain: [
            'WorkflowDefinitions',
            'WorkflowVersions',
            'WorkflowExecutionLogs' => ['sort' => ['WorkflowExecutionLogs.created' => 'ASC']],
            'WorkflowApprovals' => ['WorkflowApprovalResponses' => ['Members']],
        ]);
        $this->set(compact('instance'));
    }
}
