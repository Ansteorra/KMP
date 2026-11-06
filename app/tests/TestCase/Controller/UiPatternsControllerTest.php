<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use DOMDocument;
use DOMXPath;

/** The review surface exposes sample workflows, never domain write endpoints. */
class UiPatternsControllerTest extends HttpIntegrationTestCase
{
    public function testShowcaseRequiresAuthentication(): void
    {
        $this->get('/ui-patterns');
        $this->assertRedirectContains('/members/login');
        $this->get('/ui-patterns/grid-data?export=csv');
        $this->assertRedirectContains('/members/login');
        $this->get('/ui-patterns/reader');
        $this->assertRedirectContains('/members/login');
    }

    public function testMembersCanReviewEveryPatternWithoutDomainPermissions(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        foreach (['collection', 'record', 'reader', 'task', 'workspace', 'dashboard'] as $pattern) {
            $this->get('/ui-patterns?pattern=' . $pattern);
            $this->assertResponseOk();
            $this->assertResponseContains('data-pattern="' . $pattern . '"');
            $this->assertResponseContains('fictional records');
            $this->assertResponseContains('id="ui-app-navigation"');
            $this->assertResponseContains('id="main-content"');
        }
    }

    public function testTemplateSelectionIsWhitelisted(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/ui-patterns?pattern=../../Members/view&state=unknown&record=unknown');
        $this->assertResponseOk();
        $this->assertSame('collection', $this->viewVariable('pattern'));
        $this->assertSame('normal', $this->viewVariable('showcaseState'));
        $this->assertSame('branch', $this->viewVariable('recordKind'));
        $this->get('/ui-patterns?pattern[]=reader&state[]=error&record[]=member');
        $this->assertResponseOk();
        $this->assertSame('collection', $this->viewVariable('pattern'));
        $this->assertSame('normal', $this->viewVariable('showcaseState'));
        $this->assertSame('branch', $this->viewVariable('recordKind'));
    }

    public function testExampleFormsCannotSubmitToBusinessEndpoints(): void
    {
        $this->authenticateAsSuperUser();
        foreach (['collection', 'record', 'reader', 'task'] as $pattern) {
            $this->get('/ui-patterns?pattern=' . $pattern);
            $this->assertResponseOk();
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML((string)$this->_response->getBody());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($document);
            $forms = $xpath->query('//*[@data-pattern]//form');
            foreach ($forms as $form) {
                $this->assertMatchesRegularExpression('/ui-pattern(?:s|\-grid)#/', $form->getAttribute('data-action'));
                $this->assertSame('', $form->getAttribute('action'));
            }
        }
    }

    public function testShowcaseDoesNotAcceptWrites(): void
    {
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/ui-patterns', ['name' => 'Should never create a record']);
        $this->assertResponseCode(405);
    }

    public function testCsvExportsAllFilteredRowsInTheSelectedColumnOrder(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/ui-patterns/grid-data?export=csv&columns=start_date,name&filter[branch][]=oakmere&page=2');
        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Type', 'text/csv');
        $rows = array_map(
            static fn($line) => str_getcsv($line, ',', '"', ''),
            explode("\n", trim((string)$this->_response->getBody())),
        );
        $this->assertSame(['Date', 'Gathering'], $rows[0]);
        $this->assertCount(7, $rows);
        $this->assertContains(['2026-12-05', 'Oakmere Winter Court'], $rows);
    }

    public function testGridStateReportsCompletePaginationForAnnouncements(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/ui-patterns?pattern=collection&page=2&limit=10');
        $this->assertResponseOk();
        $this->assertSame([
            'currentPage' => 2, 'pageCount' => 2, 'count' => 4, 'totalCount' => 14,
        ], $this->viewVariable('gridState')['pagination']);
    }

    public function testBranchContextCsvCannotExportAnotherBranch(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/ui-patterns/grid-data?pattern=record&record=branch&export=csv'
            . '&filter[branch][]=willowford&dirty[filters]=1&columns=name,branch');
        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Type', 'text/csv');
        $rows = array_map(
            static fn($line) => str_getcsv($line, ',', '"', ''),
            explode("\n", trim((string)$this->_response->getBody())),
        );
        $this->assertCount(7, $rows);
        $this->assertSame(['Gathering', 'Branch'], array_shift($rows));
        $this->assertSame(['Barony of Oakmere'], array_values(array_unique(array_column($rows, 1))));
    }

    public function testReaderBackPreservesTheLiveCollectionAndEmbeddedGridOrigins(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $origins = [
            '/ui-patterns?pattern=collection&view_id=royal-progress&search=Court&limit=10'
                . '&sort=name&direction=desc&filter%5Bstatus%5D%5B%5D=published',
            '/ui-patterns?pattern=record&record=branch&section=record-branch-gatherings-tab'
                . '&grid_context=ui-patterns-grid&view_id=published&search=Winter&limit=10'
                . '&filter%5Bactivity%5D%5B%5D=court',
        ];
        foreach ($origins as $origin) {
            $this->get('/ui-patterns');
            $this->assertResponseOk();
            $this->session(['pageStack' => $this->viewVariable('pageStack'), 'pageStackVersion' => 2]);
            $this->configRequest(['headers' => ['Referer' => 'http://localhost' . $origin]]);
            $this->get('/ui-patterns/reader');
            $this->assertResponseOk();
            $this->assertSame('reader', $this->viewVariable('pattern'));
            $this->assertSame([$origin, '/ui-patterns/reader'], $this->viewVariable('pageStack'));
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML((string)$this->_response->getBody());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $back = (new DOMXPath($document))->query('//header[contains(@class, "ui-page-header")]//a[@aria-label="Go back"]');
            $this->assertCount(1, $back);
            $this->assertSame($origin, $back->item(0)->getAttribute('href'));
            $this->assertSame('_top', $back->item(0)->getAttribute('data-turbo-frame'));
        }
    }
}
