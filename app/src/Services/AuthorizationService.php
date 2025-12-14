<?php

declare(strict_types=1);

namespace App\Services;

use App\KMP\KmpIdentityInterface;
use Authorization\AuthorizationService as rootAuthorizationService;
use Authorization\AuthorizationServiceInterface;

/**
 * Custom authorization service with KMP identity handling.
 *
 * Extends CakePHP's authorization service with proper state management for nested
 * authorization calls and consistent boolean returns from policy checks.
 *
 * @see \App\KMP\KmpIdentityInterface Enhanced identity interface
 * @see \App\Policy\BasePolicy Base policy class
 */
class AuthorizationService extends rootAuthorizationService implements AuthorizationServiceInterface
{
    /**
     * @var array Debug log of authorization checks
     */
    protected static array $authCheckLog = [];

    /**
     * Check authorization with proper state management for nested calls.
     *
     * @param KmpIdentityInterface|null $user The authenticated user
     * @param string $action The action being attempted
     * @param mixed $resource The resource being accessed
     * @param mixed ...$optionalArgs Additional policy arguments
     * @return bool
     * 
     * // Check class-level permissions  
     * if ($authService->checkCan($currentUser, 'add', 'Members')) {
     *     // User can add new members
     * }
     * 
     * // Check with additional context
     * if ($authService->checkCan($currentUser, 'approve', $warrant, $branch)) {
     *     // User can approve warrant in this branch context
     * }
     * ```
     * 
     * @throws \Authorization\Exception\Exception If policy not found or other authorization errors
     */
    public function checkCan(?KmpIdentityInterface $user, string $action, $resource, ...$optionalArgs): bool
    {
        $currentAuthCheck = $this->authorizationChecked;
        $result = $this->performCheck($user, $action, $resource, ...$optionalArgs);
        if (!$currentAuthCheck) {
            $this->authorizationChecked = false;
        }

        $resultBool = is_bool($result) ? $result : $result->getStatus();

        // Only log in debug mode
        if (\Cake\Core\Configure::read('debug')) {
            $this->logAuthCheck($user, $action, $resource, $resultBool, $optionalArgs);
        }

        return $resultBool;
    }

    /**
     * Log an authorization check for debugging purposes
     * 
     * @param KmpIdentityInterface|null $user The user performing the check
     * @param string $action The action being checked
     * @param mixed $resource The resource being accessed
     * @param bool $result The result of the check
     * @param array $optionalArgs Optional arguments
     * @return void
     */
    protected function logAuthCheck(?KmpIdentityInterface $user, string $action, $resource, bool $result, array $optionalArgs): void
    {
        $resourceInfo = $this->getResourceInfo($resource);

        self::$authCheckLog[] = [
            'timestamp' => microtime(true),
            'user_id' => $user ? $user->getIdentifier() : null,
            'action' => $action,
            'resource' => $resourceInfo,
            'result' => $result,
            'additional_args' => !empty($optionalArgs) ? count($optionalArgs) : 0,
        ];
    }

    /**
     * Get readable resource information for logging
     * 
     * @param mixed $resource The resource
     * @return string
     */
    protected function getResourceInfo($resource): string
    {
        if (is_string($resource)) {
            return $resource;
        }

        if (is_object($resource)) {
            $class = get_class($resource);
            $shortClass = substr($class, strrpos($class, '\\') + 1);

            if (method_exists($resource, 'id') && isset($resource->id)) {
                return $shortClass . ' #' . $resource->id;
            }

            return $shortClass;
        }

        return gettype($resource);
    }

    /**
     * Get the authorization check log (only available in debug mode)
     * 
     * @return array
     */
    public static function getAuthCheckLog(): array
    {
        return self::$authCheckLog;
    }

    /**
     * Clear the authorization check log
     * 
     * @return void
     */
    public static function clearAuthCheckLog(): void
    {
        self::$authCheckLog = [];
    }
}
