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
    const VIEW_CALL_EVENT = 'KMP.plugins.callForViewCells';
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
        $event = new Event(static::VIEW_CALL_EVENT, $this, ['url' => $params]);
        EventManager::instance()->dispatch($event);
        if ($event->getResult()) {
            $this->pluginViewCells = $this->organizeViewCells($event->getResult());
        } else {
            $this->pluginViewCells = [];
        }
        $this->set('pluginViewCells', $this->pluginViewCells);
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