<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Services\Api;

use App\Model\Entity\Member;
use App\Services\AuthorizationService;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\MapResolver;
use Officers\Services\Api\DefaultReadOnlyOfficeService;
use Officers\Services\Api\ReadOnlyOfficeServiceInterface;

class DefaultReadOnlyOfficeServiceTest extends BaseTestCase
{
    protected DefaultReadOnlyOfficeService $service;
    protected Member $admin;
    protected AuthorizationService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $resolver = new MapResolver();
        $resolver->map(
            \Officers\Model\Entity\Office::class,
            \Officers\Policy\OfficePolicy::class,
        );
        $this->authService = new AuthorizationService($resolver);

        $members = $this->getTableLocator()->get('Members');
        $this->admin = $members->get(self::ADMIN_MEMBER_ID);
        $this->admin->getPermissions();
        $this->admin->setAuthorization($this->authService);

        $this->service = new DefaultReadOnlyOfficeService();
    }

    // ── Instantiation ────────────────────────────────────────────────

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ReadOnlyOfficeServiceInterface::class, $this->service);
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

    public function testListReturnsOffices(): void
    {
        $result = $this->service->list($this->admin, [], 1, 100);

        $this->assertNotEmpty($result['data']);
        $this->assertGreaterThanOrEqual(10, count($result['data']));
    }

    public function testListRowHasExpectedKeys(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $row = $result['data'][0];

        $expected = [
            'id', 'name', 'department_id', 'department_name',
            'requires_warrant', 'required_office', 'only_one_per_branch',
            'can_skip_report', 'term_length', 'created', 'modified',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $row, "Missing key: $key");
        }
    }

    public function testListRowBooleanTypes(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $row = $result['data'][0];

        $this->assertIsBool($row['requires_warrant']);
        $this->assertIsBool($row['required_office']);
        $this->assertIsBool($row['only_one_per_branch']);
        $this->assertIsBool($row['can_skip_report']);
    }

    public function testListRowTermLengthIsInt(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $row = $result['data'][0];

        $this->assertIsInt($row['term_length']);
    }

    public function testListOrderedByNameAsc(): void
    {
        $result = $this->service->list($this->admin, [], 1, 100);
        $names = array_column($result['data'], 'name');
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);

        $this->assertSame($sorted, $names);
    }

    public function testListFilterByDepartmentId(): void
    {
        // Department 3 = Marshallate
        $result = $this->service->list($this->admin, ['department_id' => 3], 1, 100);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertSame(3, $row['department_id']);
        }
    }

    public function testListFilterByRequiresWarrantTrue(): void
    {
        $result = $this->service->list($this->admin, ['requires_warrant' => '1'], 1, 100);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertTrue($row['requires_warrant']);
        }
    }

    public function testListFilterByRequiresWarrantFalse(): void
    {
        $result = $this->service->list($this->admin, ['requires_warrant' => '0'], 1, 100);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertFalse($row['requires_warrant']);
        }
    }

    public function testListSearchByName(): void
    {
        $result = $this->service->list($this->admin, ['search' => 'Marshal'], 1, 100);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertStringContainsStringIgnoringCase('Marshal', $row['name']);
        }
    }

    public function testListSearchNoResults(): void
    {
        $result = $this->service->list($this->admin, ['search' => 'ZZZ_NONEXISTENT_ZZZ'], 1, 25);

        $this->assertEmpty($result['data']);
        $this->assertSame(0, $result['meta']['pagination']['total']);
    }

    public function testListPaginationLimitsResults(): void
    {
        $result = $this->service->list($this->admin, [], 1, 3);

        $this->assertCount(3, $result['data']);
    }

    public function testListPaginationPage2NoOverlap(): void
    {
        $page1 = $this->service->list($this->admin, [], 1, 5);
        $page2 = $this->service->list($this->admin, [], 2, 5);

        $this->assertNotEmpty($page2['data']);
        $page1Ids = array_column($page1['data'], 'id');
        $page2Ids = array_column($page2['data'], 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    // ── getById() ────────────────────────────────────────────────────

    public function testGetByIdReturnsOffice(): void
    {
        // Office 14 = Local MoAS
        $result = $this->service->getById($this->admin, 14);

        $this->assertNotNull($result);
        $this->assertSame(14, $result['id']);
        $this->assertSame('Local MoAS', $result['name']);
    }

    public function testGetByIdIncludesRelations(): void
    {
        $result = $this->service->getById($this->admin, 14);

        $this->assertArrayHasKey('reports_to', $result);
        $this->assertArrayHasKey('deputy_to', $result);
        $this->assertArrayHasKey('grants_role', $result);
    }

    public function testGetByIdRelationStructure(): void
    {
        // Office 3 = Kingdom Rapier Marshal has reports_to and deputy_to
        $result = $this->service->getById($this->admin, 3);

        $this->assertNotNull($result);
        if ($result['reports_to'] !== null) {
            $this->assertArrayHasKey('id', $result['reports_to']);
            $this->assertArrayHasKey('name', $result['reports_to']);
        }
        if ($result['deputy_to'] !== null) {
            $this->assertArrayHasKey('id', $result['deputy_to']);
            $this->assertArrayHasKey('name', $result['deputy_to']);
        }
        if ($result['grants_role'] !== null) {
            $this->assertArrayHasKey('id', $result['grants_role']);
            $this->assertArrayHasKey('name', $result['grants_role']);
        }
    }

    public function testGetByIdReturnsNullForNonexistent(): void
    {
        $result = $this->service->getById($this->admin, 999999);

        $this->assertNull($result);
    }

    public function testGetByIdContainsBaseFormatKeys(): void
    {
        $result = $this->service->getById($this->admin, 14);

        $expected = [
            'id', 'name', 'department_id', 'department_name',
            'requires_warrant', 'required_office', 'only_one_per_branch',
            'can_skip_report', 'term_length', 'created', 'modified',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: $key");
        }
    }
}
