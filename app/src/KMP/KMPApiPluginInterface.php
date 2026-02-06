<?php

declare(strict_types=1);

namespace App\KMP;

use Cake\Routing\RouteBuilder;

/**
 * Contract for plugins that publish API routes.
 *
 * Implementors register their API endpoints under the host API scope.
 */
interface KMPApiPluginInterface
{
    /**
     * Register plugin API routes.
     *
     * @param \Cake\Routing\RouteBuilder $builder API route scope builder.
     * @return void
     */
    public function registerApiRoutes(RouteBuilder $builder): void;
}

