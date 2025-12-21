<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * GatheringWaiver Entity
 *
 * Represents an uploaded waiver for a gathering OR an exemption (attestation that waiver was not needed).
 * Waivers and exemptions are gathering-level and are not linked to specific activities.
 *
 * @property int $id
 * @property int $gathering_id
 * @property int $waiver_type_id
 * @property int|null $document_id
 * @property bool $is_exemption
 * @property string|null $exemption_reason
 * @property string $status
 * @property \Cake\I18n\Date $retention_date
 * @property int $created_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \Cake\I18n\DateTime|null $declined_at
 * @property int|null $declined_by
 * @property string|null $decline_reason
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \Waivers\Model\Entity\WaiverType $waiver_type
 * @property \App\Model\Entity\Document|null $document
 * @property \App\Model\Entity\Member $created_by_member
 * @property \App\Model\Entity\Member $declined_by_member
 */
class GatheringWaiver extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'waiver_type_id' => true,
        'document_id' => true,
        'is_exemption' => true,
        'exemption_reason' => true,
        'status' => true,
        'retention_date' => true,
        'created_by' => true,
        'created' => true,
        'modified' => true,
        'declined_at' => true,
        'declined_by' => true,
        'decline_reason' => true,
        'gathering' => true,
        'waiver_type' => true,
        'document' => true,
        'created_by_member' => true,
        'declined_by_member' => true,
    ];

    /**
     * Virtual field indicating if waiver is expired
     *
     * @return bool
     */
    protected function _getIsExpired(): bool
    {
        if ($this->status === 'deleted') {
            return true;
        }

        if (empty($this->retention_date)) {
            return false;
        }

        return $this->retention_date->isPast();
    }

    /**
     * Virtual field indicating if waiver is active
     *
     * @return bool
     */
    protected function _getIsActive(): bool
    {
        return $this->status === 'active' && !$this->is_expired;
    }

    /**
     * Virtual field indicating if waiver is declined
     *
     * @return bool
     */
    protected function _getIsDeclined(): bool
    {
        return !empty($this->declined_at);
    }

    /**
     * Virtual field indicating if waiver can be declined
     * 
     * A waiver can be declined if:
     * - It has not already been declined
     * - It was created within the last 30 days
     * - It is not expired or deleted
     *
     * @return bool
     */
    protected function _getCanBeDeclined(): bool
    {
        // Already declined
        if ($this->is_declined) {
            return false;
        }

        // Expired or deleted waivers cannot be declined
        if ($this->status === 'expired' || $this->status === 'deleted') {
            return false;
        }

        // Check if within 30 days of creation
        if (empty($this->created)) {
            return false;
        }

        $thirtyDaysAgo = new \Cake\I18n\DateTime('-30 days');
        return $this->created >= $thirtyDaysAgo;
    }

    /**
     * Virtual field for status badge class
     *
     * @return string
     */
    protected function _getStatusBadgeClass(): string
    {
        if ($this->is_declined) {
            return 'badge-danger';
        }

        return match ($this->status) {
            'active' => $this->is_expired ? 'badge-warning' : 'badge-success',
            'pending' => 'badge-info',
            'deleted' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    /**
     * Virtual field for status display text
     *
     * @return string
     */
    protected function _getStatusDisplay(): string
    {
        if ($this->is_declined) {
            return 'Declined';
        }

        if ($this->status === 'active' && $this->is_expired) {
            return 'Expired';
        }

        return match ($this->status) {
            'active' => 'Active',
            'pending' => 'Pending Review',
            'deleted' => 'Deleted',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get branch ID for authorization scoping
     * 
     * Returns the branch ID of the hosting branch for this waiver's gathering.
     * This enables proper authorization checks by allowing policies to determine
     * which branch context the waiver belongs to.
     * 
     * The branch ID is obtained through the gathering relationship, which connects
     * the waiver to its hosting branch. This supports the authorization system's
     * requirement to check permissions based on organizational scope.
     * 
     * @return int|null Branch ID from the gathering's hosting branch, or null if not determinable
     */
    public function getBranchId(): ?int
    {
        // Try to get from eager-loaded gathering relationship
        if ($this->gathering) {
            return $this->gathering->branch_id;
        }

        // If gathering_id exists but gathering not loaded, fetch it
        if ($this->gathering_id) {
            $gatheringsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Gatherings');
            $gathering = $gatheringsTable->find()
                ->where(['id' => $this->gathering_id])
                ->select(['branch_id'])
                ->first();

            if ($gathering) {
                return $gathering->branch_id;
            }
        }

        return null;
    }
}
