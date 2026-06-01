<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AppController;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;

/**
 * Tests for TurboResponseTrait helpers via AppController.
 */
class TurboResponseTraitTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        // Bootstrap routing for Router::url() used by trait helpers.
        $this->get('/app-settings');
    }

    private function traitController(?ServerRequest $request = null): AppController
    {
        $request ??= new ServerRequest();
        $controller = new class ($request) extends AppController {
            public function exposeAssert(string $url): string
            {
                return $this->assertSafeContextUrl($url);
            }

            public function exposeBuild(?string $pageContext): string
            {
                return $this->buildGridDataUrlFromPageContext(
                    $pageContext,
                    ['controller' => 'AppSettings', 'action' => 'gridData'],
                );
            }

            public function exposeWants(): bool
            {
                return $this->wantsTurboStreamRequest();
            }

            public function exposeIsGrid(?string $url): bool
            {
                return $this->isGridOriginRequest($url);
            }

            public function exposeMatchesIndex(?string $url, string $regex): bool
            {
                return $this->matchesGridIndexPath($url, $regex);
            }

            public function exposeWithPageContext(?string $url): ?string
            {
                return $this->withPageContextQuery($url, function (): ?string {
                    return $this->request->getQuery('search');
                });
            }

            public function exposeCloseStream(): Response
            {
                return $this->renderTurboCloseModal(
                    'app-settings-grid-table',
                    ['controller' => 'AppSettings', 'action' => 'gridData'],
                    '/app-settings?search=needle',
                    [],
                );
            }

            public function exposeReloadStream(): Response
            {
                return $this->renderTurboReloadFrame(
                    'editRecommendationQuick',
                    '/awards/recommendations/turbo-quick-edit-form/42',
                    [],
                );
            }

            public function exposeReplaceRowStream(): Response
            {
                return $this->renderTurboReplaceGridRow(
                    'recommendations-grid-row-42',
                    '<tr id="recommendations-grid-row-42" data-id="42"><td>ok</td></tr>',
                    [],
                );
            }

            public function exposeRemoveRowStream(): Response
            {
                return $this->renderTurboRemoveGridRow('recommendations-grid-row-99', []);
            }
        };

        return $controller;
    }

    public function testAssertSafeContextUrlRejectsExternalUrls(): void
    {
        $this->expectException(BadRequestException::class);
        $this->traitController()->exposeAssert('https://evil.example/path');
    }

    public function testBuildGridDataUrlPreservesQueryFromPageContext(): void
    {
        $url = $this->traitController()->exposeBuild('/app-settings?search=foo&filter[state][]=Draft');

        $this->assertStringContainsString('grid-data', $url);
        $this->assertStringContainsString('search=foo', $url);
        $this->assertStringContainsString('filter', $url);
    }

    public function testWantsTurboStreamFromAcceptHeader(): void
    {
        $request = (new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]))->withHeader('Accept', 'text/vnd.turbo-stream.html, text/html');

        $this->assertTrue($this->traitController($request)->exposeWants());
    }

    public function testPageContextAloneDoesNotRequestTurboStream(): void
    {
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['page_context_url' => '/awards/bestowals?search=test'],
        ]);

        $this->assertFalse($this->traitController($request)->exposeWants());
    }

    public function testIsGridOriginRequestDetectsIndexPath(): void
    {
        $controller = $this->traitController();

        $this->assertTrue($controller->exposeIsGrid('/awards/recommendations?search=a'));
        $this->assertFalse($controller->exposeIsGrid('/awards/recommendations/view/1'));
    }

    public function testRenderTurboCloseModalReturnsRenderedStreamBody(): void
    {
        $response = $this->traitController()->exposeCloseStream();
        $body = (string)$response->getBody();

        $this->assertStringContainsString('<turbo-stream action="replace"', $body);
        $this->assertStringContainsString('target="app-settings-grid-table"', $body);
        $this->assertStringContainsString('search=needle', $body);
    }

    public function testRenderTurboReloadFrameReturnsRenderedStreamBody(): void
    {
        $response = $this->traitController()->exposeReloadStream();
        $body = (string)$response->getBody();

        $this->assertStringContainsString('<turbo-stream action="replace"', $body);
        $this->assertStringContainsString('target="editRecommendationQuick"', $body);
        $this->assertStringContainsString('/awards/recommendations/turbo-quick-edit-form/42', $body);
    }

    public function testRenderTurboReplaceGridRowReturnsRenderedStreamBody(): void
    {
        $response = $this->traitController()->exposeReplaceRowStream();
        $body = (string)$response->getBody();

        $this->assertStringContainsString('<turbo-stream action="replace"', $body);
        $this->assertStringContainsString('target="recommendations-grid-row-42"', $body);
        $this->assertStringContainsString('recommendations-grid-row-42', $body);
    }

    public function testRenderTurboRemoveGridRowReturnsRenderedStreamBody(): void
    {
        $response = $this->traitController()->exposeRemoveRowStream();
        $body = (string)$response->getBody();

        $this->assertStringContainsString('<turbo-stream action="remove"', $body);
        $this->assertStringContainsString('target="recommendations-grid-row-99"', $body);
    }

    public function testMatchesGridIndexPathUsesPathOnly(): void
    {
        $controller = $this->traitController();

        $this->assertTrue($controller->exposeMatchesIndex('/app-settings?search=x', '#/app-settings/?$#'));
        $this->assertFalse($controller->exposeMatchesIndex('/app-settings/extra', '#/app-settings/?$#'));
    }

    public function testWithPageContextQueryAppliesQueryParams(): void
    {
        $search = $this->traitController()->exposeWithPageContext('/app-settings?search=needle');

        $this->assertSame('needle', $search);
    }
}
