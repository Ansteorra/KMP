<?php

declare(strict_types=1);

/**
 * Offices Management Controller
 *
 * Provides comprehensive CRUD operations for organizational offices
 * including hierarchy management, deputy relationships, role assignments,
 * and branch type compatibility.
 *
 * @property \Officers\Model\Table\OfficesTable $Offices
 */

namespace Officers\Controller;

use App\KMP\StaticHelpers;
use \Officers\Services\OfficerManagerInterface;
use App\Controller\DataverseGridTrait;
use Officers\KMP\GridColumns\OfficesGridColumns;

class OfficesController extends AppController
{
    use DataverseGridTrait;
    /**
     * Initialize controller with authorization configuration.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Inherit Officers plugin security baseline and component configuration
        parent::initialize();

        // Configure model-level authorization for office operations
        // - "index": Authorizes office listing via OfficesTablePolicy
        // - "add": Authorizes office creation via OfficesTablePolicy
        // - "gridData": Authorizes grid data retrieval via OfficesTablePolicy
        // Entity-level authorization is handled in individual action methods
        $this->Authorization->authorizeModel("index", "add", "gridData");
    }

    /**
     * List all offices with hierarchical relationships.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        // Build comprehensive office query with hierarchical relationships
        $query = $this->Offices->find()
            ->contain([
                // Department categorization and organizational structure
                'Departments' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Role assignments and permission integration
                'GrantsRole' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Deputy relationships for hierarchical support
                'DeputyTo' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Reporting relationships for organizational chains
                'ReportsTo' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Apply pagination with alphabetical ordering for administrative efficiency
        $offices = $this->paginate($query, [
            'order' => [
                'name' => 'asc',  // Alphabetical ordering for user experience
            ]
        ]);

        // Provide offices with hierarchical data to view
        $this->set(compact('offices'));
    }

    /**
     * Recalculate officers for all offices to ensure settings are synchronized.
     *
     * @param OfficerManagerInterface $officerManager Officer manager for recalculation
     * @return \\Cake\\Http\\Response|null Redirects to index
     */
    public function syncOfficers(OfficerManagerInterface $officerManager)
    {
        $this->request->allowMethod(['post']);

        // Authorize administrative sync access
        $this->Authorization->authorize($this->Offices, 'syncOfficers');

        $currentUser = $this->Authentication->getIdentity();

        $offices = $this->Offices->find()
            ->select(['id', 'name'])
            ->orderBy(['name' => 'asc'])
            ->all();

        $updatedTotal = 0;
        $currentTotal = 0;
        $upcomingTotal = 0;
        $updatedOfficeSummaries = [];

        $connection = $this->Offices->getConnection();
        $connection->begin();

        try {
            foreach ($offices as $office) {
                $result = $officerManager->recalculateOfficersForOffice(
                    $office->id,
                    $currentUser->id
                );

                if (!$result->success) {
                    \Cake\Log\Log::error('Office bulk recalculation failed', [
                        'office_id' => $office->id,
                        'office_name' => $office->name,
                        'reason' => $result->reason,
                    ]);

                    $connection->rollback();
                    $this->Flash->error(__('Officer sync failed for {0}: {1}', $office->name, $result->reason));
                    return $this->redirect(['action' => 'index']);
                }

                $updatedCount = (int)($result->data['updated_count'] ?? 0);
                $currentCount = (int)($result->data['current_count'] ?? 0);
                $upcomingCount = (int)($result->data['upcoming_count'] ?? 0);

                $updatedTotal += $updatedCount;
                $currentTotal += $currentCount;
                $upcomingTotal += $upcomingCount;

                if ($updatedCount > 0) {
                    $updatedOfficeSummaries[] = __(
                        '{0}: {1} current, {2} upcoming',
                        $office->name,
                        $currentCount,
                        $upcomingCount
                    );
                }
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
            \Cake\Log\Log::error('Office bulk recalculation failed with exception', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->Flash->error(__('Officer sync failed due to an unexpected error.'));
            return $this->redirect(['action' => 'index']);
        }

        if ($updatedTotal > 0) {
            $summary = __('Updated {0} officers ({1} current, {2} upcoming).', $updatedTotal, $currentTotal, $upcomingTotal);
            $officeSummary = !empty($updatedOfficeSummaries)
                ? __(' Offices updated: {0}.', implode('; ', $updatedOfficeSummaries))
                : '';

            $this->Flash->success($summary . $officeSummary);
        } else {
            $this->Flash->success(__('All officers are already in sync with their offices.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Provide grid data for offices listing via AJAX.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function gridData()
    {
        // Build the base query with hierarchical relationships
        $baseQuery = $this->Offices->find()
            ->contain([
                'Departments' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'GrantsRole' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'DeputyTo' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'ReportsTo' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ]);

        // Get system views from GridColumns
        $systemViews = OfficesGridColumns::getSystemViews([]);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Officers.Offices.index.main',
            'gridColumnsClass' => OfficesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Offices',
            'defaultSort' => ['Offices.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle Response objects (CSV export, JSON, etc.)
        if ($result instanceof \Cake\Http\Response) {
            return $result;
        }

        // Set view variables
        $this->set([
            'offices' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => OfficesGridColumns::getSearchableColumns(),
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

        // Use main app's element templates (not plugin templates)
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'offices-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'offices-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'offices-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display office details with hierarchy and configuration options.
     *
     * @param string|null $id Office ID
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Http\Exception\NotFoundException When office not found
     */
    public function view($id = null)
    {
        // Load office with comprehensive hierarchical relationships
        $office = $this->Offices->get(
            $id,
            contain: [
                // Department categorization with optimized field selection
                'Departments' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Role assignments for permission integration
                'GrantsRole' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Deputy relationships for hierarchical support
                'DeputyTo' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Reporting relationships for organizational chains
                'ReportsTo' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]
        );

        // Validate office exists
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user access to this specific office
        $this->Authorization->authorize($office);

        // Prepare form data for administrative interface

        // Department options for categorization
        $departments = $this->Offices->Departments->find('list')->all();

        // Office hierarchy options (excluding current office to prevent circular references)
        $report_to_offices = $this->Offices->find('list')->where(['id <>' => $office->id])->all();
        $deputy_to_offices = $this->Offices->find('list')->where(['id <>' => $office->id])->all();

        // Role options for permission assignment
        $roles = $this->Offices->GrantsRole->find('list')->all();

        // Dynamic branch type loading from application settings
        $btArray = StaticHelpers::getAppSetting("Branches.Types");
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }

        // Provide comprehensive data to view for display and form preparation
        $this->set(compact('office', 'departments', 'report_to_offices', 'roles', 'branch_types', 'deputy_to_offices'));
    }

    /**
     * Create a new office with hierarchy validation.
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function add()
    {
        // Create new empty office entity for form
        $office = $this->Offices->newEmptyEntity();

        // Authorize office creation permissions
        $this->Authorization->authorize($office);

        // Process form submission
        if ($this->request->is('post')) {
            // Patch entity with submitted form data
            $office = $this->Offices->patchEntity($office, $this->request->getData());

            // Validate mandatory branch type selection
            if (empty($office->branch_types)) {
                $this->Flash->error(__('At least 1 Branch Type must be selected.'));
            } else {
                // Attempt to save with comprehensive error handling
                if ($this->Offices->save($office)) {
                    // Success: Provide feedback and redirect to view
                    $this->Flash->success(__('The office has been saved.'));
                    return $this->redirect(['action' => 'view', $office['id']]);
                }

                // Failure: Provide error feedback (form will redisplay with errors)
                $this->Flash->error(__('The office could not be saved. Please, try again.'));
            }
        }

        // Prepare comprehensive form data for administrative interface

        // Department options for categorization
        $departments = $this->Offices->Departments->find('list')->all();

        // Office hierarchy options for reporting and deputy relationships
        $report_to_offices = $this->Offices->find('list')->all();
        $deputy_to_offices = $this->Offices->find('list')->all();

        // Role options for permission assignment
        $roles = $this->Offices->GrantsRole->find('list')->all();

        // Dynamic branch type loading from application settings
        $btArray = StaticHelpers::getAppSetting("Branches.Types");
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }

        // Provide comprehensive data to form (new entity or with validation errors)
        $this->set(compact('office', 'departments', 'report_to_offices', 'roles', 'branch_types', 'deputy_to_offices'));
    }

    /**
     * Edit an existing office with hierarchy validation.
     *
     * @param string|null $id Office ID
     * @param OfficerManagerInterface $officerManager Officer manager for recalculation
     * @return \Cake\Http\Response|null|void Redirects to view
     * @throws \Cake\Http\Exception\NotFoundException When office not found
     */
    public function edit($id = null, OfficerManagerInterface $officerManager)
    {
        // Load office with minimal associations for edit performance
        $office = $this->Offices->get($id, contain: []);

        // Validate office exists
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user can modify this specific office
        $this->Authorization->authorize($office);

        // Process form submission
        if ($this->request->is(['patch', 'post', 'put'])) {
            // Extract submitted data
            $postData = $this->request->getData();

            // Patch entity with submitted form data
            $office = $this->Offices->patchEntity($office, $postData);

            // Validate mandatory branch type selection
            if (empty($office->branch_types)) {
                $this->Flash->error(__('At least 1 Branch Type must be selected.'));
            } else {
                // Determine if we need to recalculate officers after checking dirty state
                $needsRecalculation = $office->isDirty('deputy_to_id') ||
                    $office->isDirty('reports_to_id') ||
                    $office->isDirty('grants_role_id');

                // Start transaction for atomic office save + officer recalculation
                $this->Offices->getConnection()->begin();

                try {
                    // Attempt to save office
                    if (!$this->Offices->save($office)) {
                        // Failure: Rollback and provide error feedback
                        $this->Offices->getConnection()->rollback();
                        $this->Flash->error(__('The office could not be saved. Please, try again.'));
                    } else {
                        // Office saved successfully
                        $recalcResult = null;

                        if ($needsRecalculation) {
                            // Recalculate officers for the office configuration change
                            $currentUser = $this->Authentication->getIdentity();
                            $recalcResult = $officerManager->recalculateOfficersForOffice(
                                $office->id,
                                $currentUser->id
                            );

                            if (!$recalcResult->success) {
                                // Recalculation failed: Rollback everything
                                $this->Offices->getConnection()->rollback();
                                $this->Flash->error(__('The office could not be saved: {0}', $recalcResult->reason));
                                return $this->redirect(['action' => 'view', $office['id']]);
                            }
                        }

                        // Commit transaction - everything succeeded
                        $this->Offices->getConnection()->commit();

                        // Log successful recalculation if it occurred
                        if ($needsRecalculation && $recalcResult) {
                            \Cake\Log\Log::info('Office configuration changed - officers recalculated', [
                                'office_id' => $office->id,
                                'office_name' => $office->name,
                                'updated_count' => $recalcResult->data['updated_count'],
                                'current_count' => $recalcResult->data['current_count'],
                                'upcoming_count' => $recalcResult->data['upcoming_count'],
                            ]);
                        }

                        // Success: Provide feedback and redirect to view
                        if ($needsRecalculation && $recalcResult) {
                            // Build message showing which officers were updated
                            $currentCount = $recalcResult->data['current_count'];
                            $upcomingCount = $recalcResult->data['upcoming_count'];
                            $messageParts = [];

                            if ($currentCount > 0) {
                                $messageParts[] = __('{0} current officer(s)', $currentCount);
                            }
                            if ($upcomingCount > 0) {
                                $messageParts[] = __('{0} upcoming officer(s)', $upcomingCount);
                            }

                            if (!empty($messageParts)) {
                                $officerMessage = implode(' and ', $messageParts);
                                $this->Flash->success(__('The office has been saved. {0} have been updated.', $officerMessage));
                            } else {
                                $this->Flash->success(__('The office has been saved.'));
                            }
                        } else {
                            $this->Flash->success(__('The office has been saved.'));
                        }

                        return $this->redirect(['action' => 'view', $office['id']]);
                    }
                } catch (\Exception $e) {
                    // Unexpected error: Rollback and log
                    $this->Offices->getConnection()->rollback();
                    \Cake\Log\Log::error('Office save/recalculation failed with exception', [
                        'office_id' => $id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->Flash->error(__('An unexpected error occurred. Please try again.'));
                }
            }
        }

        // Always redirect to view for consistent navigation
        // Success: Shows updated office with success message
        // Validation Failure: Shows current office with error message
        return $this->redirect(['action' => 'view', $office['id']]);
    }

    /**
     * Delete an office (soft delete).
     *
     * @param string|null $id Office ID
     * @return \Cake\Http\Response|null Redirects to index
     * @throws \Cake\Http\Exception\NotFoundException When office not found
     */
    public function delete($id = null)
    {
        // Restrict to secure HTTP methods for CSRF protection
        $this->request->allowMethod(['post', 'delete']);

        // Load office for deletion
        $office = $this->Offices->get($id);

        // Validate office exists
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user can delete this specific office
        $this->Authorization->authorize($office);

        // Implement soft deletion pattern
        // Mark office as deleted while preserving organizational integrity
        $office->name = "Deleted: " . $office->name;

        // Attempt deletion with comprehensive error handling
        if ($this->Offices->delete($office)) {
            $this->Flash->success(__('The office has been deleted.'));
        } else {
            $this->Flash->error(__('The office could not be deleted. Please, try again.'));
        }

        // Redirect to index for continued administration
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX endpoint for discovering offices available for a specific branch.
     *
     * @param string|null $id Branch ID
     * @return \Cake\Http\Response JSON response with filtered offices
     * @throws \Cake\Http\Exception\NotFoundException When branch not found
     */
    public function availableOfficesForBranch($id = null)
    {
        // Load branch with minimal field selection for performance
        $branch = $this->getTableLocator()->get("Branches")
            ->find()->select(['id', 'parent_id'])
            ->where(['id' => $id])->first();

        // Validate branch exists
        if (!$branch) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize branch access
        $this->Authorization->authorize($branch);

        // Build office query with deputy relationships and branch type filtering
        $officesTbl = $this->Offices;
        $officeQuery = $officesTbl->find("all")
            ->contain([
                // Include deputy relationships for hierarchical context
                "Deputies" => function ($q) {
                    return $q->select(["id", "name", "deputy_to_id"]);
                }
            ])
            ->select(["id", "name", "deputy_to_id"])
            ->orderBY(["name" => "ASC"]);

        // Filter offices by branch type compatibility
        // Uses JSON-based matching for flexible branch type configuration
        $officeQuery = $officeQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%']);

        // Execute query and convert to array for JSON response
        $offices = $officeQuery->toArray();

        // Configure AJAX view for JSON response
        $this->viewBuilder()->setClassName("Ajax");

        // Set JSON response with office data
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($offices));

        return $this->response;
    }
}