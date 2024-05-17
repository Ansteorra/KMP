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

    public static function getPermissions(int $Member_id): array{
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        $query = $permissionsTable->find()
            ->innerJoinWith('Roles.Members')
            ->where(['Members.id'=> $Member_id])
            ->where(function ($exp, $q) {
                $orConditions = $exp->or(function($or){
                    $now = DateTime::now();
                    return $or->isNull('MemberRoles.ended_on')
                        ->gt('MemberRoles.ended_on',$now);
                });
                return $exp->add($orConditions);
            })
            ->where(function ($exp, $q) {
                $orConditions = $exp->or(function($or){
                    $now = DateTime::now();
                    return $or->eq('Permissions.require_active_membership',false)
                        ->gt('Members.membership_expires_on',$now);
                });
                return $exp->add($orConditions);
            })
            ->where(function ($exp, $q) {
                $orConditions = $exp->or(function($or){
                    $now = DateTime::now();
                    return $or->eq('Permissions.require_active_background_check',false)
                        ->gt('Members.background_check_expires_on',$now);
                });
                return $exp->add($orConditions);
            })
            ->where(function ($exp, $q) {
                $orConditions = $exp->or(function($or){
                    $now = DateTime::now();
                    $orYear = $or->or(function($orYear) use ($now){
                        $andCurYear = $orYear->and(function($andCurYear) use ($now){
                            $andCurYear->add('Members.birth_year = '. strval($now->year) . ' - Permissions.require_min_age')
                                ->lte('Members.birth_month', $now->month);
                            return $andCurYear;
                        });
                        $orYear->add('Members.birth_year < ' . strval($now->year)  . ' - Permissions.require_min_age')
                            ->add($andCurYear);
                        return $orYear;
                    });
                    return $or->eq('Permissions.require_min_age',0)
                        ->add($orYear);
                });
                return $exp->add($orConditions);
            })
            ->distinct()
            ->all()->toList();
        return $query;
    }

}