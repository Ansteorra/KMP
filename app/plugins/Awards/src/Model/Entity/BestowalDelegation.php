<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * An audited grant for one gathering and exact award-owning branch.
 *
 * @property int $id
 * @property int $gathering_id
 * @property int $branch_id
 * @property int $grantor_id
 * @property int $delegate_id
 * @property int $created_by
 * @property int|null $revoked_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $revoked_at
 * @property \App\Model\Entity\Member|null $grantor
 * @property \App\Model\Entity\Member|null $delegate
 * @property \App\Model\Entity\Gathering|null $gathering
 * @property \App\Model\Entity\Branch|null $branch
 */
class BestowalDelegation extends BaseEntity
{
    protected array $_accessible = [
        'gathering_id' => true,
        'branch_id' => true,
        'delegate_id' => true,
    ];
}
