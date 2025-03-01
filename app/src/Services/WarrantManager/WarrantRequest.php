<?php

declare(strict_types=1);

namespace App\Services\WarrantManager;

use Cake\I18n\DateTime;

class WarrantRequest
{
    public string $entityType;
    public int $entityId;
    public int $requester_id;
    public int $member_id;
    public ?int $member_role_id;
    public ?DateTime $start_on;
    public ?DateTime $expires_on;
    public string $name;

    public function __construct(string $name, string $entity_type, int $entity_id, int $requester_id, int $member_id, ?DateTime $start_on = null, ?DateTime $expires_on = null, ?int $member_role_id = null)
    {
        $this->entity_type = $entity_type;
        $this->entity_id = $entity_id;
        $this->requester_id = $requester_id;
        $this->member_id = $member_id;
        $this->start_on = $start_on;
        $this->expires_on = $expires_on;
        $this->member_role_id = $member_role_id;
        $this->name = $name;
    }
}