<?php

declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;
use Awards\Controller\AppController;

/**
 * Awards Controller - Award Management and Hierarchical Organization
 * 
 * Provides CRUD operations for award configuration within the Domain/Level/Branch
 * hierarchy. Includes API endpoints for award discovery and recommendation integration.
 * 
 * Uses DataverseGridTrait for table-based data display.
 * 
 * @property \Awards\Model\Table\AwardsTable $Awards
 * @package Awards\Controller
 */
class AwardsController extends AppController
{
    use DataverseGridTrait;
    /**
     * Initialize Awards Controller.
     * 
     * Configures authorization for index/add/gridData and allows
     * unauthenticated access to awardsByDomain endpoint.
     * 
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add", "gridData");

        $this->Authentication->allowUnauthenticated([
            "awardsByDomain"
        ]);
    }

    /**
     * Award Index - Administrative award listing.
     * 
     * @return \Cake\Http\Response|null|void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Provide grid data for Awards listing.
     *
     * This method serves data for the Dataverse grid component via Turbo Frame requests.
     * Handles filtering, sorting, pagination, and CSV export.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(\App\Services\CsvExportService $csvExportService)
    {
        // Build base query with domain, level, and branch info
        $baseQuery = $this->Awards->find()
            ->contain([
                'Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Levels' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Awards.index.main',
            'gridColumnsClass' => \Awards\KMP\GridColumns\AwardsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Awards',
            'defaultSort' => ['Awards.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'awards');
        }

        // Set view variables
        $this->set([
            'awards' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \Awards\KMP\GridColumns\AwardsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'awards-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'awards-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'awards-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display a single award with its domain, level, branch, and related gathering activities and prepare form data for the management view.
     *
     * Loads the award and its related Domains, Levels, Branches, and GatheringActivities, enforces entity authorization,
     * and provides lists for domains, levels (ordered by progression), branch tree, and gathering activities not yet associated
     * with the award for use in the view.
     *
     * @param string|null $id Award identifier to retrieve.
     * @return \Cake\Http\Response|null|void A Response when the action issues a redirect or other response, otherwise no value.
     * @throws \Cake\Http\Exception\NotFoundException If no award exists with the provided id.
     */
    public function view($id = null)
    {
        $award = $this->Awards->find()->where(['Awards.id' => $id])
            ->contain([
                'Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Levels' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name', 'description']);
                }
            ])
            ->first();

        if (!$award) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($award);

        $awardsDomains = $this->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Awards->Levels->find('list', limit: 200, orderBy: ["progression_order"])->all();
        $branches = $this->Awards->Branches
            ->find("treeList", spacer: "--", keyPath: function ($entity) {
                return $entity->id;
            })
            ->orderBy(["name" => "ASC"])->toArray();

        // Get available activities for the add modal
        $gatheringActivitiesTable = $this->fetchTable('GatheringActivities');
        $existingActivityIds = array_map(function ($activity) {
            return $activity->id;
        }, $award->gathering_activities);

        $availableActivities = $gatheringActivitiesTable->find('list')
            ->where(function ($exp) use ($existingActivityIds) {
                if (!empty($existingActivityIds)) {
                    return $exp->notIn('id', $existingActivityIds);
                }
                return $exp;
            })
            ->orderBy(['name' => 'ASC'])
            ->toArray();

