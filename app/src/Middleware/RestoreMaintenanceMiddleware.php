<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\RestoreStatusService;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Short-circuits normal web traffic while destructive database restore is running.
 */
class RestoreMaintenanceMiddleware implements MiddlewareInterface
{
    /**
     * Block normal request handling while a restore is active.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Server request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Request handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $statusService = new RestoreStatusService();
        $status = $statusService->getStatus();
        if (empty($status['locked']) && ($status['status'] ?? null) !== 'running') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        if ($path === '/backups/status') {
            return (new Response())
                ->withType('application/json')
                ->withStringBody((string)json_encode($status));
        }

        if ($path === '/health') {
            return (new Response())
                ->withStatus(503)
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'status' => 'restore_in_progress',
                    'restore' => $status,
                    'timestamp' => date('c'),
                ]));
        }

        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return (new Response())
                ->withStatus(503)
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'success' => false,
                    'message' => 'Restore is in progress. Please try again after it completes.',
                    'restore' => $status,
                ]));
        }

        return (new Response())
            ->withStatus(503)
            ->withType('text/html')
            ->withStringBody($this->htmlBody($status));
    }

    /**
     * @param array<string, mixed> $status
     */
    private function htmlBody(array $status): string
    {
        $message = htmlspecialchars(
            (string)($status['message'] ?? 'Restore is in progress.'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $style = 'body{font-family:system-ui,sans-serif;margin:3rem;line-height:1.5;color:#222}'
            . '.card{max-width:42rem;padding:1.5rem;border:1px solid #ddd;border-radius:.5rem}';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="10">
    <title>Restore in progress</title>
    <style>{$style}</style>
</head>
<body>
    <main class="card">
        <h1>Restore in progress</h1>
        <p>{$message}</p>
        <p>This page will refresh automatically.</p>
    </main>
</body>
</html>
HTML;
    }
}
