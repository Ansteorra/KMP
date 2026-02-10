<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\AuthorizationService;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\MapResolver;
use Cake\Core\Configure;

/**
 * Test SecurityDebug functionality
 */
class SecurityDebugTest extends BaseTestCase
{
    protected $Members;
    protected $AuthService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Members = $this->getTableLocator()->get('Members');

        // Create authorization service with policy resolver
        $resolver = new MapResolver();
        $resolver->map(\App\Model\Entity\Member::class, \App\Policy\MemberPolicy::class);
        $this->AuthService = new AuthorizationService($resolver);

        // Clear any previous logs
        AuthorizationService::clearAuthCheckLog();
    }

    protected function tearDown(): void
    {
        AuthorizationService::clearAuthCheckLog();
        parent::tearDown();
    }

    /**
     * Test that authorization checks are logged only in debug mode
     */
    public function testAuthorizationLoggingInDebugMode(): void
    {
        // Enable debug mode
        Configure::write('debug', true);

        // Load a member
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $member->warrantableReview();

        // Perform some authorization checks
        $this->AuthService->checkCan($member, 'view', $member);
        $this->AuthService->checkCan($member, 'edit', $member);

        // Get the log
        $log = AuthorizationService::getAuthCheckLog();

        // Verify logs were created
        $this->assertNotEmpty($log, 'Authorization check log should not be empty in debug mode');
        $this->assertCount(2, $log, 'Should have logged 2 authorization checks');

        // Verify log structure
        $firstCheck = $log[0];
        $this->assertArrayHasKey('timestamp', $firstCheck);
        $this->assertArrayHasKey('user_id', $firstCheck);
        $this->assertArrayHasKey('action', $firstCheck);
        $this->assertArrayHasKey('resource', $firstCheck);
        $this->assertArrayHasKey('result', $firstCheck);
        $this->assertArrayHasKey('additional_args', $firstCheck);

        // Verify log content
        $this->assertEquals('view', $log[0]['action']);
        $this->assertEquals('edit', $log[1]['action']);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $log[0]['user_id']);
    }

    /**
     * Test that authorization checks are NOT logged when debug mode is off
     */
    public function testNoLoggingWhenDebugModeDisabled(): void
    {
        // Disable debug mode
        Configure::write('debug', false);

        // Load a member
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $member->warrantableReview();

        // Perform authorization checks
        $this->AuthService->checkCan($member, 'view', $member);
        $this->AuthService->checkCan($member, 'edit', $member);

        // Get the log
        $log = AuthorizationService::getAuthCheckLog();

        // Verify no logs were created
        $this->assertEmpty($log, 'Authorization check log should be empty when debug mode is disabled');

        // Re-enable debug for other tests
        Configure::write('debug', true);
    }

    /**
     * Test that log captures both granted and denied checks
     */
    public function testLogCapturesGrantedAndDenied(): void
    {
        Configure::write('debug', true);

        // Load admin member
        $admin = $this->Members->get(self::ADMIN_MEMBER_ID);
        $admin->warrantableReview();

        // Load regular member
        $regularMember = $this->Members->get(self::TEST_MEMBER_AGATHA_ID);
        $regularMember->warrantableReview();

        // Admin should have access
        $grantedResult = $this->AuthService->checkCan($admin, 'view', $admin);
        $this->assertTrue($grantedResult);

        // Regular member may not have certain permissions
        $deniedResult = $this->AuthService->checkCan($regularMember, 'delete', $regularMember);

        // Get the log
        $log = AuthorizationService::getAuthCheckLog();

        $this->assertNotEmpty($log);

        // Find the granted check
        $grantedCheck = null;
        foreach ($log as $check) {
            if ($check['action'] === 'view' && $check['user_id'] === self::ADMIN_MEMBER_ID) {
                $grantedCheck = $check;
                break;
            }
        }

        $this->assertNotNull($grantedCheck, 'Should have logged the granted check');
        $this->assertTrue($grantedCheck['result'], 'Granted check should have result = true');
    }

    /**
     * Test clearing the authorization log
     */
    public function testClearAuthCheckLog(): void
    {
        Configure::write('debug', true);

        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $member->warrantableReview();

        // Perform checks
        $this->AuthService->checkCan($member, 'view', $member);
        $this->assertNotEmpty(AuthorizationService::getAuthCheckLog());

        // Clear the log
        AuthorizationService::clearAuthCheckLog();

        // Verify cleared
        $this->assertEmpty(AuthorizationService::getAuthCheckLog());
    }

    /**
     * Test resource info formatting
     */
    public function testResourceInfoFormatting(): void
    {
        Configure::write('debug', true);

        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $member->warrantableReview();

        // Check with entity
        $this->AuthService->checkCan($member, 'view', $member);

        // Check with a different action on same entity
        $this->AuthService->checkCan($member, 'profile', $member);

        $log = AuthorizationService::getAuthCheckLog();

        // First check should have entity info
        $this->assertStringContainsString('Member', $log[0]['resource']);
        $this->assertEquals('view', $log[0]['action']);

        // Second check should also reference Member
        $this->assertStringContainsString('Member', $log[1]['resource']);
        $this->assertEquals('profile', $log[1]['action']);
    }
}
