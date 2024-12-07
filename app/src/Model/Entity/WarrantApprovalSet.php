<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * WarrantApprovalSet Entity
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
 * @property \App\Model\Entity\WarrantApproval[] $warrant_approvals
 * @property \App\Model\Entity\Warrant[] $warrants
 */
class WarrantApprovalSet extends Entity
{
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
        'planned_expires_on' => true,
        'planned_start_on' => true,
        'approvals_required' => true,
        'approval_count' => true,
        'created_by' => true,
        'created' => true,
        'warrant_approvals' => true,
        'warrants' => true,
    ];
}
