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
use App\Services\ViewCellRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;

class AppController extends Controller
{
    /** @var string Event for plugin view cell registration */
    public const VIEW_PLUGIN_EVENT = 'KMP.plugins.callForViewCells';

    /** @var string Event for plugin view data enhancement */
    public const VIEW_DATA_EVENT = 'KMP.plugins.callForViewData';

    /** @var array View cells from plugins for current request */
    protected array $pluginViewCells = [];

    /** @var bool Whether current request is for CSV export (.csv extension) */
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
     * Pre-action filter for application-wide processing.
     *
     * Handles: CSV detection, plugin validation, navigation history,
     * view cell loading, and Turbo Frame detection.
     *
     * @param EventInterface $event The beforeFilter event
     * @return void
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
                    $this->redirect(['plugin' => null, 'controller' => 'Members', 'action' => 'view', $currentUser->id]);
                } else {
                    $this->redirect(['plugin' => null, 'controller' => 'Members', 'action' => 'login']);
                }
            }
        }

        parent::beforeFilter($event);

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

        $pageStack = $session->read('pageStack', []);
        if ($params['action'] == 'index') {
            $pageStack = [];
        }

        // Exclude AJAX/Turbo/POST requests from history
        $isAjax = $this->request->is('ajax') || $this->request->is('json') || $this->request->is('xml') || $this->request->is('csv');
        $turboRequest = $this->request->getHeader('Turbo-Frame') != null;
        $isAjax = $isAjax || $turboRequest;
        if (!$isNoStack) {
            $isNoStack = $this->request->getQuery('nostack') != null;
        }
        $isPostType = $this->request->is('post') || $this->request->is('put') || $this->request->is('delete');

        // Update page stack
        if (!$isAjax && !$isPostType && !$isNoStack) {
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
                $historyCount++;
            }
        }

        $session->write('pageStack', $pageStack);
        $this->set('pageStack', $pageStack);

        // Load view cells from registry
        $urlParams = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
            'pass' => $this->request->getParam('pass') ?? [],
            'query' => $this->request->getQueryParams(),
        ];

        $currentUser = $this->request->getAttribute('identity');
        $this->pluginViewCells = ViewCellRegistry::getViewCells($urlParams, $currentUser);
        $this->set('pluginViewCells', $this->pluginViewCells);

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

        $recordId = $this->request->getParam('pass');
        if (is_array($recordId) && count($recordId) > 0) {
            $recordId = $recordId[0];
        } elseif (is_array($recordId) && count($recordId) == 0) {
            $recordId = -1;
        } elseif (is_array($recordId)) {
            foreach ($recordId as $key => $value) {
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

        $this->Flash->success(__('Switched to {0} view.', $mode));

        // Redirect based on mode
        if ($mode === 'mobile') {
            // Redirect to mobile card view with user's token
            if ($currentUser && $currentUser->mobile_card_token) {
                return $this->redirect([
                    'controller' => 'Members',
                    'action' => 'viewMobileCard',
                    'plugin' => null,
                    $currentUser->mobile_card_token
                ]);
            } else {
                // Fallback if no mobile card token exists
                $this->Flash->error(__('Mobile card not available. Please contact an administrator.'));
                return $this->redirect(['controller' => 'Members', 'action' => 'index', 'plugin' => null]);
            }
        } else {
            // Redirect to desktop profile view
            return $this->redirect([
                'controller' => 'Members',
                'action' => 'profile',
                'plugin' => null
            ]);
        }
    }
}
