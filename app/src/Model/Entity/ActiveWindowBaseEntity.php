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
    public function start(int $termYears, Datetime $startOn = null): void
    {
        if ($startOn == null) {
            $startOn = Datetime::now();
        }
        $this->set('start_on', $startOn);
        $this->set('expires_on', $startOn->addYears($termYears));
    }
    public function expire(Datetime $expiresOn = null): void
    {
        if ($expiresOn == null) {
            $expiresOn = Datetime::now();
        }
        $this->set('expires_on', $expiresOn);
    }
}