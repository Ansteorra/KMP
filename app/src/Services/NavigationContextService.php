<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\KmpIdentityInterface;
use App\KMP\PermissionsLoader;
use App\KMP\TenantContext;
use App\Model\Entity\Member;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use JsonSerializable;

/** Opaque snapshot boundary; never exposes identity or session secrets. */
final class NavigationContextService
{
    /** Bind snapshots to the current session and effective scoped authorization grants. */
    public function signature(ServerRequest $request): string
    {
        $identity = $request->getAttribute('identity');
        $grants = $identity instanceof Member
            ? [PermissionsLoader::getPermissions((int)$identity->id), PermissionsLoader::getRoles((int)$identity->id)]
            : ($identity instanceof KmpIdentityInterface ? [$identity->getPermissions()] : []);
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
            $identity?->get('branch_id'),
            $identity?->get('parent_id'),
            $identity?->get('status'),
            $request->getSession()->id(),
            (new ImpersonationService())->getState($request->getSession()),
            $this->canonicalGrants($grants),
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

    /** Grant maps and union scopes must hash identically across replica/query ordering. */
    private function canonicalGrants(mixed $value): mixed
    {
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map($this->canonicalGrants(...), $value);
        if (array_is_list($value)) {
            usort($value, static fn($left, $right): int => strcmp(
                json_encode($left, JSON_THROW_ON_ERROR),
                json_encode($right, JSON_THROW_ON_ERROR),
            ));
        } else {
            ksort($value);
        }

        return $value;
    }
}
