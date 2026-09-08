<?php
declare(strict_types=1);

namespace App\Model\Entity;

/** Server-managed authentication record; never mass assigned from request data. */
class MemberPasskey extends BaseEntity
{
    protected array $_accessible = ['*' => false];

    protected array $_hidden = ['public_key', 'credential_id', 'user_handle', 'member_state', 'binding'];
}
