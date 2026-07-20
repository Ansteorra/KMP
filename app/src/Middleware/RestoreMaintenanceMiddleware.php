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
        if (!$this->shouldShowMaintenance($status)) {
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
    private function shouldShowMaintenance(array $status): bool
    {
        return !empty($status['locked'])
            || ($status['status'] ?? null) === 'running'
            || !empty($status['maintenance_required']);
    }

    /**
     * @param array<string, mixed> $status
     */
    private function htmlBody(array $status): string
    {
        $failed = ($status['status'] ?? null) === 'failed';
        $title = $failed ? 'Restore failed' : 'Restore in progress';
        $headingClass = $failed ? ' class="failed"' : '';
        $refreshText = $failed
            ? 'The restore did not complete. This page remains available so the restore log can be reviewed.'
            : 'This page will refresh automatically.';
        $message = htmlspecialchars(
            (string)($status['message'] ?? 'Restore is in progress.'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $phase = htmlspecialchars((string)($status['phase'] ?? 'running'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $source = htmlspecialchars((string)($status['source'] ?? 'backup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $jobId = htmlspecialchars((string)($status['queue_job_id'] ?? 'pending'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logItems = $this->restoreLogItems($status);

        $style = 'body{font-family:system-ui,sans-serif;margin:3rem;line-height:1.5;color:#222}'
            . '.card{max-width:48rem;padding:1.5rem;border:1px solid #ddd;border-radius:.5rem}'
            . '.meta{color:#555}.log{max-height:20rem;overflow:auto;border:1px solid #ddd;'
            . 'border-radius:.375rem;padding:1rem;background:#f8f9fa}.log ol{margin:0;padding-left:1.5rem}'
            . '.log li{margin-bottom:.5rem}.timestamp{display:block;color:#555;font-size:.875rem}'
            . '.failed{color:#842029}';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="10">
    <title>{$title}</title>
    <style>{$style}</style>
</head>
<body>
    <main class="card">
        <h1{$headingClass}>{$title}</h1>
        <p>{$message}</p>
        <p class="meta">Phase: {$phase}<br>Source: {$source}<br>Queue job: {$jobId}</p>
        <section aria-labelledby="restore-log-heading">
            <h2 id="restore-log-heading">Restore log</h2>
            <div class="log" role="log" aria-live="polite" aria-relevant="additions text">
                {$logItems}
            </div>
        </section>
        <p>{$refreshText}</p>
    </main>
</body>
</html>
HTML;
    }

    /**
     * Render restore log lines for the maintenance page.
     *
     * @param array<string, mixed> $status
     */
    private function restoreLogItems(array $status): string
    {
        $log = $status['log'] ?? [];
        if (!is_array($log) || $log === []) {
            return '<p>No restore log entries have been written yet.</p>';
        }

        $items = [];
        foreach ($log as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $timestamp = htmlspecialchars((string)($entry['timestamp'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message = htmlspecialchars((string)($entry['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $items[] = sprintf('<li><span class="timestamp">%s</span>%s</li>', $timestamp, $message);
        }

        if ($items === []) {
            return '<p>No restore log entries have been written yet.</p>';
        }

        return '<ol>' . implode('', $items) . '</ol>';
    }
}
