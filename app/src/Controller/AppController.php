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

use App\Model\Table\AppSettingsTable;
use App\View\Cell\BasePluginCell;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\Event\EventManager;

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
        parent::beforeFilter($event);
        //get url params
        $params = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            $this->request->getParam('pass')
        ];
        $event = new Event(static::VIEW_PLUGIN_EVENT, $this, ['url' => $params]);
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