<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringWaiver Entity
 *
 * Represents an uploaded waiver for a gathering.
 *
 * @property int $id
 * @property int $gathering_id
 * @property int $waiver_type_id
 * @property int $document_id
 * @property string $status
 * @property \Cake\I18n\Date $retention_date
 * @property int $created_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \Waivers\Model\Entity\WaiverType $waiver_type
 * @property \App\Model\Entity\Document $document
 * @property \App\Model\Entity\Member $created_by_member
 * @property \Waivers\Model\Entity\GatheringWaiverActivity[] $gathering_waiver_activities
 */
class GatheringWaiver extends Entity
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
        'status' => true,
        'retention_date' => true,
        'created_by' => true,
        'created' => true,
        'modified' => true,
        'gathering' => true,
        'waiver_type' => true,
        'document' => true,
        'created_by_member' => true,
        'gathering_waiver_activities' => true,
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
     * Virtual field for status badge class
     *
     * @return string
     */
    protected function _getStatusBadgeClass(): string
    {
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
}
