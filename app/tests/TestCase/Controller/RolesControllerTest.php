<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Tests for RolesController, focusing on grid sorting.
 */
class RolesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testIndex(): void
    {
        $this->get('/roles');
        $this->assertResponseOk();
    }

    public function testGridDataNoSort(): void
    {
        $this->get('/roles/grid-data');
        $this->assertResponseOk();
    }

    public function testGridDataSortByMemberCountAsc(): void
    {
        $this->get('/roles/grid-data?ignore_default=1&sort=member_count&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByMemberCountDesc(): void
    {
        $this->get('/roles/grid-data?ignore_default=1&sort=member_count&direction=desc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByName(): void
    {
        $this->get('/roles/grid-data?ignore_default=1&sort=name&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByIsSystem(): void
    {
        $this->get('/roles/grid-data?ignore_default=1&sort=is_system&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByUnknownColumn(): void
    {
        $this->get('/roles/grid-data?ignore_default=1&sort=bogus_field&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataPaginationLinksPreserveSort(): void
    {
        $this->get('/roles/grid-data?ignore_default=1&sort=name&direction=asc&limit=1');
        $this->assertResponseOk();

        $body = (string)$this->_response->getBody();
        $dom = new \DOMDocument();
        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $dom->loadHTML($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);
        $links = $dom->getElementsByTagName('a');

        $hrefs = [];
        $hasSortedNextPageLink = false;
        foreach ($links as $link) {
            $href = (string)$link->getAttribute('href');
            if ($href === '') {
                continue;
            }

            $decodedHref = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);
            $hrefs[] = $decodedHref;
            $queryString = parse_url($decodedHref, PHP_URL_QUERY);
            if (!is_string($queryString)) {
                continue;
            }

            parse_str($queryString, $params);
            if (
                (string)($params['page'] ?? '') === '2'
                && (string)($params['sort'] ?? '') === 'name'
                && (string)($params['direction'] ?? '') === 'asc'
            ) {
                $hasSortedNextPageLink = true;
                break;
            }
        }

        $this->assertTrue(
            $hasSortedNextPageLink,
            'Expected at least one page=2 pagination link to preserve sort=name and direction=asc. '
            . 'Found links: ' . implode(', ', $hrefs)
        );
    }
}
