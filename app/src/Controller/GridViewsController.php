<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridViewConfig;
use App\Services\GridViewService;
use Cake\Event\EventInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;

/**
 * GridViews Controller - AJAX Endpoints for Grid View Management
 *
 * The GridViewsController provides JSON-based REST endpoints for managing saved grid views.
 * All actions return JSON responses and are designed to be called via AJAX from the
 * grid-view Stimulus controller.
 *
 * ## Core Responsibilities
 *
 * ### View Management
 * - **List**: Get all available views for a grid (system + user's own)
 * - **Create**: Create a new user view
 * - **Update**: Modify an existing user view
 * - **Delete**: Remove a user view (soft delete)
 *
 * ### Default Management
 * - **Set Default**: Mark a view as the user's default for a grid
 * - **Clear Default**: Remove default status from a view
 *
 * ### View Resolution
 * - **Get Effective**: Resolve which view should be applied based on priority
 *
 * ## Authorization
 *
 * - All actions require authentication (logged-in user)
 * - Users can only manage their own views
 * - System defaults are read-only for regular users
 * - Admin actions (system defaults) require additional permissions
 *
 * ## Response Format
 *
 * All endpoints return JSON with this structure:
 *
 * ### Success Response
 * ```json
 * {
 *   "success": true,
 *   "data": { ... },
 *   "message": "Optional success message"
 * }
 * ```
 *
 * ### Error Response
 * ```json
 * {
 *   "success": false,
 *   "error": "Error message",
 *   "errors": ["Validation error 1", "Validation error 2"]
 * }
 * ```
 *
 * ## Usage Examples
 *
 * ### Fetch Available Views
 * ```javascript
 * GET /grid-views/list?gridKey=Members.index.main
 * ```
 *
 * ### Create New View
 * ```javascript
 * POST /grid-views/create
 * {
 *   "gridKey": "Members.index.main",
 *   "name": "My Custom View",
 *   "config": {...}
 * }
 * ```
 *
 * ### Update View
 * ```javascript
 * PUT /grid-views/update/123
 * {
 *   "name": "Updated Name",
 *   "config": {...}
 * }
 * ```
 *
 * ### Set as Default
 * ```javascript
 * POST /grid-views/set-default/123
 * {
 *   "gridKey": "Members.index.main"
 * }
 * ```
 *
 * ### Delete View
 * ```javascript
 * DELETE /grid-views/delete/123
 * ```
 */
class GridViewsController extends AppController
{
    /**
     * Grid view service
     *
     * @var \App\Services\GridViewService
     */
    protected GridViewService $gridViewService;

    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->gridViewService = new GridViewService(
            $this->fetchTable('GridViews')
        );

        // Set response type to JSON for all actions
        $this->viewBuilder()->setClassName('Json');

