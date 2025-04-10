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
abstract class BaseEntity extends Entity
{

    public function getBranchId(): ?int
    {
        return $this->branch_id ?? null;
    }
}
