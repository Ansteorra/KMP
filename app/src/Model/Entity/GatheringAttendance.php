<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * GatheringAttendance Entity
 *
 * Represents a member's attendance record for a gathering,
 * including sharing preferences and optional public notes.
 *
 * @property int $id
 * @property int $gathering_id
 * @property int $member_id
 * @property string|null $public_note
 * @property bool $share_with_kingdom
 * @property bool $share_with_hosting_group
 * @property bool $share_with_crown
 * @property bool $is_public
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\Member $creator
 * @property \App\Model\Entity\Member $modifier
 */
class GatheringAttendance extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'member_id' => true,
        'public_note' => true,
        'share_with_kingdom' => true,
        'share_with_hosting_group' => true,
        'share_with_crown' => true,
        'is_public' => true,
        'created_by' => true,
        'modified_by' => true,
        'created' => true,
        'modified' => true,
        'deleted' => true,
        'gathering' => true,
        'member' => true,
        'creator' => true,
        'modifier' => true,
    ];

    /**
     * Virtual field to check if attendance is shared with anyone
     *
     * @return bool
     */
    protected function _getIsShared(): bool
    {
        return $this->share_with_kingdom
            || $this->share_with_hosting_group
            || $this->share_with_crown
            || $this->is_public;
    }

    /**
     * Virtual field to get sharing description
     *
     * @return string
     */
    protected function _getSharingDescription(): string
    {
        if (!$this->is_shared) {
            return 'Not shared';
        }

        $shares = [];
        if ($this->is_public) {
            $shares[] = 'Public';
        }
        if ($this->share_with_crown) {
            $shares[] = 'Crown';
        }
        if ($this->share_with_kingdom) {
            $shares[] = 'Kingdom';
        }
        if ($this->share_with_hosting_group) {
            $shares[] = 'Hosting Group';
        }

        return implode(', ', $shares);
    }

    /**
     * Get the branch ID for policy scoping
     * 
     * Returns the branch_id of the gathering this attendance is for.
     * This is used by the BasePolicy for branch-based permission scoping.
     * 
     * The gathering's branch_id is used because attendance records should
     * follow the same permission scoping as the gathering itself - if you
     * can manage a gathering's branch, you can manage attendance for that gathering.
     *
     * @return int|null
     */
    public function getBranchId(): ?int
    {
        // If the gathering is loaded and has a branch_id, return it
        if (isset($this->gathering) && isset($this->gathering->branch_id)) {
            return $this->gathering->branch_id;
        }

        // If gathering isn't loaded, we can't determine the branch_id
        // The policy system should ensure gatherings are contained when needed
        return null;
    }

    /**
     * Virtual fields
     *
     * @var array<string>
     */
    protected array $_virtual = ['is_shared', 'sharing_description'];
}
