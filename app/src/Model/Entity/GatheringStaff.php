<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringStaff Entity
 *
 * Represents a staff member for a gathering. Staff can be stewards (who require contact info)
 * or other roles. Staff can be linked to AMP member accounts or be generic SCA names.
 *
 * @property int $id
 * @property int $gathering_id
 * @property int|null $member_id
 * @property string|null $sca_name
 * @property string $role
 * @property bool $is_steward
 * @property bool $show_on_public_page
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $contact_notes
 * @property int $sort_order
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \App\Model\Entity\Member|null $member
 */
class GatheringStaff extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'member_id' => true,
        'sca_name' => true,
        'role' => true,
        'is_steward' => true,
        'show_on_public_page' => true,
        'email' => true,
        'phone' => true,
        'contact_notes' => true,
        'sort_order' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'gathering' => true,
        'member' => true,
    ];

    /**
     * Virtual field to get display name
     *
     * Returns the member's SCA name if linked to a member, otherwise the sca_name field
     *
     * @return string
     */
    protected function _getDisplayName(): string
    {
        if ($this->member !== null && isset($this->member->sca_name)) {
            return $this->member->sca_name;
        }

        return $this->sca_name ?? 'Unknown';
    }

    /**
     * Virtual field to check if contact info is provided
     *
     * @return bool
     */
    protected function _getHasContactInfo(): bool
    {
        return !empty($this->email) || !empty($this->phone);
    }

    /**
     * Get the branch ID associated with this entity
     *
     * GatheringStaff entities do not have a direct branch_id column.
     * Instead, the branch is determined through the associated gathering.
     *
     * ## Authorization Integration
     *
     * This method is used by the authorization system to determine which
     * users have access to this staff entity based on their branch permissions.
     * Since staff belong to gatherings, and gatherings belong to branches,
     * we return the gathering's branch_id.
     *
     * ## Important Usage Requirements
     *
     * **Callers MUST ensure the gathering association is loaded** before
     * invoking this method. This can be done by:
     *
     * ### In Controllers
     * ```php
     * $gatheringStaff = $this->GatheringStaff->get($id, [
     *     'contain' => ['Gatherings']
     * ]);
     * ```
     *
     * ### In Authorization Checks
     * ```php
     * $this->GatheringStaff->loadInto($gatheringStaff, ['Gatherings']);
     * $this->Authorization->authorize($gatheringStaff);
     * ```
     *
     * ### In Queries
     * ```php
     * $query->contain(['Gatherings']);
     * ```
     *
     * ## Return Values
     *
     * - **int**: The branch_id from the associated gathering (when gathering is loaded)
     * - **null**: When gathering association is not loaded or gathering has no branch_id
     *
     * @return int|null The branch ID from the associated gathering, or null if not available
     *
     * @see \App\Model\Entity\BaseEntity::getBranchId() For base implementation details
     * @see \App\Model\Entity\Gathering For the gathering entity structure
     */
    public function getBranchId(): ?int
    {
        // Return the branch_id from the associated gathering if it's loaded
        return $this->gathering->branch_id ?? null;
    }
}
