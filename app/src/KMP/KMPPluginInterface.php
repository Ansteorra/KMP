<?php

declare(strict_types=1);

namespace App\KMP;

/**
 * Plugin architecture contract for KMP plugins.
 *
 * All KMP plugins must implement this interface to integrate with the system.
 * Provides migration ordering for proper initialization sequence.
 *
 * @see /docs/5-plugins.md For plugin development documentation
 */
interface KMPPluginInterface
{
    /**
     * Get migration order for plugin initialization.
     *
     * Lower numbers initialize first. Critical plugins: 1-5, Utility plugins: 10+.
     *
     * @return int Migration order (lower = earlier initialization)
     */
    public function getMigrationOrder(): int;
}
