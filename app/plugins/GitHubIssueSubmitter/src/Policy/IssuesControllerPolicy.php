<?php

namespace GitHubIssueSubmitter\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;

class IssuesControllerPolicy extends \App\Policy\BasePolicy
{

    public function canSubmit(
        IdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return true;
    }
}