<?php

declare(strict_types=1);

namespace App\Middleware\UnauthorizedHandler;

use Authorization\Exception\Exception;
use Authorization\Middleware\UnauthorizedHandler\RedirectHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Re-throws authorization exceptions for API routes so that
 * ApiExceptionRenderer returns JSON; redirects all other routes.
 */
class ApiAwareRedirectHandler extends RedirectHandler
{
    /**
     * @inheritDoc
     */
    public function handle(
        Exception $exception,
        ServerRequestInterface $request,
        array $options = []
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        if (str_contains($path, '/api/')) {
            throw $exception;
        }

        return parent::handle($exception, $request, $options);
    }
}
