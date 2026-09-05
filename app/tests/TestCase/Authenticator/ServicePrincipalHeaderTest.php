<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authenticator;

use App\Authenticator\ServicePrincipalAuthenticator;
use Authentication\Identifier\IdentifierCollection;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

class ServicePrincipalHeaderTest extends TestCase
{
    public function testUrlCredentialsAreIgnoredAndHeadersRemainSupported(): void
    {
        $authenticator = new class (new IdentifierCollection()) extends ServicePrincipalAuthenticator {
            public function token(ServerRequest $request): ?string
            {
                return $this->extractToken($request);
            }
        };
        $request = (new ServerRequest())->withQueryParams(['api_key' => 'synthetic-url-key']);
        $this->assertNull($authenticator->token($request));
        $this->assertSame('synthetic-header-key', $authenticator->token($request->withHeader('X-API-Key', 'synthetic-header-key')));
        $this->assertSame('synthetic-bearer', $authenticator->token($request->withHeader('Authorization', 'Bearer synthetic-bearer')));
    }
}
