<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;

/**
 * Awards Controller - Award Management and Hierarchical Organization
 * 
 * Provides comprehensive award management functionality within the KMP Awards system,
 * implementing complete CRUD operations for award configuration and hierarchical
 * organization. This controller manages the administrative interface for award
 * creation, modification, and organization within the Domain/Level/Branch hierarchy.
 * 
 * The AwardsController serves as the central management interface for award
 * configuration, supporting the complex hierarchical relationships between
 * domains, levels, and branches that define the organizational structure of
 * the award system. It provides both administrative interfaces and API endpoints
 * for award discovery and recommendation workflow integration.
 * 
 * ## Core Functionality:
 * - **Award Lifecycle Management**: Complete CRUD operations for award configuration
 * - **Hierarchical Integration**: Management of Domain/Level/Branch relationships
 * - **API Endpoints**: JSON endpoints for dynamic award discovery and selection
 * - **Administrative Interface**: Web-based award management with form validation
 * - **Referential Integrity**: Protection against deletion of awards with recommendations
 * 
 * ## Security Architecture:
 * The controller implements comprehensive authorization through policy-based
 * access control, ensuring that award management operations are restricted
 * to authorized administrators while providing controlled public access to
 * award discovery endpoints for recommendation workflows.
 * 
 * ## Integration Points:
 * - **Recommendation System**: Awards serve as targets for recommendation workflows
 * - **Branch Hierarchy**: Awards are scoped to specific organizational levels
 * - **Domain/Level System**: Awards are categorized and ranked within hierarchical structure
 * - **Administrative Interfaces**: Integration with Awards plugin navigation and management
 * 
 * @property \Awards\Model\Table\AwardsTable $Awards Award data management and relationships
 * 
 * @package Awards\Controller
 * @see \Awards\Model\Table\AwardsTable For award data management
 * @see \Awards\Model\Entity\Award For award entity structure
 * @see \Awards\Controller\RecommendationsController For recommendation workflow integration
 */
class AwardsController extends AppController
{
    /**
     * Initialize Awards Controller - Authorization and security configuration
     * 
     * Configures the Awards controller with comprehensive authorization settings
     * and security framework integration for award management operations. This
     * initialization establishes the security baseline for administrative award
     * management while providing controlled public access to discovery endpoints.
     * 
     * ## Authorization Configuration:
     * - **Model Authorization**: Automatic authorization for index and add operations
     * - **Public Endpoints**: Unauthenticated access for award discovery API
     * - **Security Framework**: Integration with Awards plugin security baseline
     * 
     * ## Public Access Configuration:
     * The controller allows unauthenticated access to the awardsByDomain endpoint
     * to support dynamic award discovery in recommendation workflows without
     * requiring user authentication for basic award information retrieval.
     * 
     * @return void
     * 
     * @see \Awards\Controller\AppController::initialize() For base security configuration
     * @see \Authorization\Controller\Component\AuthorizationComponent::authorizeModel() For model authorization
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add");

        $this->Authentication->allowUnauthenticated([
            "awardsByDomain"
        ]);
    }

    /**
     * Award Index - Administrative award listing with hierarchical organization
     * 
     * Displays a comprehensive listing of all awards with their hierarchical
     * relationships to domains, levels, and branches. This administrative interface
     * provides pagination, search capabilities, and organizational context for
     * award management workflows and administrative oversight.
     * 
     * ## Query Optimization:
     * The method implements optimized database queries with selective field loading
     * and efficient containment to minimize data transfer while providing complete
     * hierarchical context for award organization and administrative management.
     * 
     * ## Authorization Integration:
     * Query results are automatically scoped through the authorization system
     * to ensure that users only see awards within their administrative scope
     * based on branch boundaries and permission-based access control.
     * 
     * ## Data Structure:
     * Returns awards with associated domain, level, and branch information
     * formatted for administrative display, including:
     * - Award identification and description
     * - Domain categorization for organizational context
     * - Level precedence for hierarchical ranking
     * - Branch scope for administrative boundaries
     * 
     * @return \Cake\Http\Response|null|void Renders administrative award listing view
     * 
     * @see \Awards\Model\Table\AwardsTable::find() For award query construction
     * @see \Authorization\Controller\Component\AuthorizationComponent::applyScope() For access control
     */
    public function index()
    {
        $query = $this->Awards->find()
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
            ->select(['id', 'name', 'description', 'domain_id', 'level_id', 'branch_id', "Domains.name", "Levels.name", "Branches.name"]);
        $query = $this->Authorization->applyScope($query, "index");
        $awards = $this->paginate($query);

        $this->set(compact('awards'));
    }

