<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * EmailTemplate Policy
 * 
 * Controls access to email template management functionality
 */
class EmailTemplatePolicy extends BasePolicy
{
    /**
     * Check if $user can index EmailTemplates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Only allow users with email template management permission
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can view EmailTemplate
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can create EmailTemplate
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canCreate(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can edit EmailTemplate
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canUpdate(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can edit EmailTemplate (alias for update)
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canUpdate($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can delete EmailTemplate
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
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
     * @param \Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDiscover(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Same permission as viewing templates
        return $this->_hasPolicy($user, 'canView', $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can sync templates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canSync(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Sync requires create permission
        return $this->_hasPolicy($user, 'canCreate', $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can preview templates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canPreview(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Same permission as viewing templates
        return $this->_hasPolicy($user, 'canView', $entity, ...$optionalArgs);
    }
}