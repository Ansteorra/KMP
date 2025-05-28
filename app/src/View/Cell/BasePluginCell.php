<?php

declare(strict_types=1);

namespace App\View\Cell;

use Cake\View\Cell;

/**
 * BasePluginCell - Base class for plugin view cells
 * 
 * @deprecated This class is part of the event-based view cell system being replaced by ViewCellRegistry.
 * New view cells should still extend this class for compatibility, but the static configuration
 * pattern ($validRoutes, $pluginData) is being replaced by ViewCellProvider classes.
 * 
 * The getViewConfigForRoute() method should continue to work as before.
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
