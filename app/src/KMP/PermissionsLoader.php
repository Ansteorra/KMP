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

    public static function getPermissions(int $member_id): array{
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        $now = DateTime::now();

        $query = $permissionsTable->find()
            ->innerJoinWith('Roles.Members')
            ->where(['Members.id'=> $member_id])
            ->where(['OR' => [
                    'MemberRoles.ended_on IS ' => null,
                    'MemberRoles.ended_on >' => DateTime::now()
                ]])
            ->where(['OR' => [
                    'Permissions.require_active_membership' => false,
                    'Members.membership_expires_on >' => DateTime::now()
                ]])
            ->where(['OR' => [
                    'Permissions.require_active_background_check' => false,
                    'Members.background_check_expires_on >' => DateTime::now()
                ]])
            ->where(['OR' => [
                    'Permissions.require_min_age' => 0,
                    'AND' => [
                        'Members.birth_year = '. strval($now->year) . ' - Permissions.require_min_age',
                        'Members.birth_month <=' => $now->month
                    ],
                    'Members.birth_year < '. strval($now->year)  . ' - Permissions.require_min_age'
                ]])
            ->distinct()
            ->all()->toList();
        return $query;
    }

    public static function getCurrentAuthorizationTypeApprovers($authorization_type_id){
        $memberTable = TableRegistry::getTableLocator()->get('Members');
        $now = DateTime::now();

        $query = $memberTable->find()
            ->contain(['Branchs','AuthorizationTypes'])
            ->innerJoinWith('Roles.Permissions')
            ->where(['OR' => ['Permissions.authorization_type_id' => $authorization_type_id, 'Permissions.is_super_user' => true]])
            ->where(['OR' => [
                    'Permissions.require_active_membership' => false,
                    'Members.membership_expires_on >' => DateTime::now()
                ]])
            ->where(['OR' => [
                    'Permissions.require_active_background_check' => false,
                    'Members.background_check_expires_on >' => DateTime::now()
                ]])
            ->where(['OR' => [
                    'Permissions.require_min_age' => 0,
                    'AND' => [
                        'Members.birth_year = '. strval($now->year) . ' - Permissions.require_min_age',
                        'Members.birth_month <=' => $now->month
                    ],
                    'Members.birth_year < '. strval($now->year)  . ' - Permissions.require_min_age'
                ]])
            ->distinct();
        return $query;
    }

    public static function generateToken(){
        return bin2hex(random_bytes(32));
    }

}