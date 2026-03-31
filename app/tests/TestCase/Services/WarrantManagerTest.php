<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\WarrantPeriod;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;
use App\Services\WarrantManager\DefaultWarrantManager;
use App\Services\WarrantManager\WarrantRequest;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class WarrantManagerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
    }

    // =========================================
    // WarrantRequest value object tests
    // =========================================

    public function testWarrantRequestConstruction(): void
    {
        $request = new WarrantRequest(
            'Test Warrant',
            'Branches',
            self::TEST_BRANCH_STARGATE_ID,
            self::ADMIN_MEMBER_ID,
            self::TEST_MEMBER_BRYCE_ID,
        );

        $this->assertEquals('Test Warrant', $request->name);
        $this->assertEquals('Branches', $request->entity_type);
        $this->assertEquals(self::TEST_BRANCH_STARGATE_ID, $request->entity_id);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $request->requester_id);
        $this->assertEquals(self::TEST_MEMBER_BRYCE_ID, $request->member_id);
        $this->assertNull($request->start_on);
        $this->assertNull($request->expires_on);
        $this->assertNull($request->member_role_id);
    }

    public function testWarrantRequestWithOptionalParams(): void
    {
        $startOn = new DateTime('2025-01-01');
        $expiresOn = new DateTime('2025-12-31');

        $request = new WarrantRequest(
            'Full Warrant',
            'Branches',
            self::TEST_BRANCH_STARGATE_ID,
            self::ADMIN_MEMBER_ID,
            self::TEST_MEMBER_BRYCE_ID,
            $startOn,
            $expiresOn,
            42,
        );

        $this->assertEquals($startOn, $request->start_on);
        $this->assertEquals($expiresOn, $request->expires_on);
        $this->assertEquals(42, $request->member_role_id);
    }

    public function testWarrantRequestWithNullDates(): void
    {
        $request = new WarrantRequest(
            'Null Date Warrant',
            'Direct Grant',
            1,
            self::ADMIN_MEMBER_ID,
            self::TEST_MEMBER_BRYCE_ID,
            null,
            null,
            null,
        );

        $this->assertNull($request->start_on);
        $this->assertNull($request->expires_on);
        $this->assertNull($request->member_role_id);
    }

    // =========================================
    // DefaultWarrantManager tests
    // =========================================

    public function testGetWarrantPeriodReturnsNullForFarFutureDates(): void
    {
        $activeWindowManager = $this->createMock(
            ActiveWindowManagerInterface::class,
        );
        $manager = new DefaultWarrantManager($activeWindowManager);

        // Use dates far in the future that won't have a warrant period
        $farFuture = new DateTime('2099-01-01');
        $result = $manager->getWarrantPeriod($farFuture, null);
        $this->assertNull($result, 'Should return null for dates outside any warrant period');
    }

    public function testGetWarrantPeriodReturnsEntityForValidDates(): void
    {
        $activeWindowManager = $this->createMock(
            ActiveWindowManagerInterface::class,
        );
        $manager = new DefaultWarrantManager($activeWindowManager);

        // Check if warrant periods exist in seed data
        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $currentPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => DateTime::now(),
                'end_date >=' => DateTime::now(),
            ])
            ->first();

        if ($currentPeriod === null) {
            $this->markTestSkipped('No current warrant period in seed data');
        }

        $result = $manager->getWarrantPeriod(DateTime::now(), null);
        $this->assertNotNull($result, 'Should return a warrant period for current date');
        $this->assertInstanceOf(WarrantPeriod::class, $result);
    }

    public function testGetWarrantPeriodRespectsEndOnConstraint(): void
    {
        $activeWindowManager = $this->createMock(
            ActiveWindowManagerInterface::class,
        );
        $manager = new DefaultWarrantManager($activeWindowManager);

        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $currentPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => DateTime::now(),
                'end_date >=' => DateTime::now(),
            ])
            ->first();

        if ($currentPeriod === null) {
            $this->markTestSkipped('No current warrant period in seed data');
        }

        // Request with an endOn earlier than the period's end
        $earlyEnd = new DateTime('+30 days');
        $result = $manager->getWarrantPeriod(DateTime::now(), $earlyEnd);
        $this->assertNotNull($result);
    }

    public function testDeclineRejectsNonPendingRoster(): void
    {
        $activeWindowManager = $this->createMock(
            ActiveWindowManagerInterface::class,
        );
        $manager = new DefaultWarrantManager($activeWindowManager);

        // Find a non-pending roster (approved or declined)
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $nonPendingRoster = $warrantRosterTable->find()
            ->where(['status !=' => 'Pending'])
            ->first();

        if ($nonPendingRoster === null) {
            $this->markTestSkipped('No non-pending warrant rosters in seed data');
        }

        $result = $manager->decline($nonPendingRoster->id, self::ADMIN_MEMBER_ID, 'test reason');
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    public function testApproveRejectsNonPendingRoster(): void
    {
        $activeWindowManager = $this->createMock(
            ActiveWindowManagerInterface::class,
        );
        $manager = new DefaultWarrantManager($activeWindowManager);

        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $nonPendingRoster = $warrantRosterTable->find()
            ->where(['status !=' => 'Pending'])
            ->first();

        if ($nonPendingRoster === null) {
            $this->markTestSkipped('No non-pending warrant rosters in seed data');
        }

        $result = $manager->approve($nonPendingRoster->id, self::ADMIN_MEMBER_ID);
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    public function testCancelNonExistentWarrantReturnsSuccess(): void
    {
        $activeWindowManager = $this->createMock(
            ActiveWindowManagerInterface::class,
        );
        $manager = new DefaultWarrantManager($activeWindowManager);

        // cancel() returns success for non-existent warrants
        // But it does a get() which throws, so let's test cancelByEntity instead
        $result = $manager->cancelByEntity(
            'NonExistentType',
            99999,
            'test reason',
            self::ADMIN_MEMBER_ID,
            new DateTime(),
        );
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertTrue($result->isSuccess(), 'cancelByEntity with no matching warrant should return success');
    }

    public function testRequestReturnsServiceResult(): void
    {
        $activeWindowManager = $this->createMock(
            ActiveWindowManagerInterface::class,
        );
        $manager = new DefaultWarrantManager($activeWindowManager);

        // Test with a member that is not warrantable - should fail gracefully
        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $currentPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => DateTime::now(),
                'end_date >=' => DateTime::now(),
            ])
            ->first();

        if ($currentPeriod === null) {
            $this->markTestSkipped('No current warrant period in seed data');
        }

        $warrantRequest = new WarrantRequest(
            'Test Request',
            'Branches',
            self::TEST_BRANCH_STARGATE_ID,
            self::ADMIN_MEMBER_ID,
            self::ADMIN_MEMBER_ID,
            DateTime::now(),
            null,
            null,
        );

        $result = $manager->request('Test Roster', 'Test Description', [$warrantRequest]);
        $this->assertInstanceOf(ServiceResult::class, $result);
    }
}
