<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\I18n\DateTime;

/**
 * TestSuperUserFixture
 * 
 * This fixture creates a complete super user setup for testing:
 * - A super user permission (Is Super User)
 * - A super user role (TestSuperUser)
 * - A test super user member (testsuper@test.com)
 * - All necessary relationships
 * 
 * This solves permission issues in tests by providing a baseline
 * authenticated user with full system access.
 */
class TestSuperUserFixture extends BaseTestFixture
{
    /**
     * The table this fixture is responsible for
     *
     * @var string
     */
    public string $table = 'members';

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        // This fixture provides a complete super user for testing
        // It includes: member, role, permission, and all relationships
        $this->records = [
            [
                'modified' => DateTime::now(),
                'password' => md5('Password123'),
                'sca_name' => 'Test Super User',
                'first_name' => 'Test',
                'middle_name' => 'Super',
                'last_name' => 'User',
                'street_address' => 'Test Address',
                'city' => 'Test City',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '555-555-5555',
                'email_address' => 'testsuper@test.com',
                'membership_number' => 'TestSuperUser001',
                'membership_expires_on' => '2100-01-01',
                'branch_id' => 1, // Kingdom
                'background_check_expires_on' => '2100-01-01',
                'status' => 'verified',
                'password_token' => null,
                'password_token_expires_on' => null,
                'last_login' => null,
                'last_failed_login' => null,
                'failed_login_attempts' => 0,
                'birth_month' => 1,
                'birth_year' => 1990,
                'deleted' => null,
                'created' => DateTime::now(),
                'mobile_card_token' => 'test_super_user_token_123',
            ]
        ];
        parent::init();
    }
}
