<?php
declare(strict_types=1);

namespace App\Model\Table;

/** Tenant-local native authentication records. */
class PasskeyChallengesTable extends BaseTable
{
    /** @inheritDoc */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('passkey_challenges');
        $this->setPrimaryKey('id');
    }
}