    /**
     * Award View - Comprehensive award detail display and management interface
     * 
     * Provides detailed award information with complete hierarchical context and
     * administrative management capabilities. This view serves as the central
     * interface for award inspection, modification, and hierarchical relationship
     * management within the awards system.
     * 
     * ## Award Detail Display:
     * Displays comprehensive award information including:
     * - Complete award configuration and description
     * - Domain, level, and branch hierarchical relationships
     * - Administrative metadata and audit trail information
     * - Integration context with recommendation workflows
     * 
     * ## Administrative Context:
     * Provides dropdown lists and selection interfaces for award modification:
     * - Domain selection for categorical organization
     * - Level selection with precedence ordering
     * - Branch selection with hierarchical tree display
     * 
     * ## Security Validation:
     * Implements entity-level authorization to ensure users can only view
     * awards within their administrative scope and organizational boundaries.
     * 
     * @param string|null $id Award identifier for detail retrieval
     * @return \Cake\Http\Response|null|void Renders award detail view
     * @throws \Cake\Http\Exception\NotFoundException When award not found
     * 
     * @see \Awards\Model\Table\AwardsTable::find() For award retrieval with relationships
     * @see \Authorization\Controller\Component\AuthorizationComponent::authorize() For entity authorization
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
     * Award Add - Administrative award creation interface
     * 
     * Provides the administrative interface for creating new awards within the
     * hierarchical organization system. This method handles both form display
     * and form processing for award creation with comprehensive validation
     * and hierarchical relationship management.
     * 
     * ## Form Processing:
     * - **GET Request**: Displays empty award creation form with dropdown options
     * - **POST Request**: Processes form submission with validation and database persistence
     * 
     * ## Hierarchical Context:
     * Provides dropdown selections for award organization:
     * - Domain selection for categorical placement
     * - Level selection for precedence hierarchy
     * - Branch selection for organizational scope
     * 
     * ## Validation and Error Handling:
     * Implements comprehensive form validation with user feedback through
     * Flash messaging system for both success and error conditions.
     * 
     * @return \Cake\Http\Response|null|void Redirects on successful creation, renders form otherwise
     * 
     * @see \Awards\Model\Table\AwardsTable::newEmptyEntity() For entity creation
     * @see \Awards\Model\Table\AwardsTable::save() For database persistence
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
     * Award Edit - In-place award modification with specialty handling
     * 
     * Provides streamlined award modification functionality with specialized
     * handling for complex fields like specialties (JSON data). This method
     * implements a redirect-based editing pattern that returns to the award
     * view after successful modification.
     * 
     * ## Editing Pattern:
     * - Loads existing award entity with authorization validation
     * - Processes form data with special handling for JSON fields
     * - Redirects to award view page after processing (success or failure)
     * 
     * ## Specialty Processing:
     * Implements specialized handling for the specialties field, which stores
     * JSON data representing award specialty categories and configurations.
     * This allows for dynamic specialty management without schema changes.
     * 
     * ## Authorization and Validation:
     * - Entity-level authorization ensures proper access control
     * - Comprehensive form validation with user feedback
     * - Error handling with Flash messaging for user guidance
     * 
     * @param string|null $id Award identifier for modification
     * @return \Cake\Http\Response|null|void Redirects to award view after processing
     * @throws \Cake\Http\Exception\NotFoundException When award not found
     * 
     * @see \Awards\Model\Table\AwardsTable::get() For entity retrieval
     * @see \Authorization\Controller\Component\AuthorizationComponent::authorize() For entity authorization
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
     * Award Delete - Soft deletion with referential integrity protection
     * 
     * Implements safe award deletion with comprehensive referential integrity
     * checking to prevent deletion of awards that have associated recommendations.
     * This method uses soft deletion patterns to preserve audit trails while
     * protecting data consistency.
     * 
     * ## Referential Integrity Protection:
     * Before deletion, the method checks for existing recommendations associated
     * with the award. If recommendations exist, deletion is prevented with
     * informative error messaging to guide administrative decision-making.
     * 
     * ## Soft Deletion Pattern:
     * - Prefixes award name with "Deleted:" marker for audit trail
     * - Uses soft deletion to preserve historical data and relationships
     * - Maintains referential integrity for existing recommendations
     * 
     * ## Security and Validation:
     * - Restricts to POST/DELETE methods for CSRF protection
     * - Entity-level authorization for access control
     * - Comprehensive error handling with user feedback
     * 
     * @param string|null $id Award identifier for deletion
     * @return \Cake\Http\Response|null Redirects to index or award view based on outcome
     * @throws \Cake\Http\Exception\NotFoundException When award not found
     * 
     * @see \Awards\Model\Table\AwardsTable::get() For entity retrieval
     * @see \Awards\Model\Table\RecommendationsTable::find() For referential integrity checking
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
     * Awards by Domain API - Dynamic award discovery for recommendation workflows
     * 
     * Provides a JSON API endpoint for dynamic award discovery based on domain
     * selection. This endpoint supports recommendation workflow interfaces by
     * returning awards filtered by domain with hierarchical organization and
     * level-based ordering for optimal user experience.
     * 
     * ## API Functionality:
     * - **Domain Filtering**: Returns awards within specified domain category
     * - **Hierarchical Data**: Includes domain, level, and branch context
     * - **Optimized Ordering**: Sorts by level progression order and award name
     * - **JSON Response**: Formatted for AJAX consumption and dynamic interfaces
     * 
     * ## Public Access:
     * This endpoint allows unauthenticated access to support recommendation
     * form workflows where users need to discover available awards based on
     * domain selection without requiring full authentication.
     * 
     * ## Query Optimization:
     * Implements efficient database queries with selective field loading and
     * strategic containment to minimize response size while providing complete
     * hierarchical context for award selection interfaces.
     * 
     * @param string|null $domainId Domain identifier for award filtering
     * @return \Cake\Http\Response JSON response with filtered award list
     * 
     * @see \Awards\Model\Table\AwardsTable::find() For domain-filtered award retrieval
     * @see \Authorization\Controller\Component\AuthorizationComponent::skipAuthorization() For public access
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
     * Add Activity - Associate a gathering activity with an award
     * 
     * Adds a gathering activity to an award, allowing the award to be given out
     * during that specific type of activity. This creates an entry in the
     * award_gathering_activities join table.
     * 
     * @param string|null $id Award identifier
     * @return \Cake\Http\Response|null Redirects back to award view
     * @throws \Cake\Http\Exception\NotFoundException When award not found
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
     * Remove Activity - Dissociate a gathering activity from an award
     * 
     * Removes a gathering activity from an award, preventing the award from being
     * given out during that type of activity. This deletes the entry from the
     * award_gathering_activities join table.
     * 
     * @param string|null $awardId Award identifier
     * @param string|null $activityId Gathering Activity identifier
     * @return \Cake\Http\Response|null Redirects back to award view or returns turbo frame content
     * @throws \Cake\Http\Exception\NotFoundException When award not found
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
     * Add Activity To Gathering Activity - Associate an award with a gathering activity
     * 
     * Adds an award to a gathering activity, allowing the award to be given out during
     * that specific type of activity. This is the reverse operation of addActivity(),
     * used when managing from the GatheringActivity view.
     * 
     * @param string|null $activityId Gathering Activity identifier
     * @return \Cake\Http\Response|null Redirects back to gathering activity view or returns turbo frame content
     * @throws \Cake\Http\Exception\NotFoundException When activity not found
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
     * Build Turbo Stream Response
     * 
     * Creates a Turbo Stream response that updates a frame and appends flash messages.
     * This allows Turbo Frame responses to display flash messages without a full page reload.
     * 
     * @param string $frameContent The HTML content to update the frame with
     * @param array|null $flashMessages Flash messages from session
     * @return string Turbo Stream HTML
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