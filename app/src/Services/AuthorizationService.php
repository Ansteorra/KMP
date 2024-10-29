<?php

declare(strict_types=1);

namespace App\Services;

use Authorization\AuthorizationService as rootAuthorizationService;
use Authorization\Policy\Result;
use Authorization\Policy\ResultInterface;
use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface;

class AuthorizationService extends rootAuthorizationService implements AuthorizationServiceInterface
{
    public function checkCan(?IdentityInterface $user, string $action, $resource, ...$optionalArgs): bool
    {
        $currentAuthCheck = $this->authorizationChecked;
        $result = $this->performCheck($user, $action, $resource, ...$optionalArgs);
        if (!$currentAuthCheck)
            $this->authorizationChecked = false;

        return is_bool($result) ? $result : $result->getStatus();
    }
}