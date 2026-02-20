<?php

declare(strict_types=1);

namespace App\Test\TestCase\Core\Unit\Model;

use App\Model\Table\MembersTable;
use App\Test\TestCase\BaseTestCase;

/**
 * Verifies the dev seed baseline for members-related expectations.
 */
final class MembersTableSeedTest extends BaseTestCase
{
    private MembersTable $Members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Members = $this->getTableLocator()->get('Members');
    }

    /**
     * The admin seed account should always exist with predictable attributes.
     *
     * @return void
     */
    public function testAdminSeedRecord(): void
    {
        $this->skipIfPostgres();
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);

        $this->assertSame('Admin von Admin', $member->sca_name);
        $this->assertSame('admin@amp.ansteorra.org', $member->email_address);
        $this->assertTrue($member->status === 'verified' || $member->status === 'active');
    }

    /**
     * Duplicate emails from the seed should be rejected by rules.
     *
     * @return void
     */
    public function testDuplicateEmailCannotBeSaved(): void
    {
        $this->skipIfPostgres();
        $new = $this->Members->newEntity([
            'sca_name' => 'Duplicate Admin',
            'first_name' => 'Duplicate',
            'last_name' => 'Admin',
            'email_address' => 'admin@amp.ansteorra.org',
            'password' => 'TestPassword123!',
            'birth_month' => 1,
            'birth_year' => 1990,
        ]);

        $this->assertFalse($this->Members->save($new));
        $this->assertArrayHasKey('email_address', $new->getErrors());
    }
}
