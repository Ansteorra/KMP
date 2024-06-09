<?php

namespace App\Services\ActiveWindowManager;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;

class DefaultActiveWindowManager implements ActiveWindowManagerInterface
{
    use MailerAwareTrait;

    public function __construct()
    {
    }

    public function start(
        string $entityType,
        int $entityId,
        int $memberId,
        DateTime $startOn,
        ?DateTime $expiresOn = null,
        ?int $termYears = null,
        ?int $grantRoleId = null,
    ): bool {
        $entityTable = TableRegistry::getTableLocator()->get($entityType);
        $entity = $entityTable->get($entityId);

        //stop all like entities for the member_id of the entity
        $peQuery = $entityTable->find('all')
            ->where([
                'member_id' => $entity->member_id,
                'id !=' => $entity->id,
                'OR' => ['expires_on >=' => DateTime::now(), 'expires_on IS' => null]
            ]);
        if ($entity->typeIdField != null) {
            $peQuery->andWhere([$entity->typeIdField => $entity[$entity->typeIdField]]);
        }
        if ($entityType == "MemberRoles") {
            $peQuery->andWhere(['granting_model' => "Direct Grant"]);
        }
        $previousEntities = $peQuery->all();
        foreach ($previousEntities as $pe) {
            if (!$this->stop($entityType, $pe->id, $memberId, "replaced", "", $startOn)) {
                return false;
            }
        }
        $entity->start($startOn, $expiresOn, $termYears);
        if (!$entityTable->save($entity)) {
            return false;
        }
        // add the member_role if the activity has a grants_role_id
        if ($grantRoleId != null) {
            $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
            $memberRole = $memberRoleTable->newEmptyEntity();
            $memberRole->member_id = $entity->member_id;
            $memberRole->role_id = $grantRoleId;
            $memberRole->start($startOn, $expiresOn, $termYears); //TODO: this should be the start of the entity, not the start of the role
            $memberRole->granting_model = $entityType;
            $memberRole->granting_id = $entityId;
            $memberRole->approver_id = $memberId;
            if (!$memberRoleTable->save($memberRole)) {
                return false;
            }
            $entity->granted_member_role_id = $memberRole->id;
            if (!$entityTable->save($entity)) {
                return false;
            }
        }
        return true;
    }

    public function stop(
        string $entityType,
        int $entityId,
        int $memberId,
        string $status,
        string $reason,
        DateTime $expiresOn,
    ): bool {
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
                return false;
            }
        }
        if (!$entityTable->save($entity)) {
            return false;
        }
        return true;
    }
}