<?php

declare(strict_types=1);

namespace App\Services;

use App\KMP\KmpIdentityInterface;
use Authorization\AuthorizationService as rootAuthorizationService;
use Authorization\AuthorizationServiceInterface;

/**
 * KMP Authorization Service
 * 
 * Custom authorization service that extends CakePHP's base authorization service with
 * KMP-specific identity handling and permission checking patterns. Provides enhanced
 * authorization checking capabilities while maintaining compatibility with the
 * framework's authorization system.
 * 
 * ## Key Enhancements
 * 
 * - **KMP Identity Integration**: Works with KmpIdentityInterface for enhanced user data
 * - **State Management**: Properly manages authorization check state for nested operations
 * - **Permission Checking**: Streamlined can/cannot checking with proper result handling
 * - **Policy Integration**: Seamless integration with KMP's policy-based authorization
 * 
 * ## Authorization Flow
 * 
 * 1. **Identity Resolution**: Ensures user implements KmpIdentityInterface
 * 2. **Action/Resource Matching**: Maps actions to appropriate policy methods
 * 3. **Policy Execution**: Delegates to registered policy classes
 * 4. **Result Processing**: Converts policy results to boolean outcomes
 * 5. **State Preservation**: Maintains check state for proper framework integration
 * 
 * ## Usage Patterns
 * 
 * ### Controller Authorization
 * ```php
 * // In controllers using AuthorizationComponent
 * $this->Authorization->authorize($entity, 'edit');
 * 
 * // Direct service usage
 * if ($authService->checkCan($user, 'view', $member)) {
 *     // User can view this member
 * }
 * ```
 * 
 * ### Policy Integration
 * ```php
 * // Policy methods receive the identity and resource
 * public function canEdit(KmpIdentityInterface $user, Member $member): bool
 * {
 *     return $user->getId() === $member->id || $user->hasRole('Admin');
 * }
 * ```
 * 
 * ## Security Considerations
 * 
 * - All authorization checks must go through registered policies
 * - Identity must be properly authenticated before authorization
 * - Failed authorization should be logged for security monitoring
 * - State management prevents authorization bypass through nested calls
 * 
 * @see \App\KMP\KmpIdentityInterface Enhanced identity interface
 * @see \App\Policy\BasePolicy Base policy class for KMP authorization rules
 * @see \Authorization\AuthorizationService Base CakePHP authorization service
 */
class AuthorizationService extends rootAuthorizationService implements AuthorizationServiceInterface
{
    /**
     * Enhanced authorization check with KMP identity support
     * 
     * Performs authorization checking while properly managing the authorization state
     * to prevent bypass scenarios in nested authorization calls. This method ensures
     * that the authorization check state is preserved correctly even when policies
     * perform their own authorization checks.
     * 
     * The method handles both boolean and result object returns from policies,
     * providing a consistent boolean interface for authorization decisions.
     * 
     * @param KmpIdentityInterface|null $user The authenticated user (null for anonymous)
     * @param string $action The action being attempted (e.g., 'view', 'edit', 'delete')
     * @param mixed $resource The resource being accessed (entity, class name, etc.)
     * @param mixed ...$optionalArgs Additional arguments passed to policy methods
     * 
     * @return bool True if authorization granted, false if denied
     * 
     * @example
     * ```php
     * // Check if user can edit a specific member
     * if ($authService->checkCan($currentUser, 'edit', $member)) {
     *     // Allow edit operation
     * } else {
     *     // Deny access - redirect or show error
     * }
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

        return is_bool($result) ? $result : $result->getStatus();
    }
}
