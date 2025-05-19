<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\KmpIdentityInterface;
use Authorization\AuthorizationService as rootAuthorizationService;
use Authorization\AuthorizationServiceInterface;

class AuthorizationService extends rootAuthorizationService implements AuthorizationServiceInterface
{
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
