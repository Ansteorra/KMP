<?php

declare(strict_types=1);

namespace App\View\Cell;

use App\KMP\StaticHelpers;
use Cake\View\Cell;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Log\Log;

/**
 * Navigation cell
 */
class BasePluginCell extends Cell
{
    const PLUGIN_TYPE_TAB = 'tab';
    const PLUGIN_TYPE_DETAIL = 'detail';
    const PLUGIN_TYPE_MODAL = 'modal';
    const PLUGIN_TYPE_JSON = 'json';

    static public function getRouteEventResponse($route, $pluginData, $validRoutes)
    {
        $testRoute = ['controller' => $route['controller'], 'action' => $route['action']];
        if (isset($route['plugin'])) {
            $testRoute['plugin'] = $route['plugin'];
        } else {
            $testRoute['plugin'] = null;
        }
        if (in_array($testRoute, $validRoutes)) {
            return $pluginData;
        }
        return null;
    }
}