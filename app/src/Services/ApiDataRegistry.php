<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Registry for API response data providers.
 *
 * Plugins register callbacks that inject additional data into API detail
 * responses. Follows the same registration pattern as ViewCellRegistry
 * but for REST API endpoints instead of view cells.
 */
class ApiDataRegistry
{
    /** @var array<string, array{callback: callable, routes: array}> */
    private static array $providers = [];

    /** @var bool */
    private static bool $initialized = false;

    /**
     * Register an API data provider.
     *
     * @param string $source Source identifier (e.g., 'Officers', 'Awards')
     * @param callable $callback fn(string $controller, string $action, mixed $entity): array
     * @param array $routes List of [controller, action] pairs this provider applies to
     * @return void
     */
    public static function register(string $source, callable $callback, array $routes): void
    {
        self::$providers[$source] = [
            'callback' => $callback,
            'routes' => $routes,
        ];
    }

    /**
     * Collect additional data from all matching providers.
     *
     * @param string $controller Controller name (e.g., 'Branches')
     * @param string $action Action name (e.g., 'view')
     * @param mixed $entity The primary entity being returned
     * @return array<string, mixed> Merged provider data keyed by provider-chosen keys
     */
    public static function collect(string $controller, string $action, mixed $entity): array
    {
        self::ensureInitialized();

        $data = [];
        foreach (self::$providers as $source => $registration) {
            if (!self::matchesRoute($registration['routes'], $controller, $action)) {
                continue;
            }
            $result = call_user_func($registration['callback'], $controller, $action, $entity);
            if (is_array($result)) {
                $data = array_merge($data, $result);
            }
        }

        return $data;
    }

    /**
     * @param array $routes Registered route patterns
     * @param string $controller Current controller
     * @param string $action Current action
     * @return bool
     */
    private static function matchesRoute(array $routes, string $controller, string $action): bool
    {
        foreach ($routes as $route) {
            if (($route['controller'] ?? null) === $controller
                && ($route['action'] ?? null) === $action) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove a registered provider.
     */
    public static function unregister(string $source): void
    {
        unset(self::$providers[$source]);
    }

    /**
     * Get registered source names.
     *
     * @return string[]
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();
        return array_keys(self::$providers);
    }

    /**
     * Clear all providers (for testing).
     */
    public static function clear(): void
    {
        self::$providers = [];
        self::$initialized = false;
    }

    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
    }
}
