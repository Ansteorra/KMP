<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use Cake\Routing\Router;

/**
 * Turbo Stream responses for partial page updates (modal save, grid refresh).
 *
 * Works with Turbo Drive disabled; forms need data-turbo="true".
 */
trait TurboResponseTrait
{
    /**
     * Whether the client expects a turbo-stream response.
     */
    protected function wantsTurboStreamRequest(): bool
    {
        $accept = $this->request->getHeaderLine('Accept');
        if (str_contains($accept, 'text/vnd.turbo-stream.html')) {
            return true;
        }

        return false;
    }

    /**
     * Posted page context URL (path + query), validated.
     */
    protected function getPageContextUrl(): ?string
    {
        $url = $this->request->getData('page_context_url');
        if (!is_string($url) || $url === '') {
            return null;
        }

        return $this->assertSafeContextUrl($url);
    }

    /**
     * Ensure URL is same-origin relative path + query only.
     *
     * @throws \Cake\Http\Exception\BadRequestException
     */
    protected function assertSafeContextUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_contains($url, '://') || str_starts_with($url, '//')) {
            throw new BadRequestException('Invalid page context URL.');
        }

        if (!str_starts_with($url, '/')) {
            $url = '/' . ltrim($url, '/');
        }

        if (str_contains($url, "\n") || str_contains($url, "\r")) {
            throw new BadRequestException('Invalid page context URL.');
        }

        return $url;
    }

    /**
     * Read flash from session and clear it for stream rendering.
     *
     * @return array<string, mixed>
     */
    protected function consumeFlashForStream(): array
    {
        $flashMessages = $this->request->getSession()->read('Flash') ?? [];
        $this->request->getSession()->delete('Flash');

        return is_array($flashMessages) ? $flashMessages : [];
    }

    /**
     * Build grid-data URL preserving query string from page context.
     *
     * @param array<string, mixed> $gridDataRoute Cake URL array for gridData action
     */
    protected function buildGridDataUrlFromPageContext(?string $pageContextUrl, array $gridDataRoute): string
    {
        $refreshUrl = Router::url($gridDataRoute);
        if ($pageContextUrl === null) {
            return $refreshUrl;
        }

        $parsed = parse_url($pageContextUrl);
        if (!empty($parsed['query'])) {
            $separator = str_contains($refreshUrl, '?') ? '&' : '?';
            $refreshUrl .= $separator . $parsed['query'];
        }

        return $refreshUrl;
    }

    /**
     * Whether POST originated from a grid index (stay on list).
     */
    protected function isGridOriginRequest(?string $pageContextUrl): bool
    {
        if ($pageContextUrl === null) {
            return false;
        }

        $path = parse_url($pageContextUrl, PHP_URL_PATH) ?? $pageContextUrl;

        return !preg_match('#/view(/|$)#', $path);
    }

    /**
     * Render turbo-stream: flash + replace table frame with lazy reload src.
     *
     * @param array<string, mixed> $gridDataRoute
     */
    protected function renderTurboCloseModal(
        string $refreshFrame,
        array $gridDataRoute,
        ?string $pageContextUrl = null,
        ?array $flashMessages = null,
    ): Response {
        if ($flashMessages === null) {
            $flashMessages = $this->consumeFlashForStream();
        }

        $refreshUrl = $this->buildGridDataUrlFromPageContext($pageContextUrl, $gridDataRoute);

        $this->response = $this->response->withType('text/vnd.turbo-stream.html');
        $this->viewBuilder()->setPlugin(null);
        $this->viewBuilder()->disableAutoLayout();
        $this->set(compact('refreshFrame', 'refreshUrl', 'flashMessages'));
        $this->viewBuilder()->setTemplatePath('element');
        $this->viewBuilder()->setTemplate('turbo_close_modal');

        return $this->render();
    }

    /**
     * Stream that reloads an edit turbo-frame (validation errors).
     */
    protected function renderTurboReloadFrame(string $frameId, string $frameSrc, ?array $flashMessages = null): Response
    {
        if ($flashMessages === null) {
            $flashMessages = $this->consumeFlashForStream();
        }

        $this->response = $this->response->withType('text/vnd.turbo-stream.html');
        $this->viewBuilder()->setPlugin(null);
        $this->viewBuilder()->disableAutoLayout();
        $this->set(compact('frameId', 'frameSrc', 'flashMessages'));
        $this->viewBuilder()->setTemplatePath('element');
        $this->viewBuilder()->setTemplate('turbo_reload_frame');

        return $this->render();
    }

    /**
     * Render turbo-stream: flash + replace a single grid row by DOM id.
     */
    protected function renderTurboReplaceGridRow(
        string $rowDomId,
        string $rowHtml,
        ?array $flashMessages = null,
    ): Response {
        if ($flashMessages === null) {
            $flashMessages = $this->consumeFlashForStream();
        }

        $this->response = $this->response->withType('text/vnd.turbo-stream.html');
        $this->viewBuilder()->setPlugin(null);
        $this->viewBuilder()->disableAutoLayout();
        $this->set([
            'rowDomId' => $rowDomId,
            'rowHtml' => $rowHtml,
            'flashMessages' => $flashMessages,
            'streamAction' => 'replace',
        ]);
        $this->viewBuilder()->setTemplatePath('element');
        $this->viewBuilder()->setTemplate('turbo_sync_grid_row');

        return $this->render();
    }

    /**
     * Render turbo-stream: flash + remove a grid row (no longer matches current filters).
     */
    protected function renderTurboRemoveGridRow(string $rowDomId, ?array $flashMessages = null): Response
    {
        if ($flashMessages === null) {
            $flashMessages = $this->consumeFlashForStream();
        }

        $this->response = $this->response->withType('text/vnd.turbo-stream.html');
        $this->viewBuilder()->setPlugin(null);
        $this->viewBuilder()->disableAutoLayout();
        $this->set([
            'rowDomId' => $rowDomId,
            'rowHtml' => '',
            'flashMessages' => $flashMessages,
            'streamAction' => 'remove',
        ]);
        $this->viewBuilder()->setTemplatePath('element');
        $this->viewBuilder()->setTemplate('turbo_sync_grid_row');

        return $this->render();
    }
}
