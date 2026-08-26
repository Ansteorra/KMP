<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Test\TestCase\BaseTestCase;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use DateTimeImmutable;

/**
 * Test case for AdvanceDevSeedDatesCommand.
 */
class AdvanceDevSeedDatesCommandTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Effective dates and date-bearing roster names move while audit dates remain fixed.
     *
     * @return void
     */
    public function testExecuteShiftsEffectiveDatesAndRosterNames(): void
    {
        $connection = $this->getTableLocator()->get('Members')->getConnection();
        $connection->update('members', [
            'membership_expires_on' => '2026-03-01',
            'background_check_expires_on' => '2026-04-01',
            'created' => '2025-01-02 03:04:05',
        ], ['id' => self::ADMIN_MEMBER_ID]);
        $connection->insert('warrant_rosters', [
            'name' => 'Test roster for 2026-01-01 ~ 2026-07-01',
            'approvals_required' => 1,
            'approval_count' => 0,
            'status' => 'Pending',
            'created' => '2026-01-10 00:00:00',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $gatheringBefore = $connection->execute(
            'SELECT id, start_date, end_date, created FROM gatherings '
            . 'WHERE start_date IS NOT NULL AND end_date IS NOT NULL ORDER BY id LIMIT 1',
        )->fetch('assoc');
        $warrantBefore = $connection->execute(
            'SELECT id, start_on, expires_on, created FROM warrants '
            . 'WHERE start_on IS NOT NULL AND expires_on IS NOT NULL ORDER BY id LIMIT 1',
        )->fetch('assoc');
        $this->assertNotFalse($gatheringBefore);
        $this->assertNotFalse($warrantBefore);

        $this->exec('advance_dev_seed_dates --as-of 2026-08-26');

        $this->assertExitCode(0);
        $this->assertOutputContains('2026-02-16 -> 2026-08-26 (191 days)');
        $member = $connection->execute(
            'SELECT membership_expires_on, background_check_expires_on, created FROM members WHERE id = ?',
            [self::ADMIN_MEMBER_ID],
        )->fetch('assoc');
        $this->assertSame('2026-09-08', $this->dateOnly($member['membership_expires_on']));
        $this->assertSame('2026-10-09', $this->dateOnly($member['background_check_expires_on']));
        $this->assertSame('2025-01-02 03:04:05', substr((string)$member['created'], 0, 19));

        $roster = $connection->execute(
            "SELECT name, created FROM warrant_rosters WHERE name LIKE 'Test roster for %'",
        )->fetch('assoc');
        $this->assertSame('Test roster for 2026-07-11 ~ 2027-01-08', $roster['name']);
        $this->assertSame('2026-01-10 00:00:00', substr((string)$roster['created'], 0, 19));

        $gatheringAfter = $connection->execute(
            'SELECT start_date, end_date, created FROM gatherings WHERE id = ?',
            [$gatheringBefore['id']],
        )->fetch('assoc');
        $this->assertShiftedByDays($gatheringBefore['start_date'], $gatheringAfter['start_date'], 191);
        $this->assertShiftedByDays($gatheringBefore['end_date'], $gatheringAfter['end_date'], 191);
        $this->assertSame($gatheringBefore['created'], $gatheringAfter['created']);

        $warrantAfter = $connection->execute(
            'SELECT start_on, expires_on, created FROM warrants WHERE id = ?',
            [$warrantBefore['id']],
        )->fetch('assoc');
        $this->assertShiftedByDays($warrantBefore['start_on'], $warrantAfter['start_on'], 191);
        $this->assertShiftedByDays($warrantBefore['expires_on'], $warrantAfter['expires_on'], 191);
        $this->assertSame($warrantBefore['created'], $warrantAfter['created']);
    }

    /**
     * Dry runs report the offset without changing data.
     *
     * @return void
     */
    public function testExecuteDryRunDoesNotChangeDates(): void
    {
        $connection = $this->getTableLocator()->get('Members')->getConnection();
        $connection->update('members', [
            'membership_expires_on' => '2026-03-01',
        ], ['id' => self::ADMIN_MEMBER_ID]);

        $this->exec('advance_dev_seed_dates --as-of 2026-08-26 --dry-run');

        $this->assertExitCode(0);
        $this->assertOutputContains('Dry run; no seed dates were changed.');
        $membershipExpiresOn = $connection->execute(
            'SELECT membership_expires_on FROM members WHERE id = ?',
            [self::ADMIN_MEMBER_ID],
        )->fetch('assoc');
        $this->assertSame('2026-03-01', $this->dateOnly($membershipExpiresOn['membership_expires_on']));
    }

    /**
     * Invalid dates fail before touching the snapshot.
     *
     * @return void
     */
    public function testExecuteRejectsInvalidAsOfDate(): void
    {
        $this->exec('advance_dev_seed_dates --as-of 2026-02-31');

        $this->assertExitCode(1);
        $this->assertErrorContains('must use valid YYYY-MM-DD dates');
    }

    /**
     * Normalize date values returned by either supported database driver.
     *
     * @param mixed $value Database value.
     * @return string
     */
    private function dateOnly(mixed $value): string
    {
        return (new DateTimeImmutable((string)$value))->format('Y-m-d');
    }

    /**
     * Assert that a database date or timestamp moved by the expected number of days.
     *
     * @param mixed $before Original value.
     * @param mixed $after Shifted value.
     * @param int $days Expected calendar-day shift.
     * @return void
     */
    private function assertShiftedByDays(mixed $before, mixed $after, int $days): void
    {
        $expected = (new DateTimeImmutable((string)$before))->modify(sprintf('+%d days', $days));
        $actual = new DateTimeImmutable((string)$after);

        $this->assertSame($expected->format('Y-m-d H:i:s'), $actual->format('Y-m-d H:i:s'));
    }
}
