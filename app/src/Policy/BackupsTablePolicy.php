<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * BackupsTable policy — controls tenant backup self-service operations.
 *
 * Granted through the "Can Manage Backups" permission; super users pass
 * via BasePolicy::before(). Restore and scheduling are platform-admin
 * responsibilities and intentionally have no tenant policy methods.
 */
class BackupsTablePolicy extends BasePolicy
{
    /**
     * Can the user request an on-demand managed backup?
     */
    public function canCreate(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * Can the user download a managed backup archive?
     */
    public function canDownload(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * Can the user export a backup's one-time recovery key?
     */
    public function canDownloadRecoveryKey(
        KmpIdentityInterface $user,
        BaseEntity|Table $entity,
        ...$optionalArgs,
    ): bool {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * Can the user download a legacy self-service .kmpbackup file?
     */
    public function canLegacyDownload(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }
}
