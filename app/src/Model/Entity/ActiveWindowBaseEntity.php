<?php
declare(strict_types=1);

namespace App\Model\Entity;

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
    public const UPCOMING_STATUS = 'Upcoming';
    public const CURRENT_STATUS = 'Current';
    public const RELEASED_STATUS = 'Released';
    public const REPLACED_STATUS = 'Replaced';
    public const EXPIRED_STATUS = 'Expired';
    public const DEACTIVATED_STATUS = 'Deactivated';
    public const CANCELLED_STATUS = 'Cancelled';

    public array $typeIdField = [];

    /**
     * Starts an active window for an entity - save your entity after calling
     *
     * @param \Cake\I18n\Datetime $expiresOn
     * @param ?\Cake\I18n\Datetime $expiresOn
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
     * @param \Cake\I18n\Datetime $expiresOn
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
            return 'No Exp Date';
        }

        return $this->expires_on->toDateString();
    }

    protected function _getStartOnToString()
    {
        return $this->start_on->toDateString();
    }
}
