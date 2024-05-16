<?php
declare(strict_types=1);

namespace App\KMP;

use ArrayAccess;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

use Permission;

class PermissionsLoader{

    public static function getPermissions(int $participant_id): array{
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        $query = $permissionsTable->find()
            ->innerJoinWith('Roles.Participants')
            ->where(['Participants.id'=> $participant_id])
            ->where(function ($exp, $q) {
                $orConditions = $exp->or(function($or){
                    $now = DateTime::now();
                    return $or->isNull('ParticipantRoles.ended_on')
                        ->gt('ParticipantRoles.ended_on',$now);
                });
                return $exp->add($orConditions);
            })
            ->where(function ($exp, $q) {
                $orConditions = $exp->or(function($or){
                    $now = DateTime::now();
                    return $or->eq('Permissions.require_active_membership',false)
                        ->gt('Participants.membership_expires_on',$now);
                });
                return $exp->add($orConditions);
            })
            ->where(function ($exp, $q) {
                $orConditions = $exp->or(function($or){
                    $now = DateTime::now();
                    return $or->eq('Permissions.require_active_background_check',false)
                        ->gt('Participants.background_check_expires_on',$now);
                });
                return $exp->add($orConditions);
            })
            ->distinct()
            ->all()->toList();
        return $query;
    }

}