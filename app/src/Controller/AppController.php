<?php
declare(strict_types=1);

/**
 * Base controller for KMP application.
 *
 * Provides shared functionality: request detection, navigation history,
 * plugin validation, view cell management, and Turbo/AJAX handling.
 *
 * @property \Authentication\Controller\Component\AuthenticationComponent $Authentication
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 * @see \App\Services\ViewCellRegistry For view cell management
 */

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use App\Services\ImpersonationService;
use App\Services\RestoreStatusService;
use App\Services\ViewCellRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Http\Response;

class AppController extends Controller
{
    use TurboResponseTrait;

    /** @var string Event for plugin view cell registration */
    public const VIEW_PLUGIN_EVENT = 'KMP.plugins.callForViewCells';

    /** @var string Event for plugin view data enhancement */
    public const VIEW_DATA_EVENT = 'KMP.plugins.callForViewData';

    /** @var int Shared default page size for mobile actionable queues. */
    public const MOBILE_QUEUE_DEFAULT_PER_PAGE = 25;

    /** @var int Shared maximum page size for mobile actionable queues. */
    public const MOBILE_QUEUE_MAX_PER_PAGE = 50;

    /**
     * @var array View cells from plugins for current request
     */
    protected array $pluginViewCells = [];

    /**
     * @var bool Whether current request is for CSV export (.csv extension)
     */
    protected bool $isCsvRequest = false;

    /**
     * Check if current request is for CSV export.
     *
     * @return bool
     */
    public function isCsvRequest(): bool
    {
        return $this->isCsvRequest;
    }

    /**
     * Build shared pagination values for mobile actionable queues.
     *
     * @param int $total Total actionable records
     * @return array<string, int|bool>
     */
    protected function mobileQueuePagination(int $total): array
    {
        $page = max(1, (int)$this->request->getQuery('page', 1));
        $perPage = min(
            self::MOBILE_QUEUE_MAX_PER_PAGE,
            max(1, (int)$this->request->getQuery('per_page', self::MOBILE_QUEUE_DEFAULT_PER_PAGE)),
        );
        $pageCount = (int)max(1, ceil($total / $perPage));
        $page = min($page, $pageCount);

        return [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pageCount' => $pageCount,
            'hasNextPage' => $page < $pageCount,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * Remove internal pagination values before sending the mobile JSON payload.
     *
     * @param array<string, int|bool> $pagination Pagination data
     * @return array<string, int|bool>
     */
    protected function mobileQueuePaginationPayload(array $pagination): array
    {
        unset($pagination['offset']);

        return $pagination;
    }

    /**
     * Pre-action filter for application-wide processing.
     *
     * Handles: CSV detection, plugin validation, navigation history,
     * view cell loading, and Turbo Frame detection.
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
        // Register CSV request detector
        $this->request->addDetector(
            'csv',
            function ($request) {
                return strpos($request->getRequestTarget(), '.csv') !== false;
            },
        );

        $this->isCsvRequest = $this->request->is('csv');
        $this->set('isCsvRequest', $this->isCsvRequest);

        // Validate plugin is enabled
        $plugin = $this->request->getParam('plugin');
        if ($plugin != null) {
            if (StaticHelpers::pluginEnabled($plugin) == false) {
                $this->Flash->error("The plugin $plugin is not enabled.");
                $currentUser = $this->request->getAttribute('identity');
                if ($currentUser != null) {
                    $this->redirect([
                        'plugin' => null,
                        'controller' => 'Members',
                        'action' => 'view',
                        $currentUser->id,
                    ]);
                } else {
                    $this->redirect(['plugin' => null, 'controller' => 'Members', 'action' => 'login']);
                }
            }
        }

        parent::beforeFilter($event);

        $lockResponse = $this->enforceRestoreLock($event);
        if ($lockResponse !== null) {
            return $lockResponse;
        }

        // Extract URL parameters
        $params = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
            $this->request->getParam('pass'),
        ];

        // Build current URL with base path
        $baseSub = Configure::read('App.base');
        $currentUrl = $this->request->getRequestTarget();
        if ($baseSub != null) {
            $currentUrl = $baseSub . $currentUrl;
        }
        $this->set('currentUrl', $currentUrl);

        // Navigation history management
        $session = $this->getRequest()->getSession();
        $isNoStack = false;

        // Exclude login/logout from history
        if ($params['controller'] == 'Members') {
            if ($params['action'] == 'logout') {
                $isNoStack = true;
                $session->destroy();
            }
            if ($params['action'] == 'login') {
                $isNoStack = true;
                $config = $this->getRequest()->getFlash()->getConfig();
                $flash = $session->read('Flash.' . $config['key']);
                $session->destroy();
                $session->write('Flash.' . $config['key'], $flash);
            }
        }
        if ($params['controller'] == 'NavBar') {
            $isNoStack = true;
        }
        if ($params['controller'] == 'AppSettings' && $params['action'] == 'asset') {
            $isNoStack = true;
        }
        // The public kingdom calendar is meant to be embedded (iframed) into
        // external pages, so it must not pollute the back-navigation stack.
        if ($params['controller'] == 'Gatherings' && $params['action'] == 'publicCalendar') {
            $isNoStack = true;
        }

        $pageStack = $session->read('pageStack', []);
        if ($params['action'] == 'index') {
            $pageStack = [];
        }

        $isFragmentRequest = $this->isFragmentRequest();
        if (!$isNoStack) {
            $isNoStack = $this->request->getQuery('nostack') != null;
        }
        $isPostType = $this->request->is('post') || $this->request->is('put') || $this->request->is('delete');

        // Update page stack
        if (!$isFragmentRequest && !$isPostType && !$isNoStack) {
            if (empty($pageStack)) {
                $pageStack[] = $currentUrl;
            }
            $historyCount = count($pageStack);

            // Handle back navigation
            if (($historyCount > 1) && ($pageStack[$historyCount - 2] == $currentUrl)) {
                $historyCount--;
                array_pop($pageStack);
            }

            if ($pageStack[$historyCount - 1] != $currentUrl) {
                $pageStack[] = $currentUrl;
            }
        }

        if (!$isFragmentRequest) {
            $session->write('pageStack', $pageStack);
        }
        $this->set('pageStack', $pageStack);

        $impersonationService = new ImpersonationService();
        $impersonationState = $impersonationService->getState($session);

        if ($isFragmentRequest) {
            $session->close();
        }

        // Turbo Frame handling
        if ($this->request->getHeader('Turbo-Frame')) {
            $this->viewBuilder()->setLayout('turbo_frame');
            $this->set('isTurboFrame', true);
            $this->set('turboFrameId', $this->request->getHeader('Turbo-Frame')[0]);
        } else {
            $this->set('isTurboFrame', false);
        }

        // Set view context variables
        $this->set('user', $this->request->getAttribute('identity'));
        $this->set('impersonationState', $impersonationState);

        $recordId = $this->request->getParam('pass');
        if (is_array($recordId) && count($recordId) > 0) {
            $recordId = $recordId[0];
        } elseif (is_array($recordId) && count($recordId) == 0) {
            $recordId = -1;
        } elseif (is_array($recordId)) {
            foreach ($recordId as $value) {
                $recordId .= $value . ', ';
            }
        }
        $this->set('recordId', $recordId);

        $recordModel = $params['controller'];
        if ($params['plugin'] != null) {
            $recordModel = $params['plugin'] . '.' . $recordModel;
        }
        $this->set('recordModel', $recordModel);

        // Dispatch view data event for plugins
        $event = new Event(static::VIEW_DATA_EVENT, $this, ['url' => $params]);
        EventManager::instance()->dispatch($event);
    }

    /**
     * Redirect/block requests while a restore lock is active.
     */
    private function enforceRestoreLock(EventInterface $event): ?Response
    {
        $restoreStatusService = new RestoreStatusService();
        if (!$restoreStatusService->isLocked()) {
            return null;
        }

        $controller = strtolower((string)$this->request->getParam('controller'));
        $action = strtolower((string)$this->request->getParam('action'));
        if ($this->isRestoreLockBypassRoute($controller, $action)) {
            return null;
        }

        $status = $restoreStatusService->getStatus();
        $message = (string)($status['message'] ?? 'A restore is currently running. Please try again shortly.');

        if ($this->request->is('ajax') || $this->request->accepts('application/json') || $this->request->is('json')) {
            $response = $this->response
                ->withType('application/json')
                ->withStatus(423)
                ->withStringBody((string)json_encode([
                    'status' => 'locked',
                    'message' => $message,
                    'restore' => $status,
                ]));
            $event->stopPropagation();
            $event->setResult($response);

            return $response;
        }

        $this->Flash->warning($message);
        $response = $this->redirect(['controller' => 'Backups', 'action' => 'index']);
        $event->stopPropagation();
        $event->setResult($response);

        return $response;
    }

    /**
     * Check if restore lock bypass route.
     *
     * @param string $controller
     * @param string $action
     * @return bool
     */
    private function isRestoreLockBypassRoute(string $controller, string $action): bool
    {
        if ($controller === 'backups' && in_array($action, ['index', 'status', 'restore'], true)) {
            return true;
        }

        if ($controller === 'health' && $action === 'index') {
            return true;
        }

        if ($controller === 'members' && in_array($action, ['login', 'logout'], true)) {
            return true;
        }

        if ($controller === 'sessions' && $action === 'keepalive') {
            return true;
        }

        return false;
    }

    /**
     * Load shared components: Authentication, Authorization, Flash.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('Authorization.Authorization');
        $this->loadComponent('Flash');
    }

    /**
     * Organize view cells by type and display order.
     *
     * @param array $viewCells Flat array of view cell configurations
     * @return array Organized array grouped by type and sorted by order
     * @deprecated Unused - view cells organized in ViewCellRegistry
     */
    protected function organizeViewCells($viewCells)
    {
        $cells = [];
        foreach ($viewCells as $cell) {
            // Group cells by their placement type (sidebar, header, footer, etc.)
            $cells[$cell['type']][$cell['order']] = $cell;
        }

        // Sort each placement type by display order
        foreach ($cells as $key => $value) {
            ksort($cells[$key]);
        }

        return $cells;
    }

    /**
     * Authorize the current URL/action via Authorization component.
     *
     * @return void
     * @throws \Authorization\Exception\ForbiddenException When authorization fails
     */
    protected function authorizeCurrentUrl()
    {
        // Build authorization context from current request parameters
        $params = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
            $this->request->getParam('pass'), // Additional URL parameters
        ];

        // Perform authorization check via Authorization component
        $this->Authorization->authorize($params);
    }

    /**
     * Finalize view-only data after the action has run.
     *
     * Deferring view cells prevents redirect-only requests from executing badge callbacks.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeRender(EventInterface $event)
    {
        parent::beforeRender($event);

        if ($this->viewBuilder()->hasVar('pluginViewCells')) {
            return;
        }

        if ($this->isFragmentRequest()) {
            $this->pluginViewCells = [];
        } else {
            $urlParams = [
                'controller' => $this->request->getParam('controller'),
                'action' => $this->request->getParam('action'),
                'plugin' => $this->request->getParam('plugin'),
                'prefix' => $this->request->getParam('prefix'),
                'pass' => $this->request->getParam('pass') ?? [],
                'query' => $this->request->getQueryParams(),
            ];
            $currentUser = $this->request->getAttribute('identity');
            // ViewCellRegistry expects a Member entity; pass null for non-Member identities (e.g. ServicePrincipal)
            $memberUser = $currentUser instanceof Member ? $currentUser : null;
            $this->pluginViewCells = ViewCellRegistry::getViewCells($urlParams, $memberUser);
        }

        $this->set('pluginViewCells', $this->pluginViewCells);
    }

    /**
     * Switch between mobile and desktop view modes.
     *
     * Stores preference in session and redirects to appropriate interface.
     * Mobile redirects to viewMobileCard, desktop to profile.
     *
     * @return \Cake\Http\Response Redirect response
     */
    public function switchView()
    {
        // Skip authorization check - view switching should be available to all authenticated users
        $this->Authorization->skipAuthorization();

        $mode = $this->request->getQuery('mode', 'desktop');

        // Validate mode parameter
        if (!in_array($mode, ['mobile', 'desktop'])) {
            $mode = 'desktop';
        }

        // Store preference in session
        $session = $this->request->getSession();
        $session->write('viewMode', $mode);

        // Get current user identity
        $currentUser = $this->request->getAttribute('identity');

        if (!$currentUser) {
            $this->Flash->error(__('Please sign in to use {0} view.', $mode));

            return $this->redirect(['controller' => 'Members', 'action' => 'login', 'plugin' => null]);
        }

        if ($mode === 'mobile') {
            $this->Flash->success(__('Switched to {0} view.', $mode));

            return $this->redirect([
                'controller' => 'Members',
                'action' => 'viewMobileCard',
                'plugin' => null,
            ]);
        }

        $this->Flash->success(__('Switched to {0} view.', $mode));

        // Redirect to desktop profile view
        return $this->redirect([
            'controller' => 'Members',
            'action' => 'profile',
            'plugin' => null,
        ]);
    }

    /**
     * Fragment requests render into existing pages and should not build app chrome.
     *
     * @return bool
     */
    private function isFragmentRequest(): bool
    {
        $isAjax = $this->request
            ->is('ajax') || $this->request->is('json') || $this->request->is('xml') || $this->request->is('csv');
        $turboRequest = $this->request->getHeader('Turbo-Frame') != null;
        $acceptHeader = $this->request->getHeaderLine('Accept');
        $turboStreamRequest = str_contains($acceptHeader, 'text/vnd.turbo-stream.html');
        $controllerName = (string)$this->request->getParam('controller');
        $actionName = (string)$this->request->getParam('action');
        $gridDataRequest = str_ends_with($actionName, 'GridData') || str_ends_with($actionName, 'gridData');
        $assetRequest = $controllerName === 'AppSettings' && $actionName === 'asset';

        return $isAjax || $turboRequest || $turboStreamRequest || $gridDataRequest || $assetRequest;
    }
}
