<?php
declare(strict_types=1);

namespace App\Middleware\UnauthorizedHandler;

use Authorization\Exception\Exception;
use Authorization\Middleware\UnauthorizedHandler\RedirectHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Re-throws authorization exceptions for API routes and XHR/fetch requests so
 * the exception renderer returns a proper 403; redirects normal navigations.
 *
 * Redirecting an XHR to the unauthorized page returns 200 HTML that embedded
 * consumers (autocompletes, turbo modals) render inline — and that page's
 * "redirecting you to your profile" script then hijacks the whole window.
 */
class ApiAwareRedirectHandler extends RedirectHandler
{
    /**
     * @inheritDoc
     */
    public function handle(
        Exception $exception,
        ServerRequestInterface $request,
        array $options = [],
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        $isXhr = strcasecmp($request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest') === 0;
        if ($isXhr || str_contains($path, '/api/')) {
            throw $exception;
        }

        return parent::handle($exception, $request, $options);
    }
}
