<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Services\Api;

use App\Test\TestCase\BaseTestCase;
use Officers\Services\Api\OfficersBranchApiDataProvider;

class OfficersBranchApiDataProviderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
    }

    // ── provide() ────────────────────────────────────────────────────

    public function testProvideReturnsOfficersKey(): void
    {
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_LOCAL_ID);
        $result = OfficersBranchApiDataProvider::provide('Branches', 'view', $branch);

        $this->assertArrayHasKey('officers', $result);
        $this->assertIsArray($result['officers']);
    }

    public function testProvideReturnsCurrentOfficers(): void
    {
        // Branch 14 (Shire of Adlersruhe) has current officers
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_LOCAL_ID);
        $result = OfficersBranchApiDataProvider::provide('Branches', 'view', $branch);

        $this->assertNotEmpty($result['officers']);
    }

    public function testProvideOfficerRowStructure(): void
    {
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_LOCAL_ID);
        $result = OfficersBranchApiDataProvider::provide('Branches', 'view', $branch);

        $this->assertNotEmpty($result['officers']);
        $officer = $result['officers'][0];

        $this->assertArrayHasKey('office', $officer);
        $this->assertArrayHasKey('department', $officer);
        $this->assertArrayHasKey('member', $officer);
        $this->assertArrayHasKey('email_address', $officer);
        $this->assertArrayHasKey('start_on', $officer);
        $this->assertArrayHasKey('expires_on', $officer);
    }

    public function testProvideOfficerMemberStructure(): void
    {
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_LOCAL_ID);
        $result = OfficersBranchApiDataProvider::provide('Branches', 'view', $branch);

        $this->assertNotEmpty($result['officers']);
        $member = $result['officers'][0]['member'];

        $this->assertArrayHasKey('id', $member);
        $this->assertArrayHasKey('sca_name', $member);
    }

    public function testProvideReturnsEmptyForBranchWithNoOfficers(): void
    {
        $entity = new \stdClass();
        $entity->id = 999999;
        $entity->type = 'Barony';

        $result = OfficersBranchApiDataProvider::provide('Branches', 'view', $entity);

        $this->assertArrayHasKey('officers', $result);
        $this->assertEmpty($result['officers']);
    }

    public function testProvideOfficesSortedByNameAsc(): void
    {
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_LOCAL_ID);
        $result = OfficersBranchApiDataProvider::provide('Branches', 'view', $branch);

        if (count($result['officers']) > 1) {
            $officeNames = array_column($result['officers'], 'office');
            $sorted = $officeNames;
            sort($sorted, SORT_STRING | SORT_FLAG_CASE);
            $this->assertSame($sorted, $officeNames);
        } else {
            $this->assertNotEmpty($result['officers']);
        }
    }

    public function testProvideAcceptsAnyControllerAction(): void
    {
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_LOCAL_ID);
        $result = OfficersBranchApiDataProvider::provide('SomeController', 'someAction', $branch);

        $this->assertArrayHasKey('officers', $result);
    }

    public function testProvideOfficerOfficeNameIsString(): void
    {
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_LOCAL_ID);
        $result = OfficersBranchApiDataProvider::provide('Branches', 'view', $branch);

        $this->assertNotEmpty($result['officers']);
        foreach ($result['officers'] as $officer) {
            if ($officer['office'] !== null) {
                $this->assertIsString($officer['office']);
            }
        }
    }
}
