<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * WorkflowDefinitionsTable policy — restricts workflow admin actions to super users.
 */
class WorkflowDefinitionsTablePolicy extends BasePolicy
{
    /**
     * Authorize workflow list access.
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow designer access.
     */
    public function canDesigner(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow registry access.
     */
    public function canRegistry(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow draft saves.
     */
    public function canSave(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow metadata updates.
     */
    public function canUpdateMetadata(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow publishing.
     */
    public function canPublish(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow creation.
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow instance access.
     */
    public function canInstances(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow instance detail access.
     */
    public function canViewInstance(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow version access.
     */
    public function canVersions(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow version comparison.
     */
    public function canCompareVersions(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow active-state changes.
     */
    public function canToggleActive(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow archiving.
     */
    public function canArchive(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow deletion.
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize draft creation.
     */
    public function canCreateDraft(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Authorize workflow instance migrations.
     */
    public function canMigrateInstances(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }
}
