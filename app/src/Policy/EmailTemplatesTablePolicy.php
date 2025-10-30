<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * EmailTemplatesTable Policy
 * 
 * Controls access to email template table-level operations
 */
class EmailTemplatesTablePolicy extends BasePolicy
{
    /**
     * Check if $user can index EmailTemplates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Allow users with email template management permission
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can add EmailTemplates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can edit EmailTemplates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can delete EmailTemplates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can discover mailers
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDiscover(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Same permission as index
        return $this->_hasPolicy($user, 'canIndex', $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can sync templates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canSync(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Sync requires add permission
        return $this->_hasPolicy($user, 'canAdd', $entity, ...$optionalArgs);
    }
}
