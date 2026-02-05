<?php

declare(strict_types=1);

namespace App\Model\Entity;

use App\KMP\TimezoneHelper;

/**
 * ServicePrincipalRole Entity - Role Assignment for Service Principals
 *
 * Mirrors MemberRole structure to enable RBAC for API clients.
 *
 * @property int $id
 * @property int $service_principal_id
 * @property int $role_id
 * @property int|null $branch_id
 * @property \Cake\I18n\Date $start_on
 * @property \Cake\I18n\Date|null $expires_on
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property int|null $approver_id
 * @property \Cake\I18n\DateTime|null $revoked_on
 * @property int|null $revoker_id
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\ServicePrincipal $service_principal
 * @property \App\Model\Entity\Role $role
 * @property \App\Model\Entity\Branch|null $branch
 * @property \App\Model\Entity\Member|null $approved_by
 * @property \App\Model\Entity\Member|null $revoked_by
 */
class ServicePrincipalRole extends ActiveWindowBaseEntity
{
    /** @var array Field combination for type identification */
    public array $typeIdField = ['role_id', 'service_principal_id'];

    /**
     * Fields accessible for mass assignment.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'service_principal_id' => true,
        'role_id' => true,
        'branch_id' => true,
        'start_on' => true,
        'expires_on' => true,
        'entity_type' => true,
        'entity_id' => true,
        'approver_id' => true,
        'revoked_on' => true,
        'revoker_id' => true,
        'service_principal' => true,
        'role' => true,
        'branch' => true,
        'approved_by' => true,
        'revoked_by' => true,
    ];

    /**
     * Get the granting entity description.
     *
     * @param mixed $value
     * @return string
     */
    protected function _getGrantedBy($value): string
    {
        if ($this->entity_type === 'Direct Grant') {
            return 'Direct Grant';
        }

        return $this->entity_type ?? 'Direct Grant';
    }

    /**
     * Format start date for display.
     *
     * @return string
     */
    protected function _getStartOnToString(): string
    {
        if ($this->start_on === null) {
            return '';
        }

        return TimezoneHelper::formatDate($this->start_on);
    }

    /**
     * Format expiration date for display.
     *
     * @return string
     */
    protected function _getExpiresOnToString(): string
    {
        if ($this->expires_on === null) {
            return '';
        }

        return TimezoneHelper::formatDate($this->expires_on);
    }
}
