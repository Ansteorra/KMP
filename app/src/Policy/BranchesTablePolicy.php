<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\BranchesTable;
use Authorization\IdentityInterface;

/**
 * AuthorizationGroupsTable policy
 */
class BranchesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = 'Can Manage Branches';
}

