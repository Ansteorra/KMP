<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;
use App\Auth\LegacyPasswordHasher;
use App\Model\Entity\Role;
use Cake\Utility\Hash;
use \Datetime;
use Cake\Log\Log;

/**
 * Participant Entity
 *
 * @property int $id
 * @property \Cake\I18n\Time $last_updated
 * @property string $password
 * @property string $sca_name
 * @property string $first_name
 * @property string $middle_name
 * @property string $last_name
 * @property string $street_address
 * @property string $city
 * @property string $state
 * @property string $zip
 * @property string $phone_number
 * @property string $email_address
 * @property int $membership_number
 * @property \Cake\I18n\Time $membership_expires_on
 * @property string $branch_name
 * @property \Cake\I18n\Time $birthdate
 * @property string $notes
 * @property int $birth_month
 * @property int $birth_year
 * @property string $parent_name
 * @property \Cake\I18n\Time $background_check_expires_on
 * @property bool $hidden
 *
 * @property \App\Model\Entity\ParticipantAuthorizationType[] $participant_authorization_types
 * @property \App\Model\Entity\Role[] $roles
 */
class Participant extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array
     */
    protected array $_hidden = [
        'password'
    ];

    protected function _setPassword($value)
    {
        if(strlen($value) > 0){
            $hasher = new LegacyPasswordHasher();
            return $hasher->hash($value);
        }else{
           return $this->password; 
        }
    }

    protected function _getBirthdate(){
        $date = new DateTime();
        $date->setDate($this->birth_year, $this->birth_month, 1);
        Log::write('debug', $date);
        return($date);
    }

    protected function _getAge()
    {
        $now = new DateTime();
        $date = new DateTime();
        $date->setDate($this->birth_year, $this->birth_month, 1);
        $interval = $now->diff($date);
        Log::write('debug', $date);
        Log::write('debug', $interval->y);
        return $interval->y;
    }

//Security function
    function hasRole($role){
        if (isset($this['roles'])) {
            $role = Hash::extract($this['roles'], "{n}[name=/".$role."/]");
            if(count($role) > 0){
                return true;
            }
        }

        // Default deny
        return false;
    }
    function isAdmin(){
        return $this->hasRole("Admin");
    }

    function can($action){
        return $this->isAdmin()||$this->hasRole("Secretary");
    }
}
