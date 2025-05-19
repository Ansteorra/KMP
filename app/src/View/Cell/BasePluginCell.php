<?php
declare(strict_types=1);

namespace App\View\Cell;

use Cake\View\Cell;

/**
 * Navigation cell
 */
class BasePluginCell extends Cell
{
    public const PLUGIN_TYPE_TAB = 'tab';
    public const PLUGIN_TYPE_DETAIL = 'detail';
    public const PLUGIN_TYPE_MODAL = 'modal';
    public const PLUGIN_TYPE_JSON = 'json';

    public static function getRouteEventResponse($route, $pluginData, $validRoutes)
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
