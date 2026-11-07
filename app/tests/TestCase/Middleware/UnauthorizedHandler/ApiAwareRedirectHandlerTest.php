<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware\UnauthorizedHandler;

use App\Middleware\UnauthorizedHandler\ApiAwareRedirectHandler;
use Authorization\Exception\Exception;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ApiAwareRedirectHandlerTest extends TestCase
{
    public static function partialRequests(): array
    {
        return [
            'frame' => ['/members', 'Turbo-Frame', 'members-grid-table'],
            'stream' => ['/members', 'Accept', 'text/vnd.turbo-stream.html, text/html'],
            'XHR' => ['/members', 'X-Requested-With', 'XMLHttpRequest'],
            'API' => ['/api/members', 'Accept', 'application/json'],
        ];
    }

    #[DataProvider('partialRequests')]
    public function testPartialRequestsRetainTheAuthorizationError(string $path, string $header, string $value): void
    {
        $request = (new ServerRequest(['url' => $path]))->withHeader($header, $value);
        $exception = new Exception('Access denied', 403);
        $this->expectExceptionObject($exception);
        (new ApiAwareRedirectHandler())->handle($exception, $request, [
            'url' => '/pages/unauthorized', 'exceptions' => [Exception::class],
        ]);
    }

    public function testDocumentRequestsKeepTheNativeRedirect(): void
    {
        $response = (new ApiAwareRedirectHandler())->handle(
            new Exception('Access denied', 403),
            new ServerRequest(['url' => '/members']),
            ['url' => '/pages/unauthorized', 'exceptions' => [Exception::class]],
        );
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/pages/unauthorized', $response->getHeaderLine('Location'));
    }
}
