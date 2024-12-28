<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * WarrantRoster Entity
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property \Cake\I18n\DateTime $planned_expires_on
 * @property \Cake\I18n\DateTime $planned_start_on
 * @property int $approvals_required
 * @property int|null $approval_count
 * @property int|null $created_by
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\WarrantRosterApproval[] $warrant_roster_approvals
 * @property \App\Model\Entity\Warrant[] $warrants
 */
class WarrantRoster extends Entity
{
    const STATUS_APPROVED = "Approved"; //all signers approved
    const STATUS_DECLINED = "Declined"; //at least 1 signer declined
    const STATUS_PENDING = "Pending"; //awaiting approval

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'approvals_required' => true,
        'approval_count' => true,
        'created_by' => true,
        'created' => true,
        'warrant_roster_approvals' => true,
        'warrants' => true,
    ];

    public function hasRequiredApprovals(): bool
    {
        return $this->approval_count >= $this->approvals_required;
    }
}