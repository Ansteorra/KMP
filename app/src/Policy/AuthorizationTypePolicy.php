<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\AuthorizationType;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class AuthorizationTypePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = 'Can Manage Authorization Types';
}