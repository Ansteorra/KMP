<?php

namespace GitHubIssueSubmitter\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;

class IssuesControllerPolicy extends \App\Policy\BasePolicy
{

    public function canSubmit(
        KmpIdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return true;
    }
}
