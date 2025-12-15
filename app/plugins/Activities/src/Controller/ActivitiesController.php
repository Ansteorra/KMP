<?php

declare(strict_types=1);

namespace Activities\Controller;

use App\Controller\DataverseGridTrait;
use App\Services\CsvExportService;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Activities\Model\Entity\Authorization;

/**
 * Activities Controller - Activity Definition and Authorization Management
 *
 * Manages activity definitions (authorization types), configuration, and approval workflows.
 * Activities are authorization types that members can request (e.g., "Marshal", "Water Bearer").
 * Uses DataverseGridTrait for table-based data display.
 *
 * @property \Activities\Model\Table\ActivitiesTable $Activities
 * @package Activities\Controller
 */
class ActivitiesController extends AppController
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
     * Display activity listing page.
     *
     * Renders dv_grid container element; actual data lazy-loads via gridData action.
     * See docs/5.6.2-activities-controller-reference.md for detailed documentation.
     *
     * @return void
     */
    public function index(): void
    {
        // Grid data loads via Turbo Frame AJAX request
    }

    /**
     * Provides Dataverse grid data with toolbar, filtering, sorting, and CSV export.
     *
     * Handles Turbo Frame requests for outer frame (toolbar + table) and inner frame (table only).
     * See docs/5.6.2-activities-controller-reference.md#grid-data-action for detailed documentation.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders grid view or CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Build base query with activity group and role info
        $baseQuery = $this->Activities->find()
            ->contain([
                'ActivityGroups' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Roles' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ]);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Activities.Activities.index.main',
            'gridColumnsClass' => \Activities\KMP\GridColumns\ActivitiesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Activities',
            'defaultSort' => ['Activities.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'activities');
        }

        // Set view variables
        $this->set([
            'activities' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \Activities\KMP\GridColumns\ActivitiesGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'activities-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'activities-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'activities-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display comprehensive activity details with authorization statistics.
     *
     * Shows activity configuration, authorization statistics (active/pending/previous counts),
     * and roles with approval authority. See docs/5.6.2-activities-controller-reference.md#view-action
     *
     * @param string|null $id Activity ID for detail display
     * @return void Renders activity detail view with statistics
     * @throws \Cake\Http\Exception\NotFoundException When activity not found
     */
    public function view(?string $id = null): void
    {
        $activity = $this->Activities->get(
            $id,
            contain: [
                "Permissions" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "ActivityGroups" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Roles" => function ($q) {
                    return $q->select(["id", "name"]);
                }
            ],
        );
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity);
        $activeCount = $this->Activities->CurrentAuthorizations->find()
            ->where(["activity_id" => $id])
            ->count();
        $pendingCount = $this->Activities->PendingAuthorizations->find()
            ->where(["activity_id" => $id])
            ->count();
        $previousCount = $this->Activities->PreviousAuthorizations->find()
            ->where(["activity_id" => $id])
            ->count();
        $isEmpty = $activeCount + $pendingCount + $previousCount == 0;
        if ($activity->permission_id) {
            $roles = $this->Activities->Permissions->Roles
                ->find()
                ->innerJoinWith("Permissions", function ($q) use (
                    $activity,
                ) {
                    return $q->where([
                        "OR" => [
                            "Permissions.id" =>
                            $activity->permission_id,
                            "Permissions.is_super_user" => true,
                        ],
                    ]);
                })
                ->distinct()
                ->all();
        } else {
            $roles = [];
        }
        $activityGroup = $this->Activities->ActivityGroups
            ->find("list")
            ->all();
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $authByPermissions = $this->Activities->Permissions
            ->find("list")
            ->all();
        $this->set(
            compact(
                "activity",
                "activityGroup",
                "roles",
                "authAssignableRoles",
                "authByPermissions",
                "pendingCount",
                "isEmpty",
                "id"
            ),
        );
    }

    /**
     * Create new Activity with complete configuration management.
     *
     * Renders form on GET; processes and saves on POST. See docs/5.6.2-activities-controller-reference.md#add-action
     * for detailed documentation.
     *
     * @return \Cake\Http\Response|null|void Redirects to activity view on success, renders form on GET
     */
    public function add()
    {
        $activity = $this->Activities->newEmptyEntity();
        if ($this->request->is("post")) {
            $activity = $this->Activities->patchEntity(
                $activity,
                $this->request->getData(),
            );
            if ($this->Activities->save($activity)) {
                $this->Flash->success(__("The authorization type has been saved."),);
                return $this->redirect(["action" => "view", $activity->id,]);
            }
            $this->Flash->error(__("The authorization type could not be saved. Please, try again.",),);
        }
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $activityGroup = $this->Activities->ActivityGroups
            ->find("list", limit: 200)
            ->all();
        $authByPermissions = $this->Activities->Permissions
            ->find("list")
            ->all();
        $this->set(compact(
            "activity",
            "activityGroup",
            "authAssignableRoles",
            "authByPermissions"
        ));
    }

    /**
     * Modify existing Activity with complete configuration management.
     *
     * Renders form on GET; processes and saves on PATCH/POST/PUT. See docs/5.6.2-activities-controller-reference.md#edit-action
     * for detailed documentation.
     *
     * @param string|null $id Activity ID for modification
     * @return \Cake\Http\Response|null Redirects to referrer on success, renders form on GET
     * @throws \Cake\Http\Exception\NotFoundException When activity not found
     */
    public function edit(?string $id = null)
    {
        $activity = $this->Activities->get($id, contain: []);
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity);
        if ($this->request->is(["patch", "post", "put"])) {
            $activity = $this->Activities->patchEntity(
                $activity,
                $this->request->getData(),
            );
            if ($this->Activities->save($activity)) {
                $this->Flash->success(
                    __("The authorization type has been saved."),
                );

                return $this->redirect(
                    $this->referer()
                );
            }
            $this->Flash->error(
                __(
                    "The authorization type could not be saved. Please, try again.",
                )
            );
            return $this->redirect(
                $this->referer()
            );
        }
        return $this->redirect(
            $this->referer()
        );
    }

    /**
     * Delete Activity with audit trail using soft deletion pattern.
     *
     * Securely deletes activity by prefixing name with "Deleted: ". Maintains referential integrity
     * and authorization history. See docs/5.6.2-activities-controller-reference.md#delete-action
     * for detailed documentation.
     *
     * @param string|null $id Activity ID for deletion
     * @return \Cake\Http\Response|null Redirects to activity index after deletion attempt
     * @throws \Cake\Http\Exception\NotFoundException When activity not found
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $activity = $this->Activities->get($id);
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity);
        $activity->name = "Deleted: " . $activity->name;
        if ($this->Activities->delete($activity)) {
            $this->Flash->success(
                __("The activity has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The activity could not be deleted. Please, try again.",
                ),
            );
        }

        return $this->redirect(["action" => "index"]);
    }

    /**
     * AJAX endpoint for discovering approvers for an activity and member.
     *
     * Permission-based discovery using Activity's getApproversQuery(). Returns JSON array of
     * approvers with id and sca_name (including branch name). See docs/5.6.2-activities-controller-reference.md#approvers-list
     * for detailed documentation.
     *
     * @param string|null $activityId Activity ID for permission-based approver discovery
     * @param string|null $memberId Member ID for organizational context and self-exclusion
     * @return \Cake\Http\Response JSON response with formatted approver list
     * @throws \Cake\Http\Exception\NotFoundException When activity not found
     */
    public function approversList(?string $activityId = null, ?string $memberId = null)
    {
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $activity = $this->Activities->get($activityId);
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->viewBuilder()->setClassName("Ajax");
        $member = TableRegistry::getTableLocator()->get('Members')->get($memberId);
        $query = $activity->getApproversQuery($member->branch_id);
        $result = $query
            ->contain(["Branches"])
            ->where(["Members.id !=" => $memberId])
            ->orderBy(["Branches.name", "Members.sca_name"])
            ->select(["Members.id", "Members.sca_name", "Branches.name"])
            ->distinct()
            ->all()
            ->toArray();
        $responseData = [];
        foreach ($result as $member) {
            $responseData[] = [
                "id" => $member->id,
                "sca_name" => $member->branch->name . ": " . $member->sca_name,
            ];
        }
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($responseData));

        return $this->response;
    }
}