        $this->set(compact('award', 'awardsDomains', 'awardsLevels', 'branches', 'availableActivities'));
    }

    /**
     * Award Add - Create new award.
     * 
     * @return \Cake\Http\Response|null|void Redirects on success, renders form otherwise
     */
    public function add()
    {
        $award = $this->Awards->newEmptyEntity();
        if ($this->request->is('post')) {
            $award = $this->Awards->patchEntity($award, $this->request->getData());
            if ($this->Awards->save($award)) {
                $this->Flash->success(__('The award has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The award could not be saved. Please, try again.'));
        }
        $awardsDomains = $this->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Awards->Branches
            ->find("treeList", spacer: "--", keyPath: function ($entity) {
                return $entity->id;
            })
            ->orderBy(["name" => "ASC"])->toArray();
        $this->set(compact('award', 'awardsDomains', 'awardsLevels', 'branches'));
    }

    /**
     * Award Edit - Modify existing award with specialty JSON handling.
     * 
     * @param string|null $id Award identifier
     * @return \Cake\Http\Response|null|void Redirects to award view after processing
     * @throws \Cake\Http\Exception\NotFoundException When award not found
     */
    public function edit($id = null)
    {
        $award = $this->Awards->get($id, contain: []);
        if (!$award) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($award);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $award = $this->Awards->patchEntity($award, $this->request->getData());
            $specialties = json_decode($this->request->getData('specialties'), true);
            $award->specialties = $specialties;
            if ($this->Awards->save($award)) {
                $this->Flash->success(__('The award has been saved.'));

                return $this->redirect(['action' => 'view', $award->id]);
            }
            $this->Flash->error(__('The award could not be saved. Please, try again.'));
        }
        return $this->redirect(['action' => 'view', $award->id]);
    }

    /**
     * Award Delete - Soft deletion with referential integrity check.
     * 
     * Prevents deletion if recommendations exist. Prefixes name with "Deleted:".
     * 
     * @param string|null $id Award identifier
     * @return \Cake\Http\Response|null Redirects to index or view based on outcome
     * @throws \Cake\Http\Exception\NotFoundException When award not found
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $award = $this->Awards->get($id);
        if (!$award) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($award);

        $countRecommendations = $this->Awards->Recommendations->find()
            ->where(['award_id' => $award->id])
            ->count();
        if ($countRecommendations > 0) {
            $this->Flash->error(
                __('The award could not be deleted because it has {0} recommendations.', $countRecommendations)
            );
            return $this->redirect(['action' => 'view', $award->id]);
        }
        $award->name = "Deleted: " . $award->name;
        if ($this->Awards->delete($award)) {
            $this->Flash->success(__('The award has been deleted.'));
        } else {
            $this->Flash->error(__('The award could not be deleted. Please, try again.'));
            return $this->redirect(['action' => 'view', $award->id]);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Provide a JSON list of awards filtered by a domain, including domain,
     * level, and branch context ordered by level progression and award name.
     *
     * @param string|null $domainId Domain identifier to filter awards; pass `null` to select awards with no domain.
     * @return \Cake\Http\Response JSON response containing an array of awards with their associated Domains, Levels, and Branches.
     */
    public function awardsByDomain($domainId = null)
    {
        $this->Authorization->skipAuthorization();
        $awards = $this->Awards->find()
            ->where(['domain_id' => $domainId])
            ->contain([
                'Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Levels' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->orderBy(["Levels.progression_order" => "ASC", "Awards.name" => "ASC"])
            ->all();
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($awards));
        return $this->response;
    }

    /**
     * Associate a gathering activity with an award.
     *
     * Creates a join record linking the specified award to the provided gathering activity
     * and redirects back to the award's view page.
     *
     * @param string|null $id The award identifier.
     * @return \Cake\Http\Response|null Redirect response to the award view.
     * @throws \Cake\Http\Exception\NotFoundException If the award cannot be found.
     */
    public function addActivity($id = null)
    {
        $this->request->allowMethod(['post']);

        $award = $this->Awards->get($id);
        $this->Authorization->authorize($award, 'edit');

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $gatheringActivityId = $data['gathering_activity_id'] ?? null;

            if (!$gatheringActivityId) {
                $this->Flash->error(__('Please select an activity.'));
                return $this->redirect(['action' => 'view', $id]);
            }

            // Create the association
            $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
            $awardGatheringActivity = $awardGatheringActivitiesTable->newEntity([
                'award_id' => $id,
                'gathering_activity_id' => $gatheringActivityId,
            ]);

            if ($awardGatheringActivitiesTable->save($awardGatheringActivity)) {
                $this->Flash->success(__('The activity has been added to this award.'));
            } else {
                // Log validation errors for debugging
                $errors = $awardGatheringActivity->getErrors();
                if (!empty($errors)) {
                    \Cake\Log\Log::error('Failed to add activity to award: ' . json_encode($errors));
                    $errorMessages = [];
                    foreach ($errors as $field => $fieldErrors) {
                        foreach ($fieldErrors as $error) {
                            $errorMessages[] = "$field: $error";
                        }
                    }
                    $this->Flash->error(__('The activity could not be added: {0}', implode(', ', $errorMessages)));
                } else {
                    \Cake\Log\Log::error('Failed to add activity to award with no validation errors');
                    $this->Flash->error(__('The activity could not be added. Please try again.'));
                }
            }
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Dissociate a gathering activity from an award.
     *
     * Deletes the association between the specified award and gathering activity and sets an appropriate flash message.
     *
     * @param string|null $awardId Award identifier.
     * @param string|null $activityId Gathering activity identifier.
     * @return \Cake\Http\Response|null Redirects to the award view or returns Turbo Stream content when requested.
     * @throws \Cake\Http\Exception\NotFoundException If the award does not exist.
     */
    public function removeActivity($awardId = null, $activityId = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $award = $this->Awards->get($awardId);
        $this->Authorization->authorize($award, 'edit');

        // Check if this is a Turbo request (not a regular form submission)
        $isTurboRequest = $this->request->getHeaderLine('Accept') !== '' &&
            strpos($this->request->getHeaderLine('Accept'), 'text/vnd.turbo-stream.html') !== false ||
            $this->request->is('ajax');

        $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
        $awardGatheringActivity = $awardGatheringActivitiesTable->find()
            ->where([
                'award_id' => $awardId,
                'gathering_activity_id' => $activityId,
            ])
            ->first();

        if ($awardGatheringActivity) {
            if ($awardGatheringActivitiesTable->delete($awardGatheringActivity)) {
                $this->Flash->success(__('The activity has been removed from this award.'));
            } else {
                $this->Flash->error(__('The activity could not be removed. Please try again.'));
            }
        } else {
            $this->Flash->error(__('Activity association not found.'));
        }

        // If this is a Turbo request from GatheringActivity view, render the cell
        if ($isTurboRequest) {
            // Create a view instance to render the cell
            $view = $this->createView();
            $cell = $view->cell('Awards.ActivityAwards', [$activityId]);

            // Get flash messages
            $flashMessages = $this->request->getSession()->read('Flash');
            $this->request->getSession()->delete('Flash');

            // Build Turbo Stream response
            $turboStream = $this->_buildTurboStreamResponse($cell->render(), $flashMessages);
            $this->response = $this->response
                ->withType('text/vnd.turbo-stream.html')
                ->withStringBody($turboStream);
            return $this->response;
        }

        return $this->redirect(['action' => 'view', $awardId]);
    }

    /**
     * Attach an award to a gathering activity from the GatheringActivity context.
     *
     * Creates an AwardGatheringActivity association and returns either a turbo-stream
     * response updating the activity's awards cell (for Turbo/AJAX requests) or a
     * redirect to the gathering activity view for standard form submissions.
     *
     * @param string|null $activityId The gathering activity identifier.
     * @return \Cake\Http\Response|null A Response containing turbo-stream HTML when the request expects turbo streams; otherwise a redirect Response to the gathering activity view.
     * @throws \Cake\Http\Exception\NotFoundException If the specified gathering activity does not exist.
     */
    public function addActivityToGatheringActivity($activityId = null)
    {
        $this->request->allowMethod(['post']);

        // Load the gathering activity to check permissions
        $gatheringActivitiesTable = $this->fetchTable('GatheringActivities');
        $gatheringActivity = $gatheringActivitiesTable->get($activityId);
        $this->Authorization->authorize($gatheringActivity, 'edit');

        // Check if this is a Turbo request (not a regular form submission)
        $isTurboRequest = $this->request->getHeaderLine('Accept') !== '' &&
            strpos($this->request->getHeaderLine('Accept'), 'text/vnd.turbo-stream.html') !== false ||
            $this->request->is('ajax');

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $awardId = $data['award_id'] ?? null;

            if (!$awardId) {
                $this->Flash->error(__('Please select an award.'));
                if ($isTurboRequest) {
                    // Return Turbo Stream with frame update and flash messages
                    $view = $this->createView();
                    $cell = $view->cell('Awards.ActivityAwards', [$gatheringActivity->id]);

                    // Get flash messages
                    $flashMessages = $this->request->getSession()->read('Flash');
                    $this->request->getSession()->delete('Flash');

                    // Build Turbo Stream response
                    $turboStream = $this->_buildTurboStreamResponse($cell->render(), $flashMessages);
                    $this->response = $this->response
                        ->withType('text/vnd.turbo-stream.html')
                        ->withStringBody($turboStream);
                    return $this->response;
                }
                return $this->redirect(['plugin' => null, 'controller' => 'GatheringActivities', 'action' => 'view', $activityId]);
            }

            // Create the association
            $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
            $awardGatheringActivity = $awardGatheringActivitiesTable->newEntity([
                'award_id' => $awardId,
                'gathering_activity_id' => $activityId,
            ]);

            if ($awardGatheringActivitiesTable->save($awardGatheringActivity)) {
                $this->Flash->success(__('The award has been added to this activity.'));
            } else {
                // Log validation errors for debugging
                $errors = $awardGatheringActivity->getErrors();
                if (!empty($errors)) {
                    \Cake\Log\Log::error('Failed to add award to activity: ' . json_encode($errors));
                    $errorMessages = [];
                    foreach ($errors as $field => $fieldErrors) {
                        foreach ($fieldErrors as $error) {
                            $errorMessages[] = "$field: $error";
                        }
                    }
                    $this->Flash->error(__('The award could not be added: {0}', implode(', ', $errorMessages)));
                } else {
                    \Cake\Log\Log::error('Failed to add award to activity with no validation errors');
                    $this->Flash->error(__('The award could not be added. Please try again.'));
                }
            }
        }

        // If this is a Turbo request, render the cell instead of redirecting
        if ($isTurboRequest) {
            // Create a view instance to render the cell
            $view = $this->createView();
            $cell = $view->cell('Awards.ActivityAwards', [$gatheringActivity->id]);

            // Get flash messages
            $flashMessages = $this->request->getSession()->read('Flash');
            $this->request->getSession()->delete('Flash');

            // Build Turbo Stream response
            $turboStream = $this->_buildTurboStreamResponse($cell->render(), $flashMessages);
            $this->response = $this->response
                ->withType('text/vnd.turbo-stream.html')
                ->withStringBody($turboStream);
            return $this->response;
        }

        return $this->redirect(['plugin' => null, 'controller' => 'GatheringActivities', 'action' => 'view', $activityId]);
    }

    /**
     * Builds a Turbo Stream payload that replaces the flash messages frame and,
     * if present, replaces the provided turbo-frame with the given content.
     *
     * Flash messages (if provided) are rendered as Bootstrap alert markup. The
     * supplied $frameContent should include a <turbo-frame id="..."> element;
     * when an id is found that frame will be replaced in the returned payload.
     *
     * @param string $frameContent HTML containing a turbo-frame to be inserted/replaced
     * @param array|null $flashMessages Flash messages grouped by key; each message is an array
     *                                 with a 'message' string and an optional 'element'
     *                                 (e.g. 'flash/success') used to determine the alert type
     * @return string The combined turbo-stream HTML payload
     */
    protected function _buildTurboStreamResponse(string $frameContent, ?array $flashMessages = null): string
    {
        $streams = [];

        // Always include the frame update
        // The frame content already includes <turbo-frame id="...">
        $streams[] = '<turbo-stream action="replace" target="flash-messages">';
        $streams[] = '<template>';

        // Render flash messages if any
        if (!empty($flashMessages)) {
            foreach ($flashMessages as $key => $messages) {
                foreach ($messages as $message) {
                    $text = $message['message'] ?? '';

                    // Extract type from element field (e.g., 'flash/success' -> 'success')
                    $element = $message['element'] ?? 'flash/info';
                    $type = 'info';
                    if (strpos($element, '/') !== false) {
                        $parts = explode('/', $element);
                        $type = end($parts);
                    }

                    // Map CakePHP flash types to Bootstrap alert types
                    $alertType = match ($type) {
                        'error' => 'danger',
                        'success' => 'success',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'info'
                    };

                    $streams[] = sprintf(
                        '<div class="alert alert-%s alert-dismissible fade show" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>',
                        h($alertType),
                        h($text)
                    );
                }
            }
        }


        $streams[] = '</template>';
        $streams[] = '</turbo-stream>';

        // Add the frame content as a second turbo-stream
        // Extract the frame ID from the content
        if (preg_match('/<turbo-frame id="([^"]+)"/', $frameContent, $matches)) {
            $frameId = $matches[1];
            $streams[] = sprintf('<turbo-stream action="replace" target="%s">', h($frameId));
            $streams[] = '<template>';
            $streams[] = $frameContent;
            $streams[] = '</template>';
            $streams[] = '</turbo-stream>';
        }

        return implode("\n", $streams);
    }
}
