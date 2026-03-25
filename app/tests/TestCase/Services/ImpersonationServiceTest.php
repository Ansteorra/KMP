<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\ImpersonationService;
use App\Test\TestCase\BaseTestCase;
use Cake\Http\Session;

class ImpersonationServiceTest extends BaseTestCase
{
    protected ?ImpersonationService $service = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->service = new ImpersonationService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(ImpersonationService::class, $this->service);
    }

    public function testSessionKeyConstant(): void
    {
        $this->assertEquals('Impersonation', ImpersonationService::SESSION_KEY);
    }

    public function testIsActiveReturnsFalseWhenNoImpersonation(): void
    {
        $session = new Session();
        $this->assertFalse($this->service->isActive($session));
    }

    public function testGetStateReturnsNullWhenNoImpersonation(): void
    {
        $session = new Session();
        $this->assertNull($this->service->getState($session));
    }

    public function testStartSetsSessionState(): void
    {
        $session = new Session();
        $membersTable = $this->getTableLocator()->get('Members');

        $impersonator = $membersTable->get(self::ADMIN_MEMBER_ID);
        $target = $membersTable->get(self::TEST_MEMBER_BRYCE_ID);

        $state = $this->service->start($session, $impersonator, $target);

        $this->assertIsArray($state);
        $this->assertTrue($state['active']);
        $this->assertEquals((int)$impersonator->id, $state['impersonator_id']);
        $this->assertEquals((string)$impersonator->sca_name, $state['impersonator_name']);
        $this->assertEquals((int)$target->id, $state['impersonated_member_id']);
        $this->assertEquals((string)$target->sca_name, $state['impersonated_member_name']);
        $this->assertArrayHasKey('started_at', $state);
    }

    public function testIsActiveReturnsTrueAfterStart(): void
    {
        $session = new Session();
        $membersTable = $this->getTableLocator()->get('Members');

        $impersonator = $membersTable->get(self::ADMIN_MEMBER_ID);
        $target = $membersTable->get(self::TEST_MEMBER_BRYCE_ID);

        $this->service->start($session, $impersonator, $target);
        $this->assertTrue($this->service->isActive($session));
    }

    public function testGetStateReturnsStateAfterStart(): void
    {
        $session = new Session();
        $membersTable = $this->getTableLocator()->get('Members');

        $impersonator = $membersTable->get(self::ADMIN_MEMBER_ID);
        $target = $membersTable->get(self::TEST_MEMBER_BRYCE_ID);

        $this->service->start($session, $impersonator, $target);

        $state = $this->service->getState($session);
        $this->assertNotNull($state);
        $this->assertTrue($state['active']);
        $this->assertEquals((int)$impersonator->id, $state['impersonator_id']);
    }

    public function testStopClearsSession(): void
    {
        $session = new Session();
        $membersTable = $this->getTableLocator()->get('Members');

        $impersonator = $membersTable->get(self::ADMIN_MEMBER_ID);
        $target = $membersTable->get(self::TEST_MEMBER_BRYCE_ID);

        $this->service->start($session, $impersonator, $target);
        $this->assertTrue($this->service->isActive($session));

        $result = $this->service->stop($session);

        $this->assertIsArray($result);
        $this->assertFalse($this->service->isActive($session));
        $this->assertNull($this->service->getState($session));
    }

    public function testStopReturnsNullWhenNotActive(): void
    {
        $session = new Session();

        $result = $this->service->stop($session);
        $this->assertNull($result);
    }

    public function testStopReturnsPreviousState(): void
    {
        $session = new Session();
        $membersTable = $this->getTableLocator()->get('Members');

        $impersonator = $membersTable->get(self::ADMIN_MEMBER_ID);
        $target = $membersTable->get(self::TEST_MEMBER_BRYCE_ID);

        $originalState = $this->service->start($session, $impersonator, $target);
        $stoppedState = $this->service->stop($session);

        $this->assertEquals($originalState['impersonator_id'], $stoppedState['impersonator_id']);
        $this->assertEquals($originalState['impersonated_member_id'], $stoppedState['impersonated_member_id']);
    }

    public function testStartLogsToImpersonationSessionLogs(): void
    {
        $session = new Session();
        $membersTable = $this->getTableLocator()->get('Members');

        $impersonator = $membersTable->get(self::ADMIN_MEMBER_ID);
        $target = $membersTable->get(self::TEST_MEMBER_BRYCE_ID);

        $this->service->start($session, $impersonator, $target);

        $logsTable = $this->getTableLocator()->get('ImpersonationSessionLogs');
        $log = $logsTable->find()
            ->where([
                'impersonator_id' => $impersonator->id,
                'impersonated_member_id' => $target->id,
                'event' => 'start',
            ])
            ->first();

        $this->assertNotNull($log, 'Impersonation start event should be logged');
    }

    public function testGetStateReturnsNullForNonActiveState(): void
    {
        $session = new Session();
        // Write a state manually with active=false
        $session->write(ImpersonationService::SESSION_KEY, [
            'active' => false,
            'impersonator_id' => 1,
        ]);

        $state = $this->service->getState($session);
        $this->assertNull($state);
    }
}