        // Skip authorization for all grid view API endpoints
        // Authorization is handled within the service layer based on member ownership
        $this->Authorization->skipAuthorization();
    }

    /**
     * Before filter callback
     *
     * @param \Cake\Event\EventInterface $event Event
     * @return \Cake\Http\Response|null
     */
    public function beforeFilter(EventInterface $event)
    {
        $result = parent::beforeFilter($event);

        // All actions require authentication
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            throw new ForbiddenException('Authentication required');
        }

        return $result;
    }

    /**
     * List all views available for a grid
     *
     * Returns system defaults and the current user's views for the specified grid.
     *
     * @return void
     */
    public function index(): void
    {
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);

        $gridKey = $this->request->getQuery('gridKey');

        if (!$gridKey) {
            $this->set([
                'success' => false,
                'error' => 'gridKey parameter is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
        }

        $member = $this->Authentication->getIdentity();
        $views = $this->gridViewService->getViewsForGrid($gridKey, $member);

        $this->set([
            'success' => true,
            'data' => [
                'views' => $views,
                'gridKey' => $gridKey,
            ],
        ]);
    }

    /**
     * Get effective view for a grid based on priority resolution
     *
     * @return void
     */
    public function effective(): void
    {
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'error']);

        $gridKey = $this->request->getQuery('gridKey');
        $viewId = $this->request->getQuery('viewId');
        if ($viewId !== null && $viewId !== '') {
            $viewId = (int)$viewId;
        } else {
            $viewId = null;
        }

        if (!$gridKey) {
            $this->set([
                'success' => false,
                'error' => 'gridKey parameter is required',
            ]);
            $this->response = $this->response->withStatus(400);
            return;
        }

        $member = $this->Authentication->getIdentity();
        $view = $this->gridViewService->getEffectiveView($gridKey, $member, $viewId);

        $this->set([
            'success' => true,
            'data' => [
                'view' => $view,
                'gridKey' => $gridKey,
            ],
        ]);
    }

    /**
     * Create a new grid view
     *
     * @return void
     */
    public function add(): void
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message']);

        $data = $this->request->getData();
        $member = $this->Authentication->getIdentity();

        // Validate required fields
        if (empty($data['gridKey']) || empty($data['name']) || empty($data['config'])) {
            $this->set([
                'success' => false,
                'error' => 'gridKey, name, and config are required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(400);
            return;
        }

        // Decode config if it's a JSON string
        $config = $data['config'];
        if (is_string($config)) {
            $config = json_decode($config, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->set([
                    'success' => false,
                    'error' => 'Invalid JSON in config field',
                ]);
                $this->viewBuilder()->setOption('serialize', ['success', 'error']);
                $this->response = $this->response->withStatus(400);
                return;
            }
        }

        // Normalize and validate config
        $config = GridViewConfig::normalize($config);
        $errors = GridViewConfig::validate($config);

        if (!empty($errors)) {
            $this->set([
                'success' => false,
                'error' => 'Invalid configuration',
                'errors' => $errors,
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error', 'errors']);
            $this->response = $this->response->withStatus(400);
            return;
        }

        // Create the view
        $viewData = [
            'grid_key' => $data['gridKey'],
            'name' => $data['name'],
            'config' => json_encode($config),
            'is_default' => !empty($data['isDefault']),
        ];

        $view = $this->gridViewService->createView($viewData, $member);

        if ($view) {
            $this->set([
                'success' => true,
                'data' => ['view' => $view],
                'message' => 'View created successfully',
            ]);
        } else {
            $this->set([
                'success' => false,
                'error' => 'Failed to create view',
                'errors' => $view ? $view->getErrors() : [],
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error', 'errors']);
            $this->response = $this->response->withStatus(400);
        }
    }

    /**
     * Update an existing grid view
     *
     * @param int $id View ID
     * @return void
     */
    public function edit(int $id): void
    {
        $this->request->allowMethod(['put', 'post']);

        $data = $this->request->getData();
        $member = $this->Authentication->getIdentity();

        // If config is provided, normalize and validate it
        if (isset($data['config'])) {
            $config = $data['config'];

            // Decode if it's a JSON string
            if (is_string($config)) {
                $config = json_decode($config, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->set([
                        'success' => false,
                        'error' => 'Invalid JSON in config field',
                    ]);
                    $this->viewBuilder()->setOption('serialize', ['success', 'error']);
                    $this->response = $this->response->withStatus(400);
                    return;
                }
            }

            // Normalize and validate
            $config = GridViewConfig::normalize($config);
            $errors = GridViewConfig::validate($config);

            if (!empty($errors)) {
                $this->set([
                    'success' => false,
                    'error' => 'Invalid configuration',
                    'errors' => $errors,
                ]);
                $this->viewBuilder()->setOption('serialize', ['success', 'error', 'errors']);
                $this->response = $this->response->withStatus(400);
                return;
            }

            $data['config'] = json_encode($config);
        }

        $view = $this->gridViewService->updateView($id, $data, $member);

        if ($view) {
            $this->set([
                'success' => true,
                'data' => ['view' => $view],
                'message' => 'View updated successfully',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message']);
        } else {
            $this->set([
                'success' => false,
                'error' => 'Failed to update view or view not found',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(404);
        }
    }

    /**
     * Delete a grid view
     *
     * @param int $id View ID
     * @return void
     */
    public function delete(int $id): void
    {
        $this->request->allowMethod(['post', 'delete']);

        $gridKey = $this->request->getData('gridKey');
        if (!$gridKey) {
            $this->set([
                'success' => false,
                'error' => 'gridKey is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(400);
            return;
        }

        $member = $this->Authentication->getIdentity();
        $success = $this->gridViewService->deleteView($id, $member);

        if ($success) {
            $this->set([
                'success' => true,
                'message' => 'View deleted successfully',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        } else {
            $this->set([
                'success' => false,
                'error' => 'Failed to delete view or view not found',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(404);
        }
    }

    /**
     * Set a view as the user's default for a grid (supports user or system views)
     *
     * @param int|string|null $id View identifier from the route (optional)
     * @return void
     */
    public function setDefault($id = null): void
    {
        $this->request->allowMethod(['post']);

        $gridKey = $this->request->getData('gridKey');
        if (!$gridKey) {
            $this->set([
                'success' => false,
                'error' => 'gridKey is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(400);
            return;
        }

        $member = $this->Authentication->getIdentity();
        $viewIdOrKey = $this->request->getData('viewIdOrKey');
        if ($viewIdOrKey === null && $id !== null) {
            $viewIdOrKey = $id;
        }

        if ($viewIdOrKey === null || $viewIdOrKey === '') {
            $this->set([
                'success' => false,
                'error' => 'view identifier is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(400);
            return;
        }

        $success = $this->gridViewService->setUserDefault($viewIdOrKey, $member->id, $gridKey);

        if ($success) {
            $this->set([
                'success' => true,
                'message' => 'Default view set successfully',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        } else {
            $this->set([
                'success' => false,
                'error' => 'Failed to set default view',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(404);
        }
    }

    /**
     * Clear the user's default for a grid
     *
     * @return void
     */
    public function clearDefault(): void
    {
        $this->request->allowMethod(['post']);

        $gridKey = $this->request->getData('gridKey');
        $member = $this->Authentication->getIdentity();

        if (!$gridKey) {
            $this->set([
                'success' => false,
                'error' => 'gridKey is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(400);
            return;
        }

        $success = $this->gridViewService->clearUserDefault($member->id, $gridKey);

        if ($success) {
            $this->set([
                'success' => true,
                'message' => 'Default view cleared successfully',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        } else {
            $this->set([
                'success' => false,
                'error' => 'Failed to clear default view',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            $this->response = $this->response->withStatus(400);
        }
    }
}
