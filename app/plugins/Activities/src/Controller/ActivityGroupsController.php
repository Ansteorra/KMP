<?php

declare(strict_types=1);

namespace Activities\Controller;

use App\Controller\DataverseGridTrait;

/**
 * ActivityGroups Controller - Activity Categorization Management
 *
 * Manages activity group CRUD operations for organizing activities into categories.
 * Activity groups enable categorical organization, navigation support, and reporting.
 * Uses DataverseGridTrait for table-based data display.
 *
 * @property \Activities\Model\Table\ActivityGroupsTable $ActivityGroups
 * @package Activities\Controller
 */
class ActivityGroupsController extends AppController
{
    use DataverseGridTrait;

    /**
     * Initialize controller with model-level authorization.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add", "gridData");
    }

    /**
     * Display paginated list of activity groups.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Provide grid data for Activity Groups listing.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(\App\Services\CsvExportService $csvExportService)
    {
        // Build base query
        $baseQuery = $this->ActivityGroups->find();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Activities.ActivityGroups.index.main',
            'gridColumnsClass' => \Activities\KMP\GridColumns\ActivityGroupsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'ActivityGroups',
            'defaultSort' => ['ActivityGroups.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Set view variables
        $this->set([
            'activityGroups' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \Activities\KMP\GridColumns\ActivityGroupsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'activity-groups-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'activity-groups-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'activity-groups-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display activity group with associated activities.
     *
     * Loads group entity with related activities and renders view. Entity-level authorization
     * checks that user can view the specific group.
     *
     * @param string|null $id Activity Group id for detailed view
     * @return \Cake\Http\Response|null|void Renders the view template with group details
     * @throws \Cake\Http\Exception\NotFoundException When group not found
     * @throws \Authorization\Exception\ForbiddenException When user lacks view permission
     * @see 5.6.3-activity-groups-controller-reference.md for detailed documentation
     */
    public function view($id = null)
    {
        $authorizationGroup = $this->ActivityGroups->get(
            $id,
            contain: ["Activities"],
        );
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($authorizationGroup);
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Display and process creation of new activity groups.
     *
     * Handles GET requests for form display and POST requests for form submission. Validates
     * form data through ActivityGroupsTable validation rules and redirects to view on success.
     * Model-level authorization configured in initialize() determines access.
     *
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form otherwise
     * @see 5.6.3-activity-groups-controller-reference.md for detailed documentation
     */
    public function add()
    {
        $authorizationGroup = $this->ActivityGroups->newEmptyEntity();
        if ($this->request->is("post")) {
            $authorizationGroup = $this->ActivityGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->ActivityGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Activity Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Activity Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Display and process modification of existing activity groups.
     *
     * Handles GET requests for form display with existing data and POST/PATCH/PUT requests for form
     * submission. Loads entity with authorization validation, validates form data through
     * ActivityGroupsTable validation rules, and redirects to view on success.
     *
     * Note: Sets template variable as 'ActivityGroup' (should be 'authorizationGroup' for consistency).
     *
     * @param string|null $id Activity Group id for editing
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form otherwise
     * @throws \Cake\Http\Exception\NotFoundException When group not found
     * @throws \Authorization\Exception\ForbiddenException When user lacks edit permission
     * @see 5.6.3-activity-groups-controller-reference.md for detailed documentation
     */
    public function edit($id = null)
    {
        $authorizationGroup = $this->ActivityGroups->get($id, contain: []);
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationGroup);
        if ($this->request->is(["patch", "post", "put"])) {
            $authorizationGroup = $this->ActivityGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->ActivityGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Activity Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Activity Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Secure deletion of activity groups with referential integrity protection.
     *
     * Validates HTTP method (POST/DELETE only), checks entity existence and authorization,
     * prevents deletion of groups with associated activities, and implements soft deletion
     * pattern by prefixing group name with "Deleted: ". Always redirects to index.
     *
     * @param string|null $id Activity Group id for deletion
     * @return \Cake\Http\Response|null Redirects to index after deletion attempt
     * @throws \Cake\Http\Exception\NotFoundException When group not found
     * @throws \Cake\Http\Exception\MethodNotAllowedException When invalid HTTP method used
     * @throws \Authorization\Exception\ForbiddenException When user lacks delete permission
     * @see 5.6.3-activity-groups-controller-reference.md for detailed documentation
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $authorizationGroup = $this->ActivityGroups->get(
            $id,
            contain: ["Activities"]
        );
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($authorizationGroup->activities) {
            $this->Flash->error(
                __("The Activity Group could not be deleted because it has associated Activities."),
            );
            return $this->redirect(["action" => "index"]);
        }
        $this->Authorization->authorize($authorizationGroup);

        $authorizationGroup->name = "Deleted: " . $authorizationGroup->name;
        if ($this->ActivityGroups->delete($authorizationGroup)) {
            $this->Flash->success(
                __("The Activity Group has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Activity Group could not be deleted. Please, try again.",
                ),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}