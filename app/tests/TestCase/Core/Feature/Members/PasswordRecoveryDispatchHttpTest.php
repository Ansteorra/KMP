<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Controller\MembersController;
use App\Mailer\KMPMailer;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use RuntimeException;

class PasswordRecoveryDispatchHttpTest extends HttpIntegrationTestCase
{
    private mixed $originalQueueSetting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalQueueSetting = Configure::read('Email.UseQueue');
        Configure::write('Email.UseQueue', 'no');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    protected function tearDown(): void
    {
        $this->removeMockService(MembersController::class);
        Configure::write('Email.UseQueue', $this->originalQueueSetting);
        parent::tearDown();
    }

    public function testRecoveryAlwaysEnqueuesWhenGeneralMailQueueIsDisabled(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $controller = $this->getMockBuilder(MembersController::class)
            ->setConstructorArgs([new ServerRequest(), 'Members'])
            ->onlyMethods(['queueMail', 'sendMailNow', 'queueMailJob'])
            ->getMock();
        // This detects the forced asynchronous path even inside transactional test fixtures.
        $controller->expects($this->never())->method('queueMail');
        $controller->expects($this->never())->method('sendMailNow');
        $controller->expects($this->once())->method('queueMailJob')->with($this->callback(
            static fn(array $data): bool => $data['class'] === KMPMailer::class
                && $data['action'] === 'sendFromTemplate'
                && $data['vars']['to'] === $member->email_address
                && $data['vars']['_templateId'] === 'password-reset'
                && preg_match('#/reset-password/[a-f0-9]{64}$#', $data['vars']['passwordResetUrl']) === 1,
        ));
        $this->mockService(MembersController::class, static function () use ($controller) {
            $request = Router::getRequest();
            assert($request instanceof ServerRequest);
            $controller->setRequest($request);

            return $controller;
        });
        $this->post('/members/forgot-password', ['email_address' => $member->email_address]);
        $this->assertRedirectContains('/members/login');
        $this->assertFlashMessage('If your email is on file, a password reset link has been sent.');
    }

    public function testQueueFailureKeepsKnownAndUnknownRecoveryResponsesEquivalent(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $controller = $this->getMockBuilder(MembersController::class)
            ->setConstructorArgs([new ServerRequest(), 'Members'])
            ->onlyMethods(['queueMail', 'sendMailNow', 'queueMailJob'])
            ->getMock();
        $controller->expects($this->never())->method('queueMail');
        $controller->expects($this->never())->method('sendMailNow');
        $controller->expects($this->once())->method('queueMailJob')
            ->willThrowException(new RuntimeException('synthetic-private-queue-diagnostic'));
        $this->mockService(MembersController::class, static function () use ($controller) {
            $request = Router::getRequest();
            assert($request instanceof ServerRequest);
            $controller->setRequest($request);

            return $controller;
        });
        $this->post('/members/forgot-password', ['email_address' => $member->email_address]);
        $this->assertRedirectContains('/members/login');
        $this->assertFlashMessage('If your email is on file, a password reset link has been sent.');
        $this->assertResponseNotContains('synthetic-private-queue-diagnostic');
        $knownLocation = $this->_response->getHeaderLine('Location');
        $this->session([]);
        $this->post('/members/forgot-password', ['email_address' => 'unknown-recovery-dispatch@example.invalid']);
        $this->assertResponseCode(302);
        $this->assertHeader('Location', $knownLocation);
        $this->assertFlashMessage('If your email is on file, a password reset link has been sent.');
    }
}
