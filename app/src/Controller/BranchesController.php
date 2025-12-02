<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Services\CsvExportService;
use Cake\Database\Exception\DatabaseException;
use Cake\Http\Exception\NotFoundException;

/**
 * Manages hierarchical organizational branches with tree structure maintenance.
 *
 * Provides CRUD operations, multi-level hierarchy search with special character handling
 * (th/Þ conversion), and member association. Maintains nested set tree integrity via
 * automatic recovery on initialization.
 *
 * @property \App\Model\Table\BranchesTable $Branches
 */
class BranchesController extends AppController
{
    use DataverseGridTrait;

    /**
     * Configure authorization and tree recovery.
     *
     * Runs tree recovery on first init to ensure nested set integrity (lft/rght values).
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
        $setting = StaticHelpers::getAppSetting('KMP.BranchInitRun', '');
        if (!$setting == 'recovered') {
            $branches = $this->Branches;
            $branches->recover();
            StaticHelpers::setAppSetting(
                'KMP.BranchInitRun',
                'recovered',
            );
        }
    }

    /**
     * Display Dataverse grid for branches.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Provide Dataverse grid data for branches.
     * Branches use a flat grid with a computed "path" column showing hierarchy.
     * The path is computed by walking up the parent chain for each branch.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Build base query with parent for path computation
        $baseQuery = $this->Branches->find()
            ->contain(['Parent']);

        // Use unified trait for grid processing
        // Sort by 'lft' (left value) to maintain hierarchical tree order
        $result = $this->processDataverseGrid([
            'gridKey' => 'Branches.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\BranchesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Branches',
            'defaultSort' => ['Branches.lft' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Post-process data to compute path for each branch
        $branches = $result['data'];
        $this->computeBranchPaths($branches);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'branches');
        }

        // Set view variables
        $this->set([
            'branches' => $branches,
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\BranchesGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        // Override data for grid rendering
        $this->set('data', $branches);

        if ($turboFrame === 'branches-grid-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', 'branches-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', 'branches-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Compute hierarchical path for each branch
     *
     * Walks up the parent chain to build a path like "/Kingdom/Barony/Shire"
     * Uses a cache to avoid repeated lookups for the same ancestors.
     *
     * @param iterable $branches The branches to compute paths for
     * @return void
     */
    protected function computeBranchPaths(iterable $branches): void
    {
        // Build a lookup map of all branches by ID for efficient path computation
        $branchMap = [];
        foreach ($branches as $branch) {
            $branchMap[$branch->id] = $branch;
        }

        // We need to load all parent IDs that aren't in the current page
        $missingParentIds = [];
        foreach ($branches as $branch) {
            if ($branch->parent_id && !isset($branchMap[$branch->parent_id])) {
                $missingParentIds[$branch->parent_id] = true;
            }
        }

        // Load missing parents if any
        if (!empty($missingParentIds)) {
            $parentBranches = $this->Branches->find()
                ->where(['Branches.id IN' => array_keys($missingParentIds)])
                ->contain(['Parent'])
                ->all();

            foreach ($parentBranches as $parent) {
                $branchMap[$parent->id] = $parent;
                // Also check if this parent's parent is missing
                if ($parent->parent_id && !isset($branchMap[$parent->parent_id])) {
                    $missingParentIds[$parent->parent_id] = true;
                }
            }

            // Recursively load grandparents etc. (max 10 levels to prevent infinite loops)
            for ($i = 0; $i < 10; $i++) {
                $newMissing = [];
                foreach ($missingParentIds as $parentId => $v) {
                    if (isset($branchMap[$parentId]) && $branchMap[$parentId]->parent_id) {
                        if (!isset($branchMap[$branchMap[$parentId]->parent_id])) {
                            $newMissing[$branchMap[$parentId]->parent_id] = true;
                        }
                    }
                }

                if (empty($newMissing)) {
                    break;
                }

                $moreBranches = $this->Branches->find()
                    ->where(['Branches.id IN' => array_keys($newMissing)])
                    ->all();

                foreach ($moreBranches as $parent) {
                    $branchMap[$parent->id] = $parent;
                }

                $missingParentIds = array_merge($missingParentIds, $newMissing);
            }
        }

        // Now compute paths for each branch
        foreach ($branches as $branch) {
            $pathParts = [$branch->name];
            $currentId = $branch->parent_id;

            // Walk up the parent chain
            while ($currentId && isset($branchMap[$currentId])) {
                $parent = $branchMap[$currentId];
                array_unshift($pathParts, $parent->name);
                $currentId = $parent->parent_id;
            }

            // Store computed path
            $branch->path = '/' . implode('/', $pathParts);
        }
    }

