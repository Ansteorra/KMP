<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ActiveWindowManager\DefaultActiveWindowManager;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\DateTime;

class ActiveWindowManagerTest extends BaseTestCase
{
    protected ?DefaultActiveWindowManager $manager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->manager = new DefaultActiveWindowManager();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(DefaultActiveWindowManager::class, $this->manager);
        $this->assertInstanceOf(ActiveWindowManagerInterface::class, $this->manager);
    }

    public function testStopWithValidMemberRole(): void
    {
        $memberRolesTable = $this->getTableLocator()->get('MemberRoles');

        // Find an active member role from seed data
        $activeRole = $memberRolesTable->find()
            ->where([
                'OR' => [
                    'expires_on >=' => DateTime::now(),
                    'expires_on IS' => null,
                ],
            ])
            ->first();

        if ($activeRole === null) {
            $this->markTestSkipped('No active member roles in seed data');
        }

        $result = $this->manager->stop(
            'MemberRoles',
            $activeRole->id,
            self::ADMIN_MEMBER_ID,
            'Deactivated',
            'Test deactivation',
            DateTime::now(),
        );

        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertTrue($result->isSuccess());

        // Verify the role was expired and revoker set
        $updated = $memberRolesTable->get($activeRole->id);
        $this->assertNotNull($updated->expires_on);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $updated->revoker_id);
    }

    public function testStopWithInvalidEntityThrowsException(): void
    {
        $this->expectException(RecordNotFoundException::class);

        $this->manager->stop(
            'MemberRoles',
            999999,
            self::ADMIN_MEMBER_ID,
            'Deactivated',
            'Test',
            DateTime::now(),
        );
    }

    public function testStartReturnsServiceResult(): void
    {
        // Create a new member role entity for testing
        $memberRolesTable = $this->getTableLocator()->get('MemberRoles');
        $rolesTable = $this->getTableLocator()->get('Roles');
        $role = $rolesTable->find()->first();

        if ($role === null) {
            $this->markTestSkipped('No roles in seed data');
        }

        $entity = $memberRolesTable->newEntity([
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'role_id' => $role->id,
            'entity_type' => 'Direct Grant',
            'approver_id' => self::ADMIN_MEMBER_ID,
            'start_on' => null,
            'expires_on' => null,
        ], ['accessibleFields' => ['*' => true]]);

        $saved = $memberRolesTable->save($entity);
        if (!$saved) {
            $this->markTestSkipped('Could not create test member role');
        }

        $result = $this->manager->start(
            'MemberRoles',
            $saved->id,
            self::ADMIN_MEMBER_ID,
            DateTime::now(),
            DateTime::now()->modify('+1 year'),
            null,
            null,
            false,
        );

        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertTrue($result->isSuccess());

        // Verify the entity was started
        $updated = $memberRolesTable->get($saved->id);
        $this->assertNotNull($updated->start_on);
    }

    public function testStartWithInvalidEntityThrowsException(): void
    {
        $this->expectException(RecordNotFoundException::class);

        $this->manager->start(
            'MemberRoles',
            999999,
            self::ADMIN_MEMBER_ID,
            DateTime::now(),
        );
    }
}
