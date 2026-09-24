<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Cell;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use DOMDocument;
use DOMXPath;

/**
 * NavigationCell regression tests.
 */
class NavigationCellTest extends HttpIntegrationTestCase
{
    public function testConfigLinksIncludePluginsInAlphabeticalOrder(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertResponseOk();

        $document = new DOMDocument();
        $document->loadHTML((string)$this->_response->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//*[@id="navheader_config"]/following-sibling::nav[1]/a');
        $labels = [];
        foreach ($links as $link) {
            $labels[] = trim($link->textContent);
        }
        $this->assertContains('App Settings', $labels);
        $this->assertContains('Activity Groups', $labels);
        $this->assertContains('Waiver Types', $labels);
        $expected = $labels;
        natcasesort($expected);
        $this->assertSame(array_values($expected), $labels);
    }

    public function testAuthenticatedUsersSeeMyApprovalsNavigation(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);

        $this->get('/members/view/' . self::TEST_MEMBER_BRYCE_ID);

        $this->assertResponseOk();
        $this->assertResponseContains('Action Items');
        $this->assertResponseContains('My Approvals');
        $this->assertResponseContains('/approvals');
    }
}
