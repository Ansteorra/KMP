<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Closure;
use Exception;
use Laminas\Diactoros\UploadedFile;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use ReflectionProperty;

/**
 * Tests workflow-backed registration dispatch in MembersController.
 *
 * @uses \App\Controller\MembersController
 */
class MembersWorkflowDispatchTest extends HttpIntegrationTestCase
{
    private array $mockedServiceKeys = [];

    private int $memberBranchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();

        $branch = TableRegistry::getTableLocator()->get('Branches')
            ->find()
            ->where(['can_have_members' => true])
            ->firstOrFail();
        $this->memberBranchId = (int)$branch->id;

        $this->mockServiceClean(CakeContainerInterface::class, function () {
            return $this->createMock(CakeContainerInterface::class);
        });
    }

    public function modifyContainer(EventInterface $event, PsrContainerInterface $container): void
    {
        parent::modifyContainer($event, $container);

        foreach ($this->mockedServiceKeys as $key) {
            if ($container->has($key)) {
                try {
                    $def = $container->extend($key);
                    $ref = new ReflectionProperty($def, 'arguments');
                    $ref->setAccessible(true);
                    $ref->setValue($def, []);
                } catch (Exception $e) {
                    // Definition may not exist in aggregate - ignore.
                }
            }
        }
    }

    protected function mockServiceClean(string $class, Closure $factory): void
    {
        $this->mockService($class, $factory);
        $this->mockedServiceKeys[] = $class;
    }

    private function ensureActiveWorkflow(): void
    {
        TableRegistry::getTableLocator()->get('WorkflowDefinitions')
            ->updateAll(['is_active' => true], ['slug' => 'member-registration']);
    }

    private function deactivateWorkflow(): void
    {
        TableRegistry::getTableLocator()->get('WorkflowDefinitions')
            ->updateAll(['is_active' => false], ['slug' => 'member-registration']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getAddData(): array
    {
        return [
            'sca_name' => 'Workflow Add ' . uniqid(),
            'email_address' => 'workflow_add_' . uniqid() . '@example.com',
            'password' => 'placeholder_password',
            'first_name' => 'Workflow',
            'last_name' => 'Add',
            'street_address' => '123 Test St',
            'city' => 'Workflowville',
            'state' => 'TX',
            'zip' => '75001',
            'phone_number' => '555-555-5555',
            'birth_month' => 1,
            'birth_year' => 1990,
            'branch_id' => $this->memberBranchId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getRegisterData(): array
    {
        return [
            'sca_name' => 'Workflow Register ' . uniqid(),
            'email_address' => 'workflow_register_' . uniqid() . '@example.com',
            'first_name' => 'Workflow',
            'last_name' => 'Register',
            'street_address' => '123 Test St',
            'city' => 'Workflowville',
            'state' => 'TX',
            'zip' => '75001',
            'phone_number' => '555-555-5555',
            'birth_month' => 1,
            'birth_year' => 1990,
            'branch_id' => $this->memberBranchId,
            'member_card' => new UploadedFile('php://temp', 0, UPLOAD_ERR_OK, '', 'application/octet-stream'),
        ];
    }

    public function testAddDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow();

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched) {
                    $dispatched = true;
                    $this->assertSame('Members.Registered', $event);
                    $this->assertSame('admin-add', $context['source']);
                    $this->assertArrayHasKey('memberId', $context);

                    $member = TableRegistry::getTableLocator()->get('Members')->get((int)$context['memberId']);
                    $this->assertSame((int)$context['memberId'], (int)$member->id);

                    return [new ServiceResult(true, null, ['instanceId' => 123])];
                });

            return $mock;
        });

        $this->post('/members/add', $this->getAddData());

        $this->assertRedirectContains('/members/view/');
        $this->assertTrue($dispatched);
        $this->assertFlashMessage(
            "The Member has been saved. Please ask the member to use 'forgot password' to set their password.",
            'flash',
        );
    }

    public function testAddFlashesErrorWhenWorkflowUnavailable(): void
    {
        $this->deactivateWorkflow();

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/members/add', $this->getAddData());

        $this->assertResponseOk();
        $this->assertFlashMessage('The member registration workflow is not currently available.', 'flash');
    }

    public function testRegisterDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow();
        $this->logout();

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched) {
                    $dispatched = true;
                    $this->assertSame('Members.Registered', $event);
                    $this->assertSame('self-register', $context['source']);
                    $this->assertArrayHasKey('memberId', $context);

                    $member = TableRegistry::getTableLocator()->get('Members')->get((int)$context['memberId']);
                    $this->assertSame((int)$context['memberId'], (int)$member->id);

                    return [new ServiceResult(true, null, ['instanceId' => 456])];
                });

            return $mock;
        });

        $this->post('/members/register', $this->getRegisterData());

        $this->assertRedirectContains('/members/login');
        $this->assertTrue($dispatched);
        $this->assertFlashMessage(
            'Your registration has been submitted. Please check your email for a link to set up your password.',
            'flash',
        );
    }
}
