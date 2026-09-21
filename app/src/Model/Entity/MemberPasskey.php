<?php
declare(strict_types=1);

namespace App\Model\Entity;

/** Only the verified registration service may assign credential fields. */
class MemberPasskey extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = ['*' => false];

    /**
     * @var array<string>
     */
    protected array $_hidden = ['auth_version', 'credential_id', 'public_key'];
}
