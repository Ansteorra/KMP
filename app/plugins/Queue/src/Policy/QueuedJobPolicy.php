<?php

namespace Queue\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;

/**
 * QueuedJob policy
 */
class QueuedJobPolicy extends BasePolicy
{

    /**
     * Check if $user can add a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAddJob(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Check if $user reset a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canResetJob(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can remove a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canRemoveJob(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can processes a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canProcesses(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Check if $user can reset a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canReset(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can flush a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canFlush(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can hard reset a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canHardReset(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view stats
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canStats(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view classes
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewClasses(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can import jobs
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canImport(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view data
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canData(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can execute a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canExecute(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can test a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canTest(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can run migrations
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canMigrate(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can  kill a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canTerminate(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can cleanup a job
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canCleanup(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
