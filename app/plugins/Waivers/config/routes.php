<?php

/**
 * Routes configuration for the Waivers plugin.
 *
 * This file configures the routes for the Waivers plugin.
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

$routes->plugin(
    'Waivers',
    ['path' => '/waivers'],
    function (RouteBuilder $builder) {
        $builder->setRouteClass(DashedRoute::class);

        // Default routes for the plugin
        $builder->fallbacks();
    }
);