    /**
     * Display detailed branch information with members and children.
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        $branch = $this->Branches->get(
            $id,
            contain: [
                'Parent',
                'Members' => function ($q) {
                    return $q
                        ->select(['id', 'sca_name', 'branch_id', 'membership_number', 'membership_expires_on', 'status', 'birth_month', 'birth_year'])
                        ->orderBy(['sca_name' => 'ASC']);
                },
            ],
        );
        if (!$branch) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($branch);
        // get the children for the branch
        $branch->children = $this->Branches
            ->find('children', for: $branch->id, direct: true)
            ->toArray();
        $treeList = $this->Branches
            ->find('treeList', spacer: '--')
            ->orderBy(['name' => 'ASC']);

        $btArray = StaticHelpers::getAppSetting('Branches.Types');
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }

        // get a list of required offices and officers for the branch

        $this->set(compact('branch', 'treeList', 'branch_types'));
    }

    /**
     * Create new organizational branch.
     *
     * Handles JSON links parsing and tree structure integration.
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $branch = $this->Branches->newEmptyEntity();
        if ($this->request->is('post')) {
            $branch = $this->Branches->patchEntity(
                $branch,
                $this->request->getData(),
            );
            $links = json_decode($this->request->getData('branch_links'), true);
            $branch->links = $links;
            if ($this->Branches->save($branch)) {
                $this->Flash->success(__('The branch has been saved.'));

                return $this->redirect(['action' => 'view', $branch->id]);
            }
            $this->Flash->error(
                __('The branch could not be saved. Please, try again.'),
            );
        }
        $btArray = StaticHelpers::getAppSetting('Branches.Types');
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }
        $treeList = $this->Branches
            ->find('list')
            ->orderBy(['name' => 'ASC']);
        $this->set(compact('branch', 'treeList', 'branch_types'));
    }

    /**
     * Update existing organizational branch.
     *
     * Handles circular reference prevention, JSON links, and automatic tree recovery.
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        $branch = $this->Branches->get($id);
        if (!$branch) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($branch);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $branch = $this->Branches->patchEntity(
                $branch,
                $this->request->getData(),
            );
            $links = json_decode($this->request->getData('branch_links'), true);
            $branch->links = $links;
            try {
                if ($this->Branches->save($branch)) {
                    $branches = $this->getTableLocator()->get('Branches');
                    $branches->recover();
                    $this->Flash->success(__('The branch has been saved.'));

                    return $this->redirect(['action' => 'view', $branch->id]);
                }
                $this->Flash->error(
                    __('The branch could not be saved. Please, try again.'),
                );

                return $this->redirect(['action' => 'view', $branch->id]);
            } catch (DatabaseException $e) {
                // if the error message starts with 'Cannot use node' then it is a tree error
                if (strpos($e->getMessage(), 'Cannot use node') === 0) {
                    $this->Flash->error(
                        __(
                            'The branch could not be saved, save would have created a circular reference.',
                        ),
                    );
                } else {
                    $this->Flash->error(
                        __(
                            'The branch could not be saved. Please, try again. Error` {0}',
                            $e->getMessage(),
                        ),
                    );
                }

                return $this->redirect(['action' => 'view', $branch->id]);
            }
        }
        $treeList = $this->Branches
            ->find('list')
            ->orderBy(['name' => 'ASC']);
        $this->set(compact('branch', 'treeList'));
        // Mirror MembersController pattern: GET edit displays view template with modal
        if ($this->request->is('get')) {
            // Provide branch_types for edit modal element
            $btArray = StaticHelpers::getAppSetting('Branches.Types');
            $branch_types = [];
            foreach ($btArray as $branchType) {
                $branch_types[$branchType] = $branchType;
            }
            $this->set(compact('branch_types'));
            return $this->render('view');
        }
    }

    /**
     * Delete branch (soft delete with "Deleted:" prefix).
     *
     * Cannot delete branches with children or associated members.
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $branch = $this->Branches->get($id);
        if (!$branch) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($branch);
        $branch->name = 'Deleted: ' . $branch->name;
        if ($this->Branches->delete($branch)) {
            $this->Flash->success(__('The branch has been deleted.'));
        } else {
            $this->Flash->error(
                __('The branch could not be deleted. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'index']);
    }
}
