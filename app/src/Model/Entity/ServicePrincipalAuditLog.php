<?php

declare(strict_types=1);

namespace App\Model\Entity;

use App\KMP\TimezoneHelper;

/**
 * ServicePrincipalAuditLog Entity - API Request Audit Trail
 *
 * Records all API requests for compliance and debugging.
 *
 * @property int $id
 * @property int $service_principal_id
 * @property int|null $token_id
 * @property string $action
 * @property string $endpoint
 * @property string $http_method
 * @property string|null $ip_address
 * @property string|null $request_summary
 * @property int|null $response_code
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\ServicePrincipal $service_principal
 * @property \App\Model\Entity\ServicePrincipalToken|null $token
 */
class ServicePrincipalAuditLog extends BaseEntity
{
    /**
     * Fields accessible for mass assignment.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'service_principal_id' => true,
        'token_id' => true,
        'action' => true,
        'endpoint' => true,
        'http_method' => true,
        'ip_address' => true,
        'request_summary' => true,
        'response_code' => true,
    ];

    /**
     * Get formatted creation date for display.
     *
     * @return string
     */
    protected function _getCreatedToString(): string
    {
        if ($this->created === null) {
            return '';
        }

        return TimezoneHelper::formatDateTime($this->created);
    }

    /**
     * Get HTTP status category (success, redirect, client error, server error).
     *
     * @return string
     */
    protected function _getStatusCategory(): string
    {
        if ($this->response_code === null) {
            return 'unknown';
        }

        if ($this->response_code >= 200 && $this->response_code < 300) {
            return 'success';
        }
        if ($this->response_code >= 300 && $this->response_code < 400) {
            return 'redirect';
        }
        if ($this->response_code >= 400 && $this->response_code < 500) {
            return 'client_error';
        }
        if ($this->response_code >= 500) {
            return 'server_error';
        }

        return 'unknown';
    }
}
