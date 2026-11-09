<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\GatheringActivitiesGridColumns;
use App\Model\Entity\GatheringActivity;
use App\Services\CsvExportService;
use App\Services\GatheringActivityOrderingService;
use App\Services\GatheringActivityPresentationService;
use Cake\Http\Response;
use Psr\Http\Message\UploadedFileInterface;

/**
 * GatheringActivities Controller
 *
 * @property \App\Model\Table\GatheringActivitiesTable $GatheringActivities
 * @method \App\Model\Entity\GatheringActivity[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class GatheringActivitiesController extends AppController
{
    use DataverseGridTrait;

    /**
     * Add an existing permission to the activity's assignment rules.
     *
     * @param string|null $id Activity ID
     * @return \Cake\Http\Response|null
     */
    public function addAssignmentPermission($id = null)
    {
        $this->request->allowMethod(['post']);
        $activity = $this->GatheringActivities->get($id);
        $this->Authorization->authorize($activity, 'edit');
        $permission = $this->fetchTable('Permissions')->get((int)$this->request->getData('permission_id'));
        $rules = $this->fetchTable('GatheringActivityAssignmentPermissions');
        if ($rules->exists(['gathering_activity_id' => $activity->id, 'permission_id' => $permission->id])) {
            $this->Flash->error(__('This permission is already assigned to the activity.'));
        } elseif ($this->GatheringActivities->AssignmentPermissions->link($activity, [$permission])) {
            $this->Flash->success(__('The permission has been added to the activity.'));
        } else {
            $this->Flash->error(__('The permission could not be added. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $activity->id, '?' => ['tab' => 'assigned-by']]);
    }

    /**
     * Remove one assignment rule without changing other rules or the permission definition.
     *
     * @param string|null $id Activity ID
     * @return \Cake\Http\Response|null
     */
    public function deleteAssignmentPermission($id = null)
    {
        $this->request->allowMethod(['post']);
        $activity = $this->GatheringActivities->get($id);
        $this->Authorization->authorize($activity, 'edit');
        $permission = $this->fetchTable('Permissions')->get((int)$this->request->getData('permission_id'));
        if ($this->GatheringActivities->AssignmentPermissions->unlink($activity, [$permission])) {
            $this->Flash->success(__('The permission has been removed from the activity.'));
        } else {
            $this->Flash->error(__('The permission could not be removed. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $activity->id, '?' => ['tab' => 'assigned-by']]);
    }

    /**
     * @param \App\Model\Entity\GatheringActivity $activity Validated activity
     * @return bool
     */
    private function saveAppearance($activity): bool
    {
        $file = $this->request->getData('icon_file');
        $result = (new GatheringActivityPresentationService())->save(
            $activity,
            $file instanceof UploadedFileInterface ? $file : null,
            filter_var($this->request->getData('remove_icon', false), FILTER_VALIDATE_BOOLEAN),
        );

        return $result->success;
    }

    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Authorize model-level operations
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
    }

    /**
     * Index method - Display Dataverse grid for gathering activities
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Grid Data method - Provides Dataverse grid data for gathering activities
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'GatheringActivities.index.main',
            'gridColumnsClass' => GatheringActivitiesGridColumns::class,
            'baseQuery' => $this->GatheringActivities->find(),
            'tableName' => 'GatheringActivities',
            'defaultSort' => GatheringActivityOrderingService::queryOrder(),
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'gathering-activities');
        }

        // Set view variables
        $this->set([
            'gatheringActivities' => $result['data'],
            'rowActions' => GatheringActivitiesGridColumns::getRowActions(),
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => GatheringActivitiesGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'gathering-activities-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'gathering-activities-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'gathering-activities-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * View method
     *
     * @param string|null $id Gathering Activity id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $gatheringActivity = $this->GatheringActivities->get($id, contain: [
            'Gatherings',
            'AssignmentPermissions',
        ]);

        $this->Authorization->authorize($gatheringActivity);

        $this->set(compact('gatheringActivity'));
        $assignedPermissionIds = array_column($gatheringActivity->assignment_permissions, 'id');
        $permissionOptions = $this->fetchTable('Permissions')->find()
            ->where(['Permissions.id NOT IN' => $assignedPermissionIds ?: [0]])
            ->orderBy(['Permissions.name' => 'ASC'])->all()->combine(
                'id',
                static fn($permission): string => $permission->name . ' (' . $permission->scoping_rule . ')',
            )->toArray();
        $identity = $this->Authentication->getIdentity();
        $canManageAssignmentPermissions = $identity->checkCan('edit', $gatheringActivity);
        $canViewPermissions = $identity->checkCan('view', 'Permissions');
        $this->set(compact('permissionOptions', 'canManageAssignmentPermissions', 'canViewPermissions'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $gatheringActivity = $this->GatheringActivities->newEmptyEntity();
        $this->Authorization->authorize($gatheringActivity);
        $pageContext = $this->wantsTurboStreamRequest() ? $this->getPageContextUrl() : null;

        if ($this->request->is('post')) {
            $gatheringActivity = $this->GatheringActivities->patchEntity($gatheringActivity, $this->request->getData());

            if ($this->saveAppearance($gatheringActivity)) {
                $this->Flash->success(__(
                    'The activity "{0}" has been created successfully.',
                    $gatheringActivity->name,
                ));

                return $this->activitySaved($gatheringActivity, $pageContext, true);
            }

            $errors = $gatheringActivity->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering activity could not be saved: {0}',
                    implode(', ', $errorMessages),
                ));
            } else {
                $this->Flash->error(__('The gathering activity could not be saved. Please, try again.'));
            }
        }

        return $this->renderActivityEditor($gatheringActivity);
    }

    /**
     * Edit method
     *
     * @param string|null $id Gathering Activity id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $gatheringActivity = $this->GatheringActivities->get($id);
        $this->Authorization->authorize($gatheringActivity);
        $pageContext = $this->wantsTurboStreamRequest() ? $this->getPageContextUrl() : null;

        if ($this->request->is(['patch', 'post', 'put'])) {
            $gatheringActivity = $this->GatheringActivities->patchEntity($gatheringActivity, $this->request->getData());

            if ($this->saveAppearance($gatheringActivity)) {
                $this->Flash->success(__(
                    'The activity "{0}" has been updated successfully.',
                    $gatheringActivity->name,
                ));

                return $this->activitySaved($gatheringActivity, $pageContext, false);
            }

            $errors = $gatheringActivity->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering activity could not be updated: {0}',
                    implode(', ', $errorMessages),
                ));
            } else {
                $this->Flash->error(__('The gathering activity could not be saved. Please, try again.'));
            }
        }

        return $this->renderActivityEditor($gatheringActivity);
    }

    /**
     * @param \App\Model\Entity\GatheringActivity $activity Saved activity
     * @param string|null $pageContext Validated originating page
     * @param bool $created New activity
     * @return \Cake\Http\Response
     */
    private function activitySaved(GatheringActivity $activity, ?string $pageContext, bool $created): Response
    {
        if ($this->wantsTurboStreamRequest()) {
            if ($pageContext === null || $this->matchesGridIndexPath($pageContext, '#/gathering-activities/?$#')) {
                return $this->renderTurboCloseModal(
                    'gathering-activities-grid-table',
                    ['controller' => 'GatheringActivities', 'action' => 'gridData'],
                    $pageContext,
                );
            }
            if ($this->matchesGridIndexPath($pageContext, '#/gathering-activities/view/' . $activity->id . '/?$#')) {
                return $this->redirect($pageContext);
            }
        }

        return $created ? $this->redirect(['action' => 'index'])
            : $this->redirect(['action' => 'view', $activity->id]);
    }

    /**
     * @param \App\Model\Entity\GatheringActivity $gatheringActivity Activity and validation errors
     * @return \Cake\Http\Response|null
     */
    private function renderActivityEditor(GatheringActivity $gatheringActivity): ?Response
    {
        $this->set(compact('gatheringActivity'));
        if ($this->wantsTurboStreamRequest() && $this->request->is(['post', 'put', 'patch'])) {
            $this->response = $this->response->withStatus(422)->withType('text/vnd.turbo-stream.html');
            $this->viewBuilder()->disableAutoLayout()->setTemplate('form_errors');

            return $this->render();
        }
        if ($this->request->getHeaderLine('Turbo-Frame') === 'gatheringActivityFormFrame') {
            $this->viewBuilder()->setLayout('turbo_frame')->setTemplate('form');
        }

        return null;
    }

    /**
     * Delete method
     *
     * @param string|null $id Gathering Activity id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $gatheringActivity = $this->GatheringActivities->get($id);
        $this->Authorization->authorize($gatheringActivity);

        $activityName = $gatheringActivity->name;

        // Check if activity is used by any gatherings
        $gatheringCount = $this->GatheringActivities->Gatherings->find()
            ->matching('GatheringActivities', function ($q) use ($id) {
                return $q->where(['GatheringActivities.id' => $id]);
            })
            ->count();

        if ($gatheringCount > 0) {
            $this->Flash->error(__(
                'Cannot delete activity "{0}" because it is used by {1} gathering(s).'
                    . ' Please remove this activity from those gatherings first.',
                $activityName,
                $gatheringCount,
            ));

            return $this->redirect(['action' => 'index']);
        }

        // Check if activity has waiver requirements
        $waiverCount = $this->fetchTable('Waivers.GatheringActivityWaivers')->find()
            ->where(['gathering_activity_id' => $id])
            ->count();

        if ($waiverCount > 0) {
            $this->Flash->error(__(
                'Cannot delete activity "{0}" because it has {1} waiver requirement(s)'
                    . ' associated with it. Please remove the waiver requirements first.',
                $activityName,
                $waiverCount,
            ));

            return $this->redirect(['action' => 'index']);
        }

        if ($this->GatheringActivities->delete($gatheringActivity)) {
            $this->Flash->success(__(
                'The activity "{0}" has been deleted successfully.',
                $activityName,
            ));
        } else {
            $errors = $gatheringActivity->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The activity "{0}" could not be deleted: {1}',
                    $activityName,
                    implode(', ', $errorMessages),
                ));
            } else {
                $this->Flash->error(__(
                    'The activity "{0}" could not be deleted. Please, try again.',
                    $activityName,
                ));
            }
        }

        return $this->redirect(['action' => 'index']);
    }
}
