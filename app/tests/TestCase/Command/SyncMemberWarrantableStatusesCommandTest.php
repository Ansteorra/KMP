<?php

declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\FrozenDate;

/**
 * Test case for SyncMemberWarrantableStatusesCommand.
 */
class SyncMemberWarrantableStatusesCommandTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Ensure stale warrantable=true is corrected when membership is expired.
     *
     * @return void
     */
    public function testExecuteUpdatesExpiredMembershipToNotWarrantable(): void
    {
        $this->markAdminMemberAsStaleWarrantable();
        $this->assertTrue($this->fetchWarrantableFlag(self::ADMIN_MEMBER_ID));

        $this->exec('sync_member_warrantable_statuses');

        $this->assertExitCode(0);
        $this->assertOutputContains('Member warrantable sync started');
        $this->assertFalse($this->fetchWarrantableFlag(self::ADMIN_MEMBER_ID));
    }

    /**
     * Ensure dry-run reports changes but does not persist them.
     *
     * @return void
     */
    public function testExecuteDryRunDoesNotPersistChanges(): void
    {
        $this->markAdminMemberAsStaleWarrantable();
        $this->assertTrue($this->fetchWarrantableFlag(self::ADMIN_MEMBER_ID));

        $this->exec('sync_member_warrantable_statuses --dry-run');

        $this->assertExitCode(0);
        $this->assertOutputContains('(dry-run)');
        $this->assertTrue($this->fetchWarrantableFlag(self::ADMIN_MEMBER_ID));
    }

    /**
     * Create a controlled stale member state for sync validation.
     *
     * @return void
     */
    private function markAdminMemberAsStaleWarrantable(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $membersTable->getConnection()->update('members', [
            'status' => Member::STATUS_VERIFIED_MEMBERSHIP,
            'membership_expires_on' => FrozenDate::yesterday()->toDateString(),
            'warrantable' => 1,
            'birth_month' => 1,
            'birth_year' => 1990,
            'first_name' => 'Admin',
            'last_name' => 'Admin',
            'street_address' => '123 Test St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip' => '78701',
            'phone_number' => '5555555555',
        ], ['id' => self::ADMIN_MEMBER_ID]);
    }

    /**
     * Read warrantable flag directly from SQL to avoid ORM identity caching.
     *
     * @param int $memberId Member identifier.
     * @return bool
     */
    private function fetchWarrantableFlag(int $memberId): bool
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $row = $membersTable->find()
            ->select(['warrantable'])
            ->where(['Members.id' => $memberId])
            ->disableHydration()
            ->first();

        $this->assertNotNull($row);

        return (bool)$row['warrantable'];
    }
}
