<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Services\Api;

use App\Model\Entity\Member;
use App\Services\AuthorizationService;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\MapResolver;
use Officers\Services\Api\DefaultReadOnlyOfficerRosterService;
use Officers\Services\Api\ReadOnlyOfficerRosterServiceInterface;

class DefaultReadOnlyOfficerRosterServiceTest extends BaseTestCase
{
    protected DefaultReadOnlyOfficerRosterService $service;
    protected Member $admin;
    protected AuthorizationService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $resolver = new MapResolver();
        $resolver->map(
            \Officers\Model\Entity\Officer::class,
            \Officers\Policy\OfficerPolicy::class,
        );
        $this->authService = new AuthorizationService($resolver);

        $members = $this->getTableLocator()->get('Members');
        $this->admin = $members->get(self::ADMIN_MEMBER_ID);
        $this->admin->getPermissions();
        $this->admin->setAuthorization($this->authService);

        $this->service = new DefaultReadOnlyOfficerRosterService();
    }

    // ── Instantiation ────────────────────────────────────────────────

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ReadOnlyOfficerRosterServiceInterface::class, $this->service);
    }

    // ── list() ───────────────────────────────────────────────────────

    public function testListReturnsDataAndMeta(): void
    {
        $result = $this->service->list($this->admin, [], 1, 25);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('pagination', $result['meta']);
    }

    public function testListPaginationStructure(): void
    {
        $result = $this->service->list($this->admin, [], 1, 25);
        $pagination = $result['meta']['pagination'];

        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total_pages', $pagination);
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(25, $pagination['per_page']);
    }

    public function testListDefaultsToCurrentAndUpcoming(): void
    {
        $result = $this->service->list($this->admin, [], 1, 100);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertContains(
                strtolower($row['status']),
                ['current', 'upcoming'],
                "Expected current/upcoming, got: {$row['status']}",
            );
        }
    }

    public function testListRowHasExpectedKeys(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $row = $result['data'][0];

        $expected = [
            'id', 'member_id', 'member_name', 'branch',
            'office_id', 'office_name', 'status',
            'start_on', 'expires_on',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $row, "Missing key: $key");
        }
    }

    public function testListRowBranchStructure(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $row = $result['data'][0];

        if ($row['branch'] !== null) {
            $this->assertArrayHasKey('id', $row['branch']);
            $this->assertArrayHasKey('name', $row['branch']);
        }
    }

    public function testListFilterByStatus(): void
    {
        $result = $this->service->list($this->admin, ['status' => 'Released'], 1, 25);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertSame('Released', $row['status']);
        }
    }

    public function testListFilterByOfficeId(): void
    {
        // Office 14 = Local MoAS
        $result = $this->service->list($this->admin, ['office_id' => 14, 'status' => 'Current'], 1, 100);

        foreach ($result['data'] as $row) {
            $this->assertSame(14, $row['office_id']);
        }
    }

    public function testListFilterByBranchPublicId(): void
    {
        // Branch 14 (Shire of Adlersruhe) has public_id x67oKj3v
        $result = $this->service->list($this->admin, ['branch' => 'x67oKj3v'], 1, 100);

        foreach ($result['data'] as $row) {
            $this->assertNotNull($row['branch']);
            $this->assertSame('x67oKj3v', $row['branch']['id']);
        }
    }

    public function testListFilterByInvalidBranchReturnsEmpty(): void
    {
        $result = $this->service->list($this->admin, ['branch' => 'FAKE_PUBLIC_ID'], 1, 25);

        $this->assertEmpty($result['data']);
        $this->assertSame(0, $result['meta']['pagination']['total']);
    }

    public function testListPaginationLimitsResults(): void
    {
        $result = $this->service->list($this->admin, [], 1, 3);

        $this->assertLessThanOrEqual(3, count($result['data']));
    }

    public function testListPaginationPage2NoOverlap(): void
    {
        $page1 = $this->service->list($this->admin, [], 1, 5);
        $page2 = $this->service->list($this->admin, [], 2, 5);

        if (!empty($page2['data'])) {
            $page1Ids = array_column($page1['data'], 'id');
            $page2Ids = array_column($page2['data'], 'id');
            $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
        } else {
            $this->assertLessThanOrEqual(5, $page1['meta']['pagination']['total']);
        }
    }

    public function testListRowDoesNotContainDetailFields(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $row = $result['data'][0];

        $this->assertArrayNotHasKey('deputy_description', $row);
        $this->assertArrayNotHasKey('email_address', $row);
        $this->assertArrayNotHasKey('revoked_reason', $row);
    }

    // ── getById() ────────────────────────────────────────────────────

    public function testGetByIdReturnsOfficer(): void
    {
        // Officer 932 = member 2871 at branch 31, office 14 (Current)
        $result = $this->service->getById($this->admin, 932);

        $this->assertNotNull($result);
        $this->assertSame(932, $result['id']);
    }

    public function testGetByIdIncludesDetailFields(): void
    {
        $result = $this->service->getById($this->admin, 932);

        $this->assertArrayHasKey('deputy_description', $result);
        $this->assertArrayHasKey('email_address', $result);
        $this->assertArrayHasKey('revoked_reason', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('modified', $result);
    }

    public function testGetByIdIncludesBaseKeys(): void
    {
        $result = $this->service->getById($this->admin, 932);

        $expected = [
            'id', 'member_id', 'member_name', 'branch',
            'office_id', 'office_name', 'status',
            'start_on', 'expires_on',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: $key");
        }
    }

    public function testGetByIdReturnsNullForNonexistent(): void
    {
        $result = $this->service->getById($this->admin, 999999);

        $this->assertNull($result);
    }
}
