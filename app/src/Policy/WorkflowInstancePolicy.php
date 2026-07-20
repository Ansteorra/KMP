<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;

/**
 * WorkflowInstance entity-level policy.
 *
 * Super user bypass and standard permission checks are handled by BasePolicy.
 */
class WorkflowInstancePolicy extends BasePolicy
{
    /**
     * Alias for the instances controller action — delegates to canIndex.
     */
    public function canInstances(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        return $this->canIndex($user, $entity, ...$args);
    }

    /**
     * Alias for the viewInstance controller action — delegates to canView.
     */
    public function canViewInstance(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        return $this->canView($user, $entity, ...$args);
    }
}
