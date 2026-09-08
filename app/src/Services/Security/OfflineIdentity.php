<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\Services\ImpersonationService;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Utility\Security;

/** Opaque current actor binding for private offline snapshots and replay. */
final class OfflineIdentity
{
    /** @return array<string, mixed>|null */
    public static function context(ServerRequest $request): ?array
    {
        $identity = $request->getAttribute('identity');
        $tenant = MemberSessionState::tenantId();
        if (!$identity || $tenant === null || empty($identity->auth_version)) {
            return null;
        }
        $owner = hash_hmac('sha256', $tenant . ':' . $identity->id, Security::getSalt());
        $now = time() * 1000;

        return [
            'owner' => $owner,
            'epoch' => hash_hmac('sha256', $owner . ':' . $identity->auth_version, Security::getSalt()),
            'impersonating' => (new ImpersonationService())->isActive($request->getSession()),
            'serverTime' => $now,
            'expiresAt' => $now + 7 * 86400 * 1000,
        ];
    }

    /** Attach the actor that produced this response, preventing A/B/A snapshot races. */
    public static function bind(Response $response, ServerRequest $request): Response
    {
        $context = self::context($request);

        return $response->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-KMP-Offline-Owner', (string)($context['owner'] ?? ''))
            ->withHeader('X-KMP-Offline-Epoch', (string)($context['epoch'] ?? ''));
    }

    /** @param array<string, mixed> $data Posted expected actor, never an authorization grant. */
    public static function matches(ServerRequest $request, array $data): bool
    {
        $context = self::context($request);

        return $context !== null && !$context['impersonating']
            && is_string($data['offline_owner'] ?? null) && is_string($data['offline_epoch'] ?? null)
            && hash_equals($context['owner'], $data['offline_owner'])
            && hash_equals($context['epoch'], $data['offline_epoch']);
    }
}
