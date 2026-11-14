<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TenantContext;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Firebase\JWT\JWT;
use RuntimeException;

/** Short-lived, exact-origin subscriptions contain no record content. */
final class LiveSubscriptionService
{
    /** @param list<string> $topics Server-authorized resource keys. @return array<string, mixed> */
    public function issue(ServerRequest $request, array $topics): array
    {
        $privateKey = (string)Configure::read('Turbo.privateKey', env('KMP_LIVE_PRIVATE_KEY', ''));
        $gatewayUrl = TurboConfig::gatewayUrl();
        if ($privateKey === '' || $gatewayUrl === '') {
            throw new RuntimeException('Live delivery is not configured.');
        }
        $origin = $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority();
        $now = time();
        $token = JWT::encode([
            'iss' => 'kmp-ui', 'aud' => 'kmp-live', 'iat' => $now, 'exp' => $now + 120,
            'tenant' => TenantContext::id(), 'origin' => $origin, 'topics' => [...$topics, 'Security:list'],
            'context' => (new NavigationContextService())->signature($request),
        ], $privateKey, 'RS256');

        return ['url' => $gatewayUrl, 'token' => $token, 'expiresAt' => $now + 120, 'expiresIn' => 120,
            'context' => (new NavigationContextService())->signature($request)];
    }
}
