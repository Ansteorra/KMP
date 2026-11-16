<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Security\TrustedProxy;
use Cake\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Apply validated client-IP and TLS metadata without accepting a forwarded tenant host. */
final class TrustedProxyMiddleware implements MiddlewareInterface
{
    /** @param \App\Services\Security\TrustedProxy|null $policy Optional explicit trust policy */
    public function __construct(private readonly ?TrustedProxy $policy = null)
    {
    }

    /** Normalize forwarded metadata before tenant resolution, rate limiting, and audit logging. */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request instanceof ServerRequest) {
            return $handler->handle($request);
        }
        $request = clone $request;
        $request->trustProxy = false;
        $policy = $this->policy ?? TrustedProxy::fromEnvironment();
        $peer = (string)$request->getEnv('REMOTE_ADDR');
        if ($policy->isTrustedPeer($peer)) {
            $proto = $request->getHeaderLine('X-Forwarded-Proto');
            if (in_array($proto, ['http', 'https'], true)) {
                $request = $request->withUri($request->getUri()->withScheme($proto))
                    ->withEnv('HTTPS', $proto === 'https' ? 'on' : 'off')
                    ->withEnv('HTTP_X_FORWARDED_PROTO', $proto);
            }
            $client = $policy->clientAddress($peer, $request->getHeaderLine('X-Forwarded-For'));
            if ($client !== null && $client !== $peer) {
                // Cake sees one validated value, avoiding spoofed prefixes and alternate headers.
                $request = $request->withEnv('HTTP_X_FORWARDED_FOR', $client);
                $request->setTrustedProxies([$peer]);
            }
        }

        return $handler->handle($request);
    }
}
