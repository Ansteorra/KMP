<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * Represents a single impersonation audit record created when a super user writes while impersonating.
 *
 * Captures request metadata (HTTP method, URL, IP), the acting administrator, impersonated member,
 * and the table/entity that was changed.
 *
 * @property int $id
 * @property int $impersonator_id
 * @property int $impersonated_member_id
 * @property string $operation
 * @property string $table_name
 * @property string $entity_primary_key
 * @property string|null $request_method
 * @property string|null $request_url
 * @property string|null $ip_address
 * @property string|null $metadata
 * @property \Cake\I18n\FrozenTime $created
 *
 * @property \App\Model\Entity\Member $impersonator
 * @property \App\Model\Entity\Member $impersonated_member
 */
class ImpersonationActionLog extends Entity
{
    /** @inheritDoc */
    protected array $_accessible = [
        'impersonator_id' => true,
        'impersonated_member_id' => true,
        'operation' => true,
        'table_name' => true,
        'entity_primary_key' => true,
        'request_method' => true,
        'request_url' => true,
        'ip_address' => true,
        'metadata' => true,
        'created' => true,
        'impersonator' => true,
        'impersonated_member' => true,
    ];

    /**
     * @return \Cake\I18n\FrozenTime|null
     */
    protected function _getCreated(): ?FrozenTime
    {
        $created = $this->_fields['created'] ?? null;
        if ($created instanceof FrozenTime || $created === null) {
            return $created;
        }

        return FrozenTime::parse($created);
    }
}
