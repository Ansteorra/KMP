<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;

/**
 * GatheringWaiver Entity Authorization Policy
 *
 * Provides entity-level authorization for gathering waiver operations.
 * Inherits standard CRUD operations from BasePolicy and adds waiver-specific
 * authorization methods.
 *
 * @see /docs/5.7-waivers-plugin.md
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringWaiverPolicy extends BasePolicy
{
    public function canChangeWaiverType(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    public function canViewGatheringWaivers(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canNeedingWaivers(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canUploadWaivers(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canCloseWaivers(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can decline a waiver.
     * Business rules (30-day limit, not already declined) are checked in the controller.
     */
    public function canDecline(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
