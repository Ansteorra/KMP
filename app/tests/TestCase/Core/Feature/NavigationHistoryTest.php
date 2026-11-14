<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use DOMDocument;
use DOMXPath;

/** Browser history owns return context; PHP renders only an authorized fallback. */
class NavigationHistoryTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
    }

    public function testProfileRedirectPreservesDetailTabAndGridContext(): void
    {
        $query = ['tab' => 'gatherings', 'grid_context' => 'member-gatherings-grid-' . self::ADMIN_MEMBER_ID,
            'search' => 'context test', 'page' => '3'];
        $this->get('/members/profile?' . http_build_query($query));
        $this->assertRedirect('/members/view/' . self::ADMIN_MEMBER_ID . '?' . http_build_query($query));
    }

    public function testOrdinaryVisitsDoNotBuildASecondServerHistoryStack(): void
    {
        foreach (['/members/view/' . self::ADMIN_MEMBER_ID, '/gatherings/calendar', '/members'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertSession(null, 'pageStack');
            $this->assertSession(null, 'pageStackVersion');
        }
    }

    public function testLegacySessionHistoryCannotChooseTheBackDestination(): void
    {
        $this->session(['pageStack' => ['https://attacker.example/', '/unrelated-old-page'], 'pageStackVersion' => 2]);
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertResponseOk();
        $xpath = $this->responseXPath();
        $links = $xpath->query('//a[@data-kmp-back]');
        $this->assertGreaterThan(0, $links->length);
        foreach ($links as $link) {
            $this->assertSame('/members', $link->getAttribute('href'));
            $this->assertSame('history-back', $link->getAttribute('data-controller'));
            $this->assertSame('history-back#go', $link->getAttribute('data-action'));
        }
    }

    public function testGatheringReaderAndOrganizerUseTheSharedBrowserBackControl(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        foreach ([self::ADMIN_MEMBER_ID, self::TEST_MEMBER_AGATHA_ID] as $memberId) {
            $this->authenticateAsMember($memberId);
            $this->get('/gatherings/view/' . $gathering->public_id);
            $this->assertResponseOk();
            $xpath = $this->responseXPath();
            $back = $xpath->query('//article[contains(@class, "gathering-reader")]//a[@data-kmp-back]');
            $this->assertCount(1, $back);
            $this->assertSame('Go back', $back->item(0)->getAttribute('aria-label'));
            $this->assertSame('_top', $back->item(0)->getAttribute('data-turbo-frame'));
            $this->assertCount(1, $xpath->query('.//kmp-icon[@name="circle-arrow-left"]', $back->item(0)));
            $this->assertStringStartsWith('/', $back->item(0)->getAttribute('href'));
        }
    }

    public function testFragmentsAndBackgroundReadsDoNotCreateBackDestinations(): void
    {
        $this->configRequest(['headers' => ['Turbo-Frame' => 'email-subscriptions']]);
        $this->get('/grid-subscriptions');
        $this->assertResponseOk();
        $this->assertSession(null, 'pageStack');
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
        $this->get('/navigation/context');
        $this->assertResponseOk();
        $this->assertSession(null, 'pageStack');
    }

    public function testDownloadsAndPrefetchesDoNotCreateBackDestinations(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $this->get('/gatherings/download-calendar/' . $gathering->public_id);
        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Disposition', 'attachment');
        $this->assertSession(null, 'pageStack');
        $this->configRequest(['headers' => ['X-Sec-Purpose' => 'prefetch', 'Accept' => 'text/html']]);
        $this->get('/members');
        $this->assertResponseOk();
        $this->assertSession(null, 'pageStack');
    }

    private function responseXPath(): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
