<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Cake\Datasource\EntityInterface;
use Cake\I18n\Datetime;

/**
 * Branch Entity
 *
 * @property int $id
 * @property string $name
 * @property string $location
 * @property string $region
 */
class ActiveWindowBaseEntity extends Entity
{
    public string $typeIdField = 'type';
    public function start(Datetime $startOn = null, ?DateTime $expiresOn = null, ?int $termYears = null): void
    {
        if ($startOn == null) {
            $startOn = Datetime::now();
        }
        $this->start_on = $startOn->toDateTimeString();
        if ($expiresOn == null) {
            if ($termYears != null && $termYears != -1) {
                $this->expires_on = $startOn->addYears($termYears)->toDateTimeString();
            } else {
                $this->expiresOn = null;
            }
        } else {
            $this->expiresOn = $expiresOn->toDateTimeString();
        }
    }
    public function expire(Datetime $expiresOn = null): void
    {
        if ($expiresOn == null) {
            $expiresOn = Datetime::now();
        }
        $expiresOn = $expiresOn->subSeconds(1);
        $this->set('expires_on', $expiresOn->toDateTimeString());
    }
}