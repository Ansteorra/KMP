<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * Represents an impersonation session event (start/stop).
 *
 * @property int $id
 * @property int $impersonator_id
 * @property int $impersonated_member_id
 * @property string $event
 * @property string|null $request_url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Cake\I18n\FrozenTime $created
 */
class ImpersonationSessionLog extends Entity
{
    protected array $_accessible = [
        'impersonator_id' => true,
        'impersonated_member_id' => true,
        'event' => true,
        'request_url' => true,
        'ip_address' => true,
        'user_agent' => true,
        'created' => true,
        'impersonator' => true,
        'impersonated_member' => true,
    ];

    protected function _getCreated(): ?FrozenTime
    {
        $created = $this->_fields['created'] ?? null;
        if ($created instanceof FrozenTime || $created === null) {
            return $created;
        }

        return FrozenTime::parse($created);
    }
}
