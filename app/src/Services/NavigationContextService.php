<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TenantContext;
use App\Services\Cache\TenantAwareCache;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;

/** Opaque snapshot boundary; never exposes identity or session secrets. */
final class NavigationContextService
{
    /** Bind snapshots to the current authenticated session and permission generation. */
    public function signature(ServerRequest $request): string
    {
        $identity = $request->getAttribute('identity');
        $revisionFile = CONFIG . 'revision.txt';
        $revision = is_file($revisionFile) ? trim((string)file_get_contents($revisionFile)) : '';
        if ($revision === '' || $revision === 'unknown') {
            $manifest = WWW_ROOT . '.vite/manifest.json';
            $revision = is_file($manifest) ? hash_file('sha256', $manifest) : 'unknown';
        }
        $context = [
            TenantContext::tryCurrent()?->id,
            $request->getUri()->getHost(),
            $identity?->getIdentifier(),
            $identity?->get('auth_version'),
            $request->getSession()->id(),
            (new ImpersonationService())->getState($request->getSession()),
            (new TenantAwareCache())->read('security_generation'),
            Configure::read('App.imageTag', 'unknown'),
            Configure::read('App.releaseRevision', $revision),
            TurboConfig::enabled(),
            TurboConfig::liveEnabled(),
        ];

        return hash_hmac(
            'sha256',
            json_encode($context, JSON_THROW_ON_ERROR),
            (string)Configure::read('Security.salt'),
        );
    }
}
