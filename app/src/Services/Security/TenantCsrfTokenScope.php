<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\KMP\TenantContext;
use Cake\Utility\Security;

/**
 * Config hook for a future tenant-bound CSRF token rollout.
 */
final class TenantCsrfTokenScope
{
    /**
     * Return the configured CSRF signing salt; tenant binding is disabled by default.
     */
    public function signingSalt(): string
    {
        if (!$this->isEnabled()) {
            return Security::getSalt();
        }

        $tenant = TenantContext::tryCurrent();
        if ($tenant === null) {
            return Security::getSalt();
        }

        return hash('sha256', Security::getSalt() . '|tenant:' . $tenant->id);
    }

    /**
     * Return whether tenant-bound CSRF signing is enabled.
     */
    public function isEnabled(): bool
    {
        return filter_var((string)env('KMP_TENANT_CSRF_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
    }
}
