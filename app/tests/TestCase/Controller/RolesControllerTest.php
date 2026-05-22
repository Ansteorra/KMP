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
        preg_match_all('/href="([^"]+)"/', $body, $matches);

        $hasSortedNextPageLink = false;
        foreach ($matches[1] ?? [] as $href) {
            $decodedHref = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);
            if (
                str_contains($decodedHref, 'page=2')
                && str_contains($decodedHref, 'sort=name')
                && str_contains($decodedHref, 'direction=asc')
            ) {
                $hasSortedNextPageLink = true;
                break;
            }
        }

        $this->assertTrue($hasSortedNextPageLink, 'Expected pagination link to preserve sort and direction query params');
    }
}
