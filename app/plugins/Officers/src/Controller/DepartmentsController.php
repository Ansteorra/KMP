<?php

declare(strict_types=1);

namespace Officers\Controller;

use App\Controller\DataverseGridTrait;

/**
 * Departments Controller
 *
 * Manages department CRUD operations and organizational structure.
 *
 * @property \Officers\Model\Table\DepartmentsTable $Departments
 */

class DepartmentsController extends AppController
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

        // Configure model-level authorization for department operations
        // - "index": Authorizes department listing via DepartmentsTablePolicy
        // - "add": Authorizes department creation via DepartmentsTablePolicy
        // Entity-level authorization is handled in individual action methods
        $this->Authorization->authorizeModel("index", "add", "gridData");
    }

    /**
     * List all departments.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Provide grid data for departments listing via Turbo Frame.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(\App\Services\CsvExportService $csvExportService)
    {
        // Build base query
        $baseQuery = $this->Departments->find();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Officers.Departments.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\DepartmentsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Departments',
            'defaultSort' => ['Departments.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'departments');
        }

        // Set view variables
        $this->set([
            'departments' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\DepartmentsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'departments-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'departments-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'departments-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display department details with associated offices.
     *
     * @param string|null $id Department ID
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Http\Exception\NotFoundException When department not found
     */
    public function view($id = null)
    {
        // Load department with comprehensive associations for full context
        $department = $this->Departments->get($id, contain: [
            'Offices',                  // Associated offices in department
            'Offices.GrantsRole',       // Role assignments for offices
            'Offices.DeputyTo'          // Deputy relationships and reporting
        ]);

        // Authorize user access to this specific department
        $this->Authorization->authorize($department);

        // Validate department exists (additional safety check)
        if (!$department) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Provide department data to view
        $this->set(compact('department'));
    }

    /**
     * Create a new department.
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function add()
    {
        // Create new empty department entity for form
        $department = $this->Departments->newEmptyEntity();

        // Authorize department creation permissions
        $this->Authorization->authorize($department);

        // Process form submission
        if ($this->request->is('post')) {
            // Patch entity with submitted form data
            $department = $this->Departments->patchEntity($department, $this->request->getData());

            // Attempt to save with comprehensive error handling
            if ($this->Departments->save($department)) {
                // Success: Provide feedback and redirect to view
                $this->Flash->success(__('The department has been saved.'));
                return $this->redirect(['action' => 'view', $department->id]);
            }

            // Failure: Provide error feedback (form will redisplay with errors)
            $this->Flash->error(__('The department could not be saved. Please, try again.'));
        }

        // Provide department entity to form (new or with validation errors)
        $this->set(compact('department'));
    }

    /**
     * Edit an existing department.
     *
     * @param string|null $id Department ID
     * @return \Cake\Http\Response|null|void Redirects to view
     * @throws \Cake\Http\Exception\NotFoundException When department not found
     */
    public function edit($id = null)
    {
        // Load department with minimal associations for edit performance
        $department = $this->Departments->get($id, contain: []);

        // Validate department exists
        if (!$department) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user can modify this specific department
        $this->Authorization->authorize($department);

        // Process form submission
        if ($this->request->is(['patch', 'post', 'put'])) {
            // Patch entity with submitted form data
            $department = $this->Departments->patchEntity($department, $this->request->getData());

            // Attempt to save with feedback
            if ($this->Departments->save($department)) {
                $this->Flash->success(__('The department has been saved.'));
            } else {
                $this->Flash->error(__('The department could not be saved. Please, try again.'));
            }
        }

        // Always redirect to view for consistent navigation
        // Success: Shows updated department with success message
        // Failure: Shows current department with error message
        return $this->redirect(['action' => 'view', $department->id]);
    }

    /**
     * Delete a department (soft delete).
     *
     * @param string|null $id Department ID
     * @return \Cake\Http\Response|null Redirects to index
     * @throws \Cake\Http\Exception\NotFoundException When department not found
     */
    public function delete($id = null)
    {
        // Restrict to secure HTTP methods for CSRF protection
        $this->request->allowMethod(['post', 'delete']);

        // Load department for deletion
        $department = $this->Departments->get($id);

        // Validate department exists
        if (!$department) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user can delete this specific department
        $this->Authorization->authorize($department);

        // Implement soft deletion pattern
        // Mark department as deleted while preserving referential integrity
        $department->name = "Deleted: " . $department->name;

        // Attempt deletion with comprehensive error handling
        if ($this->Departments->delete($department)) {
            $this->Flash->success(__('The department has been deleted.'));
        } else {
            $this->Flash->error(__('The department could not be deleted. Please, try again.'));
        }

        // Redirect to index for continued administration
        return $this->redirect(['action' => 'index']);
    }
}
