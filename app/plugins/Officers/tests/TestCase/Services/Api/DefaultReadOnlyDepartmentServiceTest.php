<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Services\Api;

use App\Model\Entity\Member;
use App\Services\AuthorizationService;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\MapResolver;
use Officers\Services\Api\DefaultReadOnlyDepartmentService;
use Officers\Services\Api\ReadOnlyDepartmentServiceInterface;

class DefaultReadOnlyDepartmentServiceTest extends BaseTestCase
{
    protected DefaultReadOnlyDepartmentService $service;
    protected Member $admin;
    protected AuthorizationService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $resolver = new MapResolver();
        $resolver->map(
            \Officers\Model\Entity\Department::class,
            \Officers\Policy\DepartmentPolicy::class,
        );
        $this->authService = new AuthorizationService($resolver);

        $members = $this->getTableLocator()->get('Members');
        $this->admin = $members->get(self::ADMIN_MEMBER_ID);
        $this->admin->getPermissions();
        $this->admin->setAuthorization($this->authService);

        $this->service = new DefaultReadOnlyDepartmentService();
    }

    // ── Instantiation ────────────────────────────────────────────────

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ReadOnlyDepartmentServiceInterface::class, $this->service);
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

    public function testListReturnsDepartments(): void
    {
        $result = $this->service->list($this->admin, [], 1, 100);

        $this->assertNotEmpty($result['data']);
        $this->assertGreaterThanOrEqual(5, count($result['data']));
    }

    public function testListRowHasExpectedKeys(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $row = $result['data'][0];

        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('domain', $row);
        $this->assertArrayHasKey('created', $row);
        $this->assertArrayHasKey('modified', $row);
    }

    public function testListOrderedByNameAsc(): void
    {
        $result = $this->service->list($this->admin, [], 1, 100);
        $names = array_column($result['data'], 'name');
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);

        $this->assertSame($sorted, $names);
    }

    public function testListPaginationLimitsResults(): void
    {
        $result = $this->service->list($this->admin, [], 1, 2);

        $this->assertCount(2, $result['data']);
        $this->assertGreaterThan(2, $result['meta']['pagination']['total']);
    }

    public function testListPaginationPage2(): void
    {
        $page1 = $this->service->list($this->admin, [], 1, 2);
        $page2 = $this->service->list($this->admin, [], 2, 2);

        $this->assertNotEmpty($page2['data']);
        $page1Ids = array_column($page1['data'], 'id');
        $page2Ids = array_column($page2['data'], 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    public function testListSearchFilterByName(): void
    {
        $result = $this->service->list($this->admin, ['search' => 'Seneschallate'], 1, 25);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertStringContainsStringIgnoringCase('Seneschallate', $row['name']);
        }
    }

    public function testListSearchNoResults(): void
    {
        $result = $this->service->list($this->admin, ['search' => 'ZZZ_NONEXISTENT_ZZZ'], 1, 25);

        $this->assertEmpty($result['data']);
        $this->assertSame(0, $result['meta']['pagination']['total']);
        $this->assertSame(1, $result['meta']['pagination']['total_pages']);
    }

    public function testListTotalPagesCalculation(): void
    {
        $result = $this->service->list($this->admin, [], 1, 5);
        $pagination = $result['meta']['pagination'];

        $expected = (int)max(1, ceil($pagination['total'] / 5));
        $this->assertSame($expected, $pagination['total_pages']);
    }

    // ── getById() ────────────────────────────────────────────────────

    public function testGetByIdReturnsDepartment(): void
    {
        $result = $this->service->getById($this->admin, 1);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['id']);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('domain', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('modified', $result);
    }

    public function testGetByIdIncludesOffices(): void
    {
        $result = $this->service->getById($this->admin, 1);

        $this->assertArrayHasKey('offices', $result);
        $this->assertIsArray($result['offices']);
    }

    public function testGetByIdOfficesHaveIdAndName(): void
    {
        // Department 3 = Marshallate, which has offices
        $result = $this->service->getById($this->admin, 3);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result['offices']);
        foreach ($result['offices'] as $office) {
            $this->assertArrayHasKey('id', $office);
            $this->assertArrayHasKey('name', $office);
        }
    }

    public function testGetByIdReturnsNullForNonexistent(): void
    {
        $result = $this->service->getById($this->admin, 999999);

        $this->assertNull($result);
    }

    public function testGetByIdNameMatchesList(): void
    {
        $listResult = $this->service->list($this->admin, [], 1, 100);
        $firstFromList = $listResult['data'][0];

        $detail = $this->service->getById($this->admin, $firstFromList['id']);

        $this->assertSame($firstFromList['name'], $detail['name']);
    }
}
