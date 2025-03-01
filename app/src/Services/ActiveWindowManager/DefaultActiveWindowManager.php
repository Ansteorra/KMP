<?php

namespace App\Services\ActiveWindowManager;

use App\Model\Entity\ActiveWindowBaseEntity;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;

class DefaultActiveWindowManager implements ActiveWindowManagerInterface
{

    public function __construct() {}

    /**
     * Starts an active window for an entity - Make sure to create a transaction before calling this service
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $memberId
     * @param DateTime $startOn
     * @param DateTime|null $expiresOn
     * @param int|null $termYears
     * @param int|null $grantRoleId
     * @return bool
     */
    public function start(
        string $entityType,
        int $entityId,
        int $memberId,
        DateTime $startOn,
        ?DateTime $expiresOn = null,
        ?int $termYears = null,
        ?int $grantRoleId = null,
        bool $closeExisting = true,
        ?int $branchId = null,

    ): ServiceResult {
        $entityTable = TableRegistry::getTableLocator()->get($entityType);
        $entity = $entityTable->get($entityId);

        //stop all like entities for the member_id of the entity
        if ($closeExisting) {
            $peQuery = $entityTable->find('all')
                ->where([
                    'id !=' => $entity->id,
                    'OR' => ['expires_on >=' => DateTime::now(), 'expires_on IS' => null]
                ]);
            if (!empty($entity->typeIdField)) {
                foreach ($entity->typeIdField as $field) {
                    $peQuery->andWhere([$field => $entity[$field]]);
                }
            }
            if ($entityType == "MemberRoles") {
                $peQuery->andWhere(['entity_type' => "Direct Grant"]);
            }
            $previousEntities = $peQuery->all();
            foreach ($previousEntities as $pe) {
                if (!$this->stop($entityType, $pe->id, $memberId, ActiveWindowBaseEntity::REPLACED_STATUS, "", $startOn)) {
                    return new ServiceResult(false, "Failed to expire current $entityType");
                }
            }
        }
        $entity->start($startOn, $expiresOn, $termYears);
        if (!$entityTable->save($entity)) {
            return new ServiceResult(false, "Failed to save $entityType");
        }
        // add the member_role if the activity has a grants_role_id
        if ($grantRoleId != null) {
            $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
            $memberRole = $memberRoleTable->newEmptyEntity();
            $memberRole->member_id = $entity->member_id;
            $memberRole->role_id = $grantRoleId;
            $memberRole->start($startOn, $expiresOn, $termYears); //TODO: this should be the start of the entity, not the start of the role
            $memberRole->entity_type = $entityType;
            $memberRole->entity_id = $entityId;
            $memberRole->approver_id = $memberId;
            $memberRole->branch_id = $branchId;
            if (!$memberRoleTable->save($memberRole)) {
                return new ServiceResult(false, "Failed to Assign Role from Member");
            }
            $entity->granted_member_role_id = $memberRole->id;
            if (!$entityTable->save($entity)) {
                return new ServiceResult(false, "Failed to save $entityType");
            }
        }
        return new ServiceResult(true);
    }

    /**
     * Stops an active window for an entity - Make sure to create a transaction before calling this service
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $memberId
     * @param string $status
     * @param string $reason
     * @param DateTime $expiresOn
     * @return bool
     */
    public function stop(
        string $entityType,
        int $entityId,
        int $memberId,
        string $status,
        string $reason,
        DateTime $expiresOn,
    ): ServiceResult {
        $entityTable = TableRegistry::getTableLocator()->get($entityType);
        $entity = $entityTable->get($entityId);
        $entity->expire($expiresOn);
        $entity->revoker_id = $memberId;
        $entity->status = $status;
        $entity->revoked_reason = $reason;
        if ($entity->granted_member_role_id != null) {
            $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
            $memberRole = $memberRoleTable->get($entity->granted_member_role_id);
            $memberRole->expire($expiresOn);
            $memberRole->revoker_id = $memberId;
            if (!$memberRoleTable->save($memberRole)) {
                return new ServiceResult(false, "Failed to Remove Role from Member");
            }
        }
        if (!$entityTable->save($entity)) {
            return new ServiceResult(false, "Failed to save $entityType");
        }
        return new ServiceResult(true);
    }
}