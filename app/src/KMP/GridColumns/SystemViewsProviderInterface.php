<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Contract for GridColumns classes that provide dv_grid system views.
 */
interface SystemViewsProviderInterface
{
    /**
     * Return system views for a grid.
     *
     * @param array<string, mixed> $options Runtime context (timezone, scope, etc.)
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array;
}
