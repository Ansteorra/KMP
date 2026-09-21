<?php
declare(strict_types=1);

namespace App\Model\Table;

/** Public passkey credentials; registration and use are mediated by PasskeyService. */
class MemberPasskeysTable extends BaseTable
{
    /** @inheritDoc */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('member_passkeys');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Members', ['foreignKey' => 'member_id', 'joinType' => 'INNER']);
    }
}
