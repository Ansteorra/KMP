<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;

/**
 * Authorization policy for bestowal to-do templates.
 *
 * Concrete can* methods are inherited from BasePolicy and resolve against the
 * permission_policies mapping seeded by the template permission migration.
 */
class BestowalTodoTemplatePolicy extends BasePolicy
{
    /**
     * Authorize synchronizing outdated open bestowals assigned to this template.
     */
    public function canSyncOpenBestowals(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }
}
