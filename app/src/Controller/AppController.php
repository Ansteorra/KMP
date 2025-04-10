<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Model\Table\AppSettingsTable;
use App\View\Cell\BasePluginCell;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\Event\EventManager;
use Cake\Core\Configure;
use App\KMP\PermissionsLoader;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    const VIEW_PLUGIN_EVENT = 'KMP.plugins.callForViewCells';
    const VIEW_DATA_EVENT = 'KMP.plugins.callForViewData';
    protected array $pluginViewCells = [];
    //use Cake\Event\EventInterface;
    public function beforeFilter(EventInterface $event)
    {
        $this->request->addDetector(
            'csv',
            function ($request) {
                //check the url for .csv
                return strpos($request->getRequestTarget(), '.csv') !== false;
            }
        );


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

        //get url params
        $params = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            $this->request->getParam('pass')
        ];

        $baseSub = Configure::read('App.base');
        $currentUrl = $this->request->getRequestTarget();
        if ($baseSub != null) {
            $currentUrl = $baseSub . $currentUrl;
        }
        $this->set('currentUrl', $currentUrl);
        $session = $this->getRequest()->getSession();
        $isNoStack = false;
        if ($params['controller'] == 'Members') {
            if ($params['action'] == 'logout') {
                $isNoStack = true;
                $session->destroy();
            }
            if ($params['action'] == 'login') {
                $isNoStack = true;
                $config = $this->getRequest()->getFlash()->getConfig();
                //get the flash from the session
                $flash = $session->read('Flash.' . $config['key']);
                $session->destroy();
                //save the flash back to the session
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

        //check if the call is Ajax
        $isAjax = $this->request->is('ajax') || $this->request->is('json') || $this->request->is('xml') || $this->request->is('csv');
        $turboRequest = $this->request->getHeader('Turbo-Frame') != null;
        $isAjax = $isAjax || $turboRequest;
        if (!$isNoStack) {
            $isNoStack = $this->request->getQuery('nostack') != null;
        }
        $isPostType = $this->request->is('post') || $this->request->is('put') || $this->request->is('delete');
        //if the method is a post skip the history
        if (!$isAjax && !$isPostType && !$isNoStack) {
            if (empty($pageStack)) {
                $pageStack[] = $currentUrl;
            }
            $historyCount = count($pageStack);
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

        $event = new Event(static::VIEW_PLUGIN_EVENT, $this, ['url' => $params, "currentUser" => $this->request->getAttribute("identity")]);
        EventManager::instance()->dispatch($event);
        if ($event->getResult()) {
            $this->pluginViewCells = $this->organizeViewCells($event->getResult());
        } else {
            $this->pluginViewCells = [];
        }
        $this->set('pluginViewCells', $this->pluginViewCells);

        //check the header for a turbo-frame request
        if ($this->request->getHeader('Turbo-Frame')) {
            $this->viewBuilder()->setLayout('turbo_frame');
            $this->set("isTurboFrame", true);
            $this->set("turboFrameId", $this->request->getHeader('Turbo-Frame')[0]);
        } else {
            $this->set("isTurboFrame", false);
        }
        $this->set("user", $this->request->getAttribute("identity"));
        $recordId = $this->request->getParam('pass');
        if (is_array($recordId) && count($recordId) > 0) {
            $recordId = $recordId[0];
        } elseif (is_array($recordId) && count($recordId) == 0) {
            $recordId = -1;
        } elseif (is_array($recordId)) {
            foreach ($recordId as $key => $value) {
                $recordId .= $value . ", ";
            }
        }
        $this->set("recordId", $recordId);
        $recordModel = $params["controller"];
        if ($params["plugin"] != null) {
            $recordModel = $params["plugin"] . "." . $recordModel;
        }
        $this->set("recordModel", $recordModel);
        $event = new Event(static::VIEW_DATA_EVENT, $this, ['url' => $params]);
        EventManager::instance()->dispatch($event);
    }

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('Authorization.Authorization');
        $this->loadComponent('Flash');

        // $this->appSettings = ServiceProvider::getContainer()->get(AppSettingsService::class);

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        // $this->loadComponent('FormProtection');
    }

    protected function organizeViewCells($viewCells)
    {
        $cells = [];
        foreach ($viewCells as $cell) {
            $cells[$cell['type']][$cell['order']] = $cell;
        }
        //loop through the cell keys and sort them
        foreach ($cells as $key => $value) {
            ksort($cells[$key]);
        }
        return $cells;
    }
}