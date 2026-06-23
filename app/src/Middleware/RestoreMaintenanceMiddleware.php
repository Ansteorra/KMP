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
        $requiresMaintenance = !empty($status['maintenance_required']);
        if (empty($status['locked']) && ($status['status'] ?? null) !== 'running' && !$requiresMaintenance) {
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
                ->withStatus($requiresMaintenance ? 500 : 503)
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'success' => false,
                    'message' => $requiresMaintenance
                        ? 'Restore failed and maintenance is required.'
                        : 'Restore is in progress. Please try again after it completes.',
                    'restore' => $status,
                ]));
        }

        return (new Response())
            ->withStatus($requiresMaintenance ? 500 : 503)
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
        $isFailed = ($status['status'] ?? null) === 'failed';
        $title = $isFailed ? 'Restore failed' : 'Restore in progress';
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logHtml = $this->logHtml($status);

        $style = 'body{font-family:system-ui,sans-serif;margin:3rem;line-height:1.5;color:#222}'
            . '.card{max-width:52rem;padding:1.5rem;border:1px solid #ddd;border-radius:.5rem}'
            . '.log{max-height:24rem;overflow:auto;background:#111;color:#f5f5f5;padding:1rem;border-radius:.375rem}'
            . '.log-entry{margin:0 0 .5rem}.log-time{color:#9ad;margin-right:.5rem}';
        $refresh = $isFailed ? '' : '<meta http-equiv="refresh" content="10">';
        $helpText = $isFailed
            ? 'The restore did not complete. Review the log below before restarting the application.'
            : 'This page will refresh automatically.';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {$refresh}
    <title>{$escapedTitle}</title>
    <style>{$style}</style>
</head>
<body>
    <main class="card">
        <h1>{$escapedTitle}</h1>
        <p>{$message}</p>
        <p>{$helpText}</p>
        {$logHtml}
    </main>
</body>
</html>
HTML;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function logHtml(array $status): string
    {
        $log = $status['log'] ?? [];
        if (!is_array($log) || $log === []) {
            return '<p>No restore log entries have been written yet.</p>';
        }

        $entries = [];
        foreach ($log as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $timestamp = htmlspecialchars((string)($entry['timestamp'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message = htmlspecialchars((string)($entry['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $entries[] = "<p class=\"log-entry\"><span class=\"log-time\">{$timestamp}</span>{$message}</p>";
        }

        return '<section aria-labelledby="restore-log-title"><h2 id="restore-log-title">Restore log</h2>'
            . '<div class="log" role="log" aria-live="polite">' . implode('', $entries) . '</div></section>';
    }
}
