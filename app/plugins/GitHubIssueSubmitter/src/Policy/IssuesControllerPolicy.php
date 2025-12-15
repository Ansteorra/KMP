<?php

declare(strict_types=1);

namespace GitHubIssueSubmitter\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;

/**
 * Issues Controller Authorization Policy
 *
 * Governs access to the Issues controller, implementing a permissive authorization
 * model for anonymous feedback submission. Security is maintained through input
 * validation, API tokens, and infrastructure-level protections.
 *
 * @package GitHubIssueSubmitter\Policy
 * @see /docs/5.4-github-issue-submitter-plugin.md
 */
class IssuesControllerPolicy extends \App\Policy\BasePolicy
{
    /**
     * Authorize anonymous feedback submission access
     *
     * Returns true for all requests to enable anonymous feedback submission.
     * Security is maintained through complementary mechanisms: input validation,
     * GitHub API authentication, and infrastructure-level rate limiting.
     *
     * @param \App\KMP\KmpIdentityInterface|null $user User identity (null for anonymous users)
     * @param mixed $resource Request resource or context information
     * @return \Authorization\Policy\ResultInterface|bool Always returns true
     */
    public function canSubmit(
        KmpIdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return true;
    }
}
