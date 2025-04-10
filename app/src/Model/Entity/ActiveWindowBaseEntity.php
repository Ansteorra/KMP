<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Cake\Datasource\EntityInterface;
use Cake\I18n\Datetime;

/**
 * ActiveWindowBaseEntity Entity
 *
 * @property int $id
 * @property int $member_id
 * @property \Cake\I18n\Date|null $expires_on
 * @property \Cake\I18n\Date|null $start_on
 * @property string $status
 * @property string|null $revoked_reason
 * @property int|null $revoker_id
 */
abstract class ActiveWindowBaseEntity extends BaseEntity
{
    const UPCOMING_STATUS = "Upcoming";
    const CURRENT_STATUS = "Current";
    const RELEASED_STATUS = "Released";
    const REPLACED_STATUS = "Replaced";
    const EXPIRED_STATUS = "Expired";
    const DEACTIVATED_STATUS = "Deactivated";
    const CANCELLED_STATUS = "Cancelled";

    public array $typeIdField = [];

    /**
     * Starts an active window for an entity - save your entity after calling
     *
     * @param DateTime $expiresOn
     * @param ?DateTime $expiresOn
     * @param ?int $termYears
     * @return bool
     */
    public function start(?Datetime $startOn = null, ?DateTime $expiresOn = null, ?int $termYears = null): void
    {
        if ($startOn == null) {
            $startOn = Datetime::now();
        }
        $this->start_on = $startOn->toDateTimeString();
        if ($expiresOn == null) {
            if ($termYears != null && $termYears != -1) {
                $this->expires_on = $startOn->addMonths($termYears)->toDateTimeString();
            } else {
                $this->expires_on = null;
            }
        } else {
            $this->expires_on = $expiresOn->toDateTimeString();
        }
    }

    /**
     * Stops an active window for an entity - save your entity after calling
     * 
     * @param DateTime $expiresOn
     * @return bool
     */
    public function expire(?Datetime $expiresOn = null): void
    {
        if ($expiresOn == null) {
            $expiresOn = Datetime::now();
        }
        $expiresOn = $expiresOn->subSeconds(1);
        $this->set('expires_on', $expiresOn->toDateTimeString());
    }

    protected function _getExpiresOnToString()
    {
        if ($this->expires_on == null) {
            return "No Exp Date";
        }
        return $this->expires_on->toDateString();
    }

    protected function _getStartOnToString()
    {
        return $this->start_on->toDateString();
    }
}
