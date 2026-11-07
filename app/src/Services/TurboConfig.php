<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Utility\Inflector;
use RuntimeException;

/** Runtime switches and deliberate document-navigation boundaries. */
final class TurboConfig
{
    /** Enable Drive only after the compatibility gates pass. */
    public static function enabled(): bool
    {
        return (bool)Configure::read(
            'Turbo.enabled',
            filter_var(env('KMP_TURBO_ENABLED', false), FILTER_VALIDATE_BOOL),
        );
    }

    /** Delivery has an independent fallback and requires the Drive compatibility gate. */
    public static function liveEnabled(): bool
    {
        return self::enabled()
            && (bool)Configure::read(
                'Turbo.liveEnabled',
                filter_var(env('KMP_LIVE_UPDATES_ENABLED', false), FILTER_VALIDATE_BOOL),
            );
    }

    /** Restrict CSP and subscriptions to the same explicitly configured gateway. */
    public static function gatewayUrl(): string
    {
        $url = (string)Configure::read('Turbo.gatewayUrl', env('KMP_LIVE_GATEWAY_URL', ''));
        $parts = parse_url($url);
        if (
            !is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false
            || isset($parts['user'], $parts['pass']) || isset($parts['user'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ($parts['path'] ?? '') !== '/events'
            || !preg_match('/^[a-z0-9.-]+$/i', $parts['host'] ?? '')
            || !in_array($parts['scheme'] ?? '', Configure::read('debug') ? ['http', 'https'] : ['https'], true)
        ) {
            throw new RuntimeException('Live gateway URL must be an explicit HTTPS events endpoint.');
        }

        return $url;
    }

    /** A validated origin cannot add extra CSP sources or directives. */
    public static function gatewayOrigin(): string
    {
        $parts = parse_url(self::gatewayUrl());

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /** Identity and editor boundaries retain native document navigation. */
    public static function boundary(ServerRequest $request): bool
    {
        $controller = (string)$request->getParam('controller');
        $action = Inflector::dasherize((string)$request->getParam('action'));

        return $request->getAttribute('identity') === null
            || in_array(
                Inflector::dasherize((string)$request->getParam('prefix')),
                self::nativeNavigation()['controllers'],
                true,
            )
            || in_array(Inflector::dasherize($controller), self::nativeNavigation()['controllers'], true)
            || array_any(
                self::nativeNavigation()['actions'],
                fn(string $part): bool => str_contains(strtolower($action), $part),
            )
            || in_array(strtolower((string)$request->getParam('_ext')), self::nativeNavigation()['extensions'], true);
    }

    /** Shared server/browser paths that need native identity, editor, download or upload semantics. */
    public static function nativeNavigation(): array
    {
        return [
            'controllers' => ['auth', 'passkeys', 'workflow-definitions', 'workflows', 'platform-admin', 'table-admin'],
            'actions' => ['login', 'logout', 'password', 'impersonat', 'mfa', 'passkey',
                'offline', 'download', 'export', 'upload', 'mobile', 'public', 'switch-view'],
            'paths' => ['/events'],
            'extensions' => ['pdf', 'csv', 'xlsx', 'zip'],
        ];
    }
}
