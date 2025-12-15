<?php

declare(strict_types=1);

namespace App\Services\WarrantManager;

use Cake\I18n\DateTime;

/**
 * Warrant Request Data Structure
 *
 * Encapsulates warrant application data for a member to hold a specific position
 * or role within an organizational entity.
 *
 * @see \App\Services\WarrantManager\WarrantManagerInterface::request() Primary usage
 * @see \App\Model\Entity\Warrant Database entity created from this request
 */
class WarrantRequest
{
    /** @var string Entity type (e.g., 'Branches', 'Activities', 'Direct Grant') */
    public string $entityType;

    /** @var int Entity primary key ID */
    public int $entityId;

    /** @var int ID of member requesting this warrant */
    public int $requester_id;

    /** @var int ID of member who will receive the warrant */
    public int $member_id;

    /** @var int|null Optional specific role within the entity */
    public ?int $member_role_id;

    /** @var DateTime|null Optional custom start date (null = use period default) */
    public ?DateTime $start_on;

    /** @var DateTime|null Optional custom end date (null = use period default) */
    public ?DateTime $expires_on;

    /** @var string Human-readable warrant title */
    public string $name;

    /**
     * Create a new warrant request.
     *
     * @param string $name Warrant title
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @param int $requester_id Requester member ID
     * @param int $member_id Recipient member ID
     * @param DateTime|null $start_on Optional start date
     * @param DateTime|null $expires_on Optional end date
     * @param int|null $member_role_id Optional role within entity
     */
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
