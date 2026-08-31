<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\MemberRegistrationService;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
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

    private function ensureMembershipCardReuploadWorkflow(): void
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definition = $definitions->find()
            ->where(['slug' => 'membership-card-reupload-request'])
            ->first();
        if ($definition === null) {
            $definition = $definitions->newEntity([
                'name' => 'Membership Card Re-upload Request',
                'slug' => 'membership-card-reupload-request',
                'description' => 'Test fixture for replacement-card notifications.',
                'trigger_type' => 'event',
                'trigger_config' => ['event' => 'Members.MembershipCardReuploadRequested'],
                'entity_type' => 'Members.Members',
                'is_active' => true,
                'execution_mode' => 'ephemeral',
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $definitions->saveOrFail($definition);
        }

        if (!$definition->current_version_id) {
            $definitionJson = json_decode((string)file_get_contents(
                ROOT . '/config/Seeds/WorkflowDefinitions/membership-card-reupload-request.json',
            ), true, 512, JSON_THROW_ON_ERROR);
            $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
            $version = $versions->newEntity([
                'workflow_definition_id' => $definition->id,
                'version_number' => 1,
                'definition' => $definitionJson,
                'canvas_layout' => [],
                'status' => 'published',
                'published_at' => DateTime::now(),
                'published_by' => self::ADMIN_MEMBER_ID,
                'change_notes' => 'Controller test fixture',
                'created_by' => self::ADMIN_MEMBER_ID,
            ]);
            $versions->saveOrFail($version);
            $definition->current_version_id = $version->id;
        }

        $definition->is_active = true;
        $definitions->saveOrFail($definition);
    }

    private function addLegacyMembershipCard(int $memberId): string
    {
        $members = TableRegistry::getTableLocator()->get('Members');
        $members->updateAll(
            [
                'membership_card_document_id' => null,
                'membership_card_path' => 'legacy-membership-card.jpg',
            ],
            ['id' => $memberId],
        );

        $member = $members->get($memberId);
        $this->assertNull($member->membership_card_document_id);
        $this->assertSame('legacy-membership-card.jpg', $member->membership_card_path);

        return 'legacy:' . hash('sha256', 'legacy-membership-card.jpg');
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

    public function testMembershipCardReuploadRequestDispatchesAndClearsCard(): void
    {
        $this->ensureMembershipCardReuploadWorkflow();
        $expectedCardReference = $this->addLegacyMembershipCard(self::TEST_MEMBER_AGATHA_ID);

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched) {
                    $dispatched = true;
                    $this->assertSame('Members.MembershipCardReuploadRequested', $event);
                    $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $context['memberId']);
                    $this->assertNotFalse(filter_var($context['contactEmail'], FILTER_VALIDATE_EMAIL));

                    return [new ServiceResult(true, null, ['instanceId' => 789])];
                });

            return $mock;
        });
        $cardDeleted = false;
        $this->mockServiceClean(MemberRegistrationService::class, function () use (&$cardDeleted) {
            $mock = $this->createMock(MemberRegistrationService::class);
            $mock->method('deleteMembershipCard')
                ->willReturnCallback(function (?int $documentId, ?string $legacyPath) use (&$cardDeleted) {
                    $cardDeleted = true;
                    $this->assertNull($documentId);
                    $this->assertSame('legacy-membership-card.jpg', $legacyPath);

                    return ['success' => true];
                });

            return $mock;
        });

        $this->post(
            '/members/request-membership-card-reupload/' . self::TEST_MEMBER_AGATHA_ID,
            ['expected_card_reference' => $expectedCardReference],
        );

        $this->assertRedirectContains('/members/view/' . self::TEST_MEMBER_AGATHA_ID);
        $this->assertTrue($dispatched);
        $this->assertTrue($cardDeleted);
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertNull($member->membership_card_path);
        $this->assertNull($member->membership_card_document_id);
        $this->assertFlashMessage(
            'The membership card was rejected and the member was asked to upload a new copy.',
            'flash',
        );
    }

    public function testMembershipCardReuploadRequestRollsBackWhenWorkflowFails(): void
    {
        $this->ensureMembershipCardReuploadWorkflow();
        $expectedCardReference = $this->addLegacyMembershipCard(self::TEST_MEMBER_AGATHA_ID);

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function () use (&$dispatched) {
                    $dispatched = true;

                    return [new ServiceResult(false, 'Notification failed')];
                });

            return $mock;
        });
        $cardDeleted = false;
        $this->mockServiceClean(MemberRegistrationService::class, function () use (&$cardDeleted) {
            $mock = $this->createMock(MemberRegistrationService::class);
            $mock->method('deleteMembershipCard')
                ->willReturnCallback(function () use (&$cardDeleted) {
                    $cardDeleted = true;

                    return ['success' => true];
                });

            return $mock;
        });

        $this->post(
            '/members/request-membership-card-reupload/' . self::TEST_MEMBER_AGATHA_ID,
            ['expected_card_reference' => $expectedCardReference],
        );

        $this->assertRedirectContains('/members/view/' . self::TEST_MEMBER_AGATHA_ID);
        $this->assertTrue($dispatched);
        $this->assertFalse($cardDeleted);
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertSame('legacy-membership-card.jpg', $member->membership_card_path);
        $this->assertFlashMessage(
            'The new upload request could not be completed. '
                . 'The existing membership card was kept. Please try again.',
            'flash',
        );
    }

    public function testMembershipCardReuploadRequestDoesNotRestoreReferenceWhenCleanupFails(): void
    {
        $this->ensureMembershipCardReuploadWorkflow();
        $expectedCardReference = $this->addLegacyMembershipCard(self::TEST_MEMBER_AGATHA_ID);

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturn([new ServiceResult(true, null, ['instanceId' => 790])]);

            return $mock;
        });
        $this->mockServiceClean(MemberRegistrationService::class, function () {
            $mock = $this->createMock(MemberRegistrationService::class);
            $mock->method('deleteMembershipCard')
                ->willReturn([
                    'success' => false,
                    'message' => 'Document record cleanup failed after file deletion.',
                ]);

            return $mock;
        });

        $this->post(
            '/members/request-membership-card-reupload/' . self::TEST_MEMBER_AGATHA_ID,
            ['expected_card_reference' => $expectedCardReference],
        );

        $this->assertRedirectContains('/members/view/' . self::TEST_MEMBER_AGATHA_ID);
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertNull($member->membership_card_path);
        $this->assertNull($member->membership_card_document_id);
        $this->assertFlashMessage(
            'The request was sent, but the old stored card could not be deleted automatically.',
            'flash',
        );
        $this->assertFlashMessage(
            'The membership card was rejected and the member was asked to upload a new copy.',
            'flash',
        );
    }

    public function testMembershipCardReuploadRequestRejectsStaleCardReference(): void
    {
        $this->ensureMembershipCardReuploadWorkflow();
        $expectedCardReference = $this->addLegacyMembershipCard(self::TEST_MEMBER_AGATHA_ID);
        TableRegistry::getTableLocator()->get('Members')->updateAll(
            ['membership_card_path' => 'replacement-membership-card.jpg'],
            ['id' => self::TEST_MEMBER_AGATHA_ID],
        );

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function () use (&$dispatched) {
                    $dispatched = true;

                    return [new ServiceResult(true)];
                });

            return $mock;
        });
        $cardDeleted = false;
        $this->mockServiceClean(MemberRegistrationService::class, function () use (&$cardDeleted) {
            $mock = $this->createMock(MemberRegistrationService::class);
            $mock->method('deleteMembershipCard')
                ->willReturnCallback(function () use (&$cardDeleted) {
                    $cardDeleted = true;

                    return ['success' => true];
                });

            return $mock;
        });

        $this->post(
            '/members/request-membership-card-reupload/' . self::TEST_MEMBER_AGATHA_ID,
            ['expected_card_reference' => $expectedCardReference],
        );

        $this->assertRedirectContains('/members/view/' . self::TEST_MEMBER_AGATHA_ID);
        $this->assertFalse($dispatched);
        $this->assertFalse($cardDeleted);
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertSame('replacement-membership-card.jpg', $member->membership_card_path);
        $this->assertFlashMessage(
            'The membership card changed after this form was opened. '
                . 'Review the current upload before requesting a new copy.',
            'flash',
        );
    }

    public function testVerificationModalUsesSeparateConfirmedReuploadForm(): void
    {
        $this->addLegacyMembershipCard(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/members/view/' . self::TEST_MEMBER_AGATHA_ID);

        $this->assertResponseOk();
        $this->assertResponseContains('Request new upload');
        $this->assertResponseContains('btn btn-outline-danger');
        $this->assertResponseContains(
            '/members/request-membership-card-reupload/' . self::TEST_MEMBER_AGATHA_ID,
        );
        $this->assertResponseContains('name="expected_card_reference"');
        $this->assertResponseContains(
            'data-confirmation-submit-selector-value="#membershipCardReuploadRequestForm_'
                . self::TEST_MEMBER_AGATHA_ID . '"',
        );
        $this->assertResponseContains('data-action="confirmation#confirm"');
    }
}
