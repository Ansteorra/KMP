<?php
declare(strict_types=1);

namespace App\Error;

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

        $body = json_encode([
            'error' => [
                'code' => $code,
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
