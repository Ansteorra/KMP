<?php
declare(strict_types=1);

namespace App\Error;

use Authorization\Exception\ForbiddenException as AuthForbiddenException;
use Authorization\Exception\MissingIdentityException;
use Cake\Core\Configure;
use Cake\Error\Renderer\WebExceptionRenderer;
use Psr\Http\Message\ResponseInterface;

/**
 * Returns JSON error responses for API routes instead of HTML error pages.
 *
 * Routes starting with /api/ receive a JSON envelope matching ApiController's
 * apiError() format.  All other routes fall through to the default HTML renderer.
 */
class ApiExceptionRenderer extends WebExceptionRenderer
{
    protected array $exceptionHttpCodes = [
        AuthForbiddenException::class => 403,
        MissingIdentityException::class => 401,
    ];

    public function render(): ResponseInterface
    {
        if ($this->isApiRequest()) {
            return $this->renderApiJson();
        }

        return parent::render();
    }

    protected function isApiRequest(): bool
    {
        if (!$this->request) {
            return false;
        }

        $path = $this->request->getUri()->getPath();
        if (str_contains($path, '/api/')) {
            return true;
        }

        $prefix = $this->request->getParam('prefix');
        if ($prefix && str_starts_with((string)$prefix, 'Api')) {
            return true;
        }

        return false;
    }

    protected function renderApiJson(): ResponseInterface
    {
        $exception = $this->error;
        $code = $this->getHttpCode($exception);

        $message = $exception->getMessage();
        if (!Configure::read('debug') && $code >= 500) {
            $message = 'An internal error has occurred.';
        }

        $codeLabel = match ($code) {
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            default => 'INTERNAL_ERROR',
        };

        $body = json_encode([
            'error' => [
                'code' => $codeLabel,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->controller->getResponse()
            ->withStatus($code)
            ->withType('application/json')
            ->withStringBody($body);

        return $response;
    }
}
