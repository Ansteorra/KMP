<?php

declare(strict_types=1);

namespace App\Authenticator;

use Authentication\Authenticator\AbstractAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Identifier\IdentifierInterface;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Mobile Card Token Authenticator
 *
 * Authenticates users via their unique mobile card token passed as a URL parameter.
 * This allows passwordless authentication for mobile PWA access while maintaining
 * security through long, random, single-use tokens.
 *
 * ## Security Considerations
 *
 * - Tokens are 16+ character random strings, cryptographically secure
 * - Tokens are unique per member and stored in database
 * - Tokens can be regenerated if compromised
 * - Limited to specific mobile card routes
 * - Does not provide session persistence (token required on each request)
 *
 * ## Usage
 *
 * Configured in Application::getAuthenticationService():
 * ```php
 * $service->loadAuthenticator('MobileCardToken', [
 *     'tokenParam' => 'token',           // URL parameter name
 *     'fields' => [
 *         'mobile_card_token' => 'token' // Database field => URL param
 *     ]
 * ]);
 * ```
 *
 * URL Example:
 * ```
 * /members/view-mobile-card/abc123def456ghi7
 * ```
 *
 * @see Application::getAuthenticationService()
 */
class MobileCardTokenAuthenticator extends AbstractAuthenticator
{
    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'tokenParam' => 'token',           // URL parameter containing token
        'fields' => [
            'mobile_card_token' => 'token' // Database field mapping
        ],
        'userModel' => 'Members',          // Table to query for users
    ];

    /**
     * Authenticate a user based on mobile card token
     *
     * Checks if the request contains a valid mobile card token and returns
     * the authenticated user identity if found.
     *
     * @param ServerRequestInterface $request The request that contains login information.
     * @return ResultInterface Authentication result
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        // Get token from route parameters
        $params = $request->getAttribute('params', []);
        $token = $params['pass'][0] ?? null;

        if (!$token) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
        }

        // Load Members table
        $membersTable = TableRegistry::getTableLocator()->get($this->_config['userModel']);

        // Find member by token
        $inactiveStatuses = [
            3, // STATUS_DEACTIVATED
            6, // STATUS_UNVERIFIED_MINOR
            7, // STATUS_MINOR_MEMBERSHIP_VERIFIED
        ];

        $member = $membersTable
            ->find()
            ->where([
                'mobile_card_token' => $token,
                'status NOT IN' => $inactiveStatuses
            ])
            ->first();

        if (!$member) {
            return new Result(
                null,
                Result::FAILURE_CREDENTIALS_INVALID,
                ['message' => 'Invalid or expired mobile card token']
            );
        }

        // Return successful authentication
        return new Result($member, Result::SUCCESS);
    }

    /**
     * No credentials are needed for this authenticator
     * Token is extracted from URL parameters
     *
     * @param ServerRequestInterface $request Request object.
     * @return array|null
     */
    public function getCredentials(ServerRequestInterface $request): ?array
    {
        return null;
    }
}
