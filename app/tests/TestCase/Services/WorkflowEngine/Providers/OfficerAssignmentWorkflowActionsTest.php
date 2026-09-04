<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Providers;

use App\KMP\TimezoneHelper;
use App\Model\Entity\Warrant;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;
use Officers\Services\OfficerAssignmentWorkflowActions;
use RuntimeException;

/**
 * Tests the security-sensitive officer assignment update workflow actions.
 */
class OfficerAssignmentWorkflowActionsTest extends BaseTestCase
{
    private WarrantManagerInterface $warrantManager;

    private OfficerAssignmentWorkflowActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warrantManager = $this->createMock(WarrantManagerInterface::class);
        $this->actions = new OfficerAssignmentWorkflowActions($this->warrantManager);
    }

    public function testTermChangeUpdatesOfficerAndRoleAndCreatesAuditNote(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $newStart = DateTime::now()->subDays(20)->toDateString();
        $newEnd = DateTime::now()->addMonths(8)->toDateString();

        $result = $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $newStart,
            'expiresOn' => $newEnd,
            'emailAddress' => 'updated-office@example.test',
            'deputyDescription' => 'Emergency deputy',
            'termNote' => 'The branch approved the revised term.',
        ]);

        $this->assertTrue($result['changed']);
        $this->assertTrue($result['termChanged']);
        $this->assertSame($newStart, $result['startOn']);
        $this->assertSame($newEnd, $result['expiresOn']);

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $this->assertSame($newStart, $savedOfficer->start_on->toDateString());
        $this->assertSame($newEnd, $savedOfficer->expires_on->toDateString());
        $this->assertSame('updated-office@example.test', $savedOfficer->email_address);
        $this->assertSame('Emergency deputy', $savedOfficer->deputy_description);

        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertSame($newStart, $savedRole->start_on->toDateString());
        $this->assertSame($newEnd, $savedRole->expires_on->toDateString());
        $this->assertNull($savedRole->revoker_id);

        $note = TableRegistry::getTableLocator()->get('Notes')->find()
            ->where([
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
                'subject' => Officer::TERM_UPDATE_NOTE_SUBJECT,
            ])
            ->firstOrFail();
        $this->assertSame(self::ADMIN_MEMBER_ID, $note->author_id);
        $this->assertStringContainsString('The branch approved the revised term.', $note->body);
        $this->assertFalse($note->private);
    }

    public function testEarlierEndShortensLinkedRoleAndLongerCurrentWarrant(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $warrant = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            DateTime::now()->addMonths(10),
        );
        $newEnd = DateTime::now()->addMonths(3)->startOfDay();
        $expectedEnd = $newEnd->endOfDay();
        $currentWarrantShortened = false;

        $this->warrantManager->expects($this->once())
            ->method('cancel')
            ->with(
                $warrant->id,
                'Officer term shortened.',
                self::ADMIN_MEMBER_ID,
                $this->callback(
                    fn(DateTime $date): bool => $date->toDateTimeString() === $expectedEnd->toDateTimeString(),
                ),
            )
            ->willReturnCallback(function () use (&$currentWarrantShortened): ServiceResult {
                $currentWarrantShortened = true;

                return new ServiceResult(true);
            });
        $this->warrantManager->expects($this->once())
            ->method('withdrawPendingRequests')
            ->with(
                'Officers.Officers',
                $officer->id,
                $officer->member_id,
                $memberRole->id,
                self::ADMIN_MEMBER_ID,
                'Officer term shortened; the issued warrant now covers the complete revised term.',
            )
            ->willReturnCallback(function () use (&$currentWarrantShortened): ServiceResult {
                $this->assertTrue(
                    $currentWarrantShortened,
                    'The issued warrant must be shortened before pending requests are withdrawn.',
                );

                return new ServiceResult(true, '', 2);
            });

        $result = $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $officer->start_on->toDateString(),
            'expiresOn' => $newEnd->toDateString(),
            'emailAddress' => $officer->email_address,
            'deputyDescription' => '',
            'termNote' => 'The branch approved an earlier end to this term.',
        ]);

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertSame($expectedEnd->toDateTimeString(), $savedOfficer->expires_on->toDateTimeString());
        $this->assertSame($expectedEnd->toDateTimeString(), $savedRole->expires_on->toDateTimeString());
        $this->assertSame(2, $result['withdrawnPendingWarrantCount']);
        $this->assertStringContainsString('current warrant was shortened', $result['warrantMessage']);
        $this->assertStringContainsString(
            '2 obsolete pending warrant request(s) were withdrawn.',
            $result['warrantMessage'],
        );

        $note = TableRegistry::getTableLocator()->get('Notes')->find()
            ->where([
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
                'subject' => Officer::TERM_UPDATE_NOTE_SUBJECT,
            ])
            ->firstOrFail();
        $this->assertStringContainsString('current warrant was shortened', $note->body);
        $this->assertStringContainsString('No issued warrant was lengthened', $note->body);
        $this->assertStringContainsString(
            '2 obsolete pending warrant request(s) were withdrawn.',
            $note->body,
        );
    }

    public function testLaterEndExtendsLinkedRoleWithoutLengtheningCurrentWarrant(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $currentWarrantEnd = DateTime::now()->addMonths(2)->setTime(12, 34, 56);
        $warrant = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            $currentWarrantEnd,
        );
        $newEnd = DateTime::now()->addMonths(9)->startOfDay();
        $expectedRoleEnd = $newEnd->endOfDay();
        $this->warrantManager->expects($this->never())->method('cancel');

        $result = $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $officer->start_on->toDateString(),
            'expiresOn' => $newEnd->toDateString(),
            'emailAddress' => $officer->email_address,
            'deputyDescription' => '',
            'termNote' => 'The branch approved a longer officer term.',
        ]);

        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $savedWarrant = TableRegistry::getTableLocator()->get('Warrants')->get($warrant->id);
        $this->assertSame($expectedRoleEnd->toDateTimeString(), $savedRole->expires_on->toDateTimeString());
        $this->assertSame($currentWarrantEnd->toDateTimeString(), $savedWarrant->expires_on->toDateTimeString());
        $this->assertSame('', $result['warrantMessage']);
    }

    public function testEndOfDayNormalizationDoesNotLengthenWarrantEndingEarlierThatDay(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $newEnd = DateTime::now()->addMonths(3)->startOfDay();
        $warrant = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            $newEnd,
        );
        $this->warrantManager->expects($this->never())->method('cancel');

        $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $officer->start_on->toDateString(),
            'expiresOn' => $newEnd->toDateString(),
            'emailAddress' => $officer->email_address,
            'deputyDescription' => '',
            'termNote' => 'The branch approved the shorter term.',
        ]);

        $savedWarrant = TableRegistry::getTableLocator()->get('Warrants')->get($warrant->id);
        $this->assertSame($newEnd->toDateTimeString(), $savedWarrant->expires_on->toDateTimeString());
    }

    public function testTrustedWorkflowActorOverridesConfiguredActorForAuditNote(): void
    {
        [$officer] = $this->createOfficerWithRole();
        $newEnd = DateTime::now()->addMonths(9)->toDateString();

        $this->actions->updateOfficerAssignment(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'officerId' => $officer->id,
                'actorId' => self::TEST_MEMBER_AGATHA_ID,
                'startOn' => $officer->start_on->toDateString(),
                'expiresOn' => $newEnd,
                'emailAddress' => $officer->email_address,
                'deputyDescription' => '',
                'termNote' => 'Authorized by the authenticated workflow actor.',
            ],
        );

        $note = TableRegistry::getTableLocator()->get('Notes')->find()
            ->where([
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
                'subject' => Officer::TERM_UPDATE_NOTE_SUBJECT,
            ])
            ->firstOrFail();
        $this->assertSame(self::ADMIN_MEMBER_ID, $note->author_id);
    }

    public function testEndDateEqualTodayRemainsCurrentThroughEndOfCalendarDay(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $today = DateTime::now()->toDateString();

        $result = $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $officer->start_on->toDateString(),
            'expiresOn' => $today,
            'emailAddress' => $officer->email_address,
            'deputyDescription' => '',
            'termNote' => 'The approved term ends after today is complete.',
        ]);

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertSame(Officer::CURRENT_STATUS, $savedOfficer->status);
        $this->assertSame($today, $result['expiresOn']);
        $this->assertSame($today, $savedOfficer->expires_on->toDateString());
        $this->assertSame('23:59:59', $savedOfficer->expires_on->format('H:i:s'));
        $this->assertSame($today, $savedRole->expires_on->toDateString());
        $this->assertSame('23:59:59', $savedRole->expires_on->format('H:i:s'));
        $this->assertGreaterThan(DateTime::now(), $savedRole->expires_on);
    }

    public function testFutureStartSetsOfficerAndRoleUpcoming(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $newStart = DateTime::now()->addDays(5)->toDateString();
        $newEnd = DateTime::now()->addMonths(3)->toDateString();

        $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $newStart,
            'expiresOn' => $newEnd,
            'emailAddress' => $officer->email_address,
            'deputyDescription' => '',
            'termNote' => 'The appointment begins next week.',
        ]);

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertSame(Officer::UPCOMING_STATUS, $savedOfficer->status);
        $this->assertGreaterThan(DateTime::now(), $savedRole->start_on);
    }

    public function testClearingOfficeEmailPersistsEmptyStringWithoutTermNote(): void
    {
        [$officer] = $this->createOfficerWithRole();

        $result = $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $officer->start_on->toDateString(),
            'expiresOn' => $officer->expires_on->toDateString(),
            'emailAddress' => '   ',
            'deputyDescription' => '',
            'termNote' => '',
        ]);

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $this->assertTrue($result['changed']);
        $this->assertFalse($result['termChanged']);
        $this->assertSame('', $savedOfficer->email_address);
    }

    public function testTermChangeWithoutNoteRollsBack(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $originalStart = $officer->start_on->toDateString();
        $originalEnd = $officer->expires_on->toDateString();
        $notes = TableRegistry::getTableLocator()->get('Notes');
        $beforeNotes = $notes->find()
            ->where(['entity_type' => 'Officers.Officers', 'entity_id' => $officer->id])
            ->count();

        try {
            $this->actions->updateOfficerAssignment([], [
                'officerId' => $officer->id,
                'actorId' => self::ADMIN_MEMBER_ID,
                'startOn' => DateTime::now()->subDays(10)->toDateString(),
                'expiresOn' => $originalEnd,
                'emailAddress' => 'should-not-save@example.test',
                'deputyDescription' => '',
                'termNote' => '   ',
            ]);
            $this->fail('Expected a missing term note to reject the update.');
        } catch (RuntimeException $e) {
            $this->assertSame('A note is required when changing an officer term.', $e->getMessage());
        }

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertSame($originalStart, $savedOfficer->start_on->toDateString());
        $this->assertSame($originalEnd, $savedOfficer->expires_on->toDateString());
        $this->assertSame($originalStart, $savedRole->start_on->toDateString());
        $this->assertSame($originalEnd, $savedRole->expires_on->toDateString());
        $this->assertSame(
            $beforeNotes,
            $notes->find()->where([
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
            ])->count(),
        );
    }

    public function testReleasedAssignmentCannotBeUpdatedOrReactivated(): void
    {
        $this->assertTerminalAssignmentCannotBeUpdated(Officer::RELEASED_STATUS);
    }

    public function testExpiredAssignmentCannotBeUpdatedOrReactivated(): void
    {
        $this->assertTerminalAssignmentCannotBeUpdated(Officer::EXPIRED_STATUS);
    }

    public function testPastEndTerminatesWarrantAtRecordingTimeAndExplainsPermissionWindow(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $warrant = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            DateTime::now()->addMonths(2),
        );
        $before = DateTime::now()->subSeconds(2);

        $this->warrantManager->expects($this->once())
            ->method('cancel')
            ->with(
                $warrant->id,
                'Officer term corrected to a past end date.',
                self::ADMIN_MEMBER_ID,
                $this->callback(
                    fn(DateTime $date): bool => $date >= $before && $date <= DateTime::now(),
                ),
            )
            ->willReturn(new ServiceResult(true));

        $pastEnd = DateTime::now()->subDays(5)->toDateString();
        $result = $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $officer->start_on->toDateString(),
            'expiresOn' => $pastEnd,
            'emailAddress' => $officer->email_address,
            'deputyDescription' => '',
            'termNote' => 'The archival record confirmed an earlier end date.',
        ]);

        $this->assertTrue($result['termChanged']);
        $this->assertStringContainsString('remained effective until', $result['warrantMessage']);

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $this->assertSame(Officer::EXPIRED_STATUS, $savedOfficer->status);
        $this->assertSame($pastEnd, $savedOfficer->expires_on->toDateString());

        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertGreaterThanOrEqual($before, $savedRole->expires_on);
        $this->assertLessThanOrEqual(DateTime::now(), $savedRole->expires_on);
        $this->assertNull($savedRole->revoker_id);
        $this->assertSame(0, TableRegistry::getTableLocator()->get('MemberRoles')
            ->find('current')
            ->where(['MemberRoles.id' => $memberRole->id])
            ->count());

        $note = TableRegistry::getTableLocator()->get('Notes')->find()
            ->where([
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
                'subject' => Officer::TERM_UPDATE_NOTE_SUBJECT,
            ])
            ->firstOrFail();
        $this->assertStringContainsString('were not backdated', $note->body);
        $this->assertStringContainsString('remained effective until this change was recorded', $note->body);
        $this->assertStringContainsString('terminated effective that date', $note->body);
    }

    public function testPastEndCancelFailureRollsBackAndDoesNotLeakUnderlyingReason(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $originalOfficerEnd = $officer->expires_on->toDateTimeString();
        $originalRoleEnd = $memberRole->expires_on->toDateTimeString();
        $warrant = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            DateTime::now()->addMonths(2),
        );
        $this->warrantManager->expects($this->once())
            ->method('cancel')
            ->with(
                $warrant->id,
                'Officer term corrected to a past end date.',
                self::ADMIN_MEMBER_ID,
                $this->isInstanceOf(DateTime::class),
            )
            ->willReturn(new ServiceResult(false, 'Sensitive warrant backend detail'));

        try {
            $this->actions->updateOfficerAssignment([], [
                'officerId' => $officer->id,
                'actorId' => self::ADMIN_MEMBER_ID,
                'startOn' => $officer->start_on->toDateString(),
                'expiresOn' => DateTime::now()->subDays(5)->toDateString(),
                'emailAddress' => 'should-roll-back@example.test',
                'deputyDescription' => '',
                'termNote' => 'This update should roll back when warrant termination fails.',
            ]);
            $this->fail('Expected the failed warrant termination to reject the update.');
        } catch (RuntimeException $e) {
            $this->assertSame('The officer assignment could not be updated.', $e->getMessage());
            $this->assertStringNotContainsString('Sensitive warrant backend detail', $e->getMessage());
        }

        $savedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertSame($originalOfficerEnd, $savedOfficer->expires_on->toDateTimeString());
        $this->assertSame($originalRoleEnd, $savedRole->expires_on->toDateTimeString());
        $this->assertSame('office@example.test', $savedOfficer->email_address);
        $this->assertSame(0, TableRegistry::getTableLocator()->get('Notes')->find()
            ->where([
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
                'subject' => Officer::TERM_UPDATE_NOTE_SUBJECT,
            ])
            ->count());
    }

    public function testNonTermEditPreservesExactAssignmentAndRoleTimestamps(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $futureEnd = DateTime::now()->addDays(5)->startOfDay();
        $roleEnd = DateTime::now()->addDays(5)->setTime(14, 15, 16);
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer->expires_on = $futureEnd;
        $officer->status = Officer::CURRENT_STATUS;
        $officers->saveOrFail($officer);
        $memberRole->expires_on = $roleEnd;
        TableRegistry::getTableLocator()->get('MemberRoles')->saveOrFail($memberRole);
        $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            DateTime::now()->addMonths(1),
        );
        $notes = TableRegistry::getTableLocator()->get('Notes');
        $beforeNotes = $notes->find()
            ->where(['entity_type' => 'Officers.Officers', 'entity_id' => $officer->id])
            ->count();
        $this->warrantManager->expects($this->never())->method('cancel');

        $result = $this->actions->updateOfficerAssignment([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'startOn' => $officer->start_on->toDateString(),
            'expiresOn' => $futureEnd->toDateString(),
            'emailAddress' => 'office-updated@example.test',
            'deputyDescription' => 'Updated without changing the term',
            'termNote' => '',
        ]);

        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertTrue($result['changed']);
        $this->assertFalse($result['termChanged']);
        $this->assertSame('', $result['warrantMessage']);
        $this->assertSame(
            $roleEnd->toDateTimeString(),
            $savedRole->expires_on->toDateTimeString(),
        );
        $this->assertSame(
            $futureEnd->toDateTimeString(),
            $officers->get($officer->id)->expires_on->toDateTimeString(),
        );
        $this->assertSame(
            $beforeNotes,
            $notes->find()
                ->where(['entity_type' => 'Officers.Officers', 'entity_id' => $officer->id])
                ->count(),
        );
        $this->assertSame(
            'office-updated@example.test',
            $officers->get($officer->id)->email_address,
        );
    }

    public function testNotificationUsesMemberAccountEmailAndFormattedTermDates(): void
    {
        [$officer] = $this->createOfficerWithRole();
        $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);

        $result = $this->actions->prepareAssignmentUpdateNotificationVars([], [
            'officerId' => $officer->id,
            'changeSummary' => 'The office email was updated.',
            'termChangeNote' => '',
            'warrantMessage' => '',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($member->email_address, $result['data']['to']);
        $this->assertNotSame($officer->email_address, $result['data']['to']);
        $this->assertSame(TimezoneHelper::formatDate($officer->start_on), $result['data']['startDate']);
        $this->assertSame(TimezoneHelper::formatDate($officer->expires_on), $result['data']['endDate']);
    }

    public function testExtensionRequestsCoveringWarrantWithoutChangingCurrentWarrant(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10);
        $extensionStart = $currentEnd->addDays(1)->startOfDay();
        $newEnd = DateTime::now()->addDays(25);
        $periodEnd = $newEnd->startOfDay();
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer->expires_on = $newEnd;
        $officers->saveOrFail($officer);
        $currentWarrant = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            $currentEnd,
        );
        $period = TableRegistry::getTableLocator()->get('WarrantPeriods')->newEntity([
            'start_date' => DateTime::now()->subDays(5),
            'end_date' => $periodEnd,
        ]);

        $this->warrantManager->expects($this->once())
            ->method('getWarrantPeriod')
            ->with(
                $this->callback(
                    fn(DateTime $date): bool => $date->toDateString() === $extensionStart->toDateString(),
                ),
                null,
            )
            ->willReturn($period);
        $this->warrantManager->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('(Extension)'),
                $this->stringContains('without cancelling'),
                $this->callback(function (array $requests) use ($extensionStart, $newEnd): bool {
                    if (count($requests) !== 1 || !$requests[0] instanceof WarrantRequest) {
                        return false;
                    }

                    return $requests[0]->start_on?->toDateString() === $extensionStart->toDateString()
                        && $requests[0]->expires_on?->toDateString() === $newEnd->toDateString();
                }),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn(new ServiceResult(true, '', 4321));

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertTrue($result['requested']);
        $this->assertSame(4321, $result['rosterId']);
        $this->assertSame('', $result['warning']);
        $this->assertStringContainsString('without cancelling the current warrant', $result['warrantMessage']);
        $this->assertStringContainsString(TimezoneHelper::formatDate($newEnd), $result['warrantMessage']);

        $savedCurrent = TableRegistry::getTableLocator()->get('Warrants')->get($currentWarrant->id);
        $this->assertSame(Warrant::CURRENT_STATUS, $savedCurrent->status);
        $this->assertSame($currentEnd->toDateString(), $savedCurrent->expires_on->toDateString());
    }

    public function testExtensionUsesAvailablePeriodWhenItEndsBeforeTerm(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $periodEnd = DateTime::now()->addDays(20)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $currentWarrant = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            $currentEnd,
        );
        $this->expectSuccessfulExtensionRequest($currentEnd, $periodEnd, $periodEnd, 4322);

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertTrue($result['requested']);
        $this->assertSame(4322, $result['rosterId']);
        $this->assertStringContainsString(TimezoneHelper::formatDate($periodEnd), $result['warrantMessage']);
        $this->assertStringContainsString(
            TimezoneHelper::formatDate($newEnd),
            $result['warrantMessage'],
        );
        $this->assertStringContainsString(
            'Additional warrant coverage will still be required.',
            $result['warning'],
        );
        $this->assertStringContainsString(TimezoneHelper::formatDate($periodEnd), $result['warning']);
        $this->assertStringContainsString(TimezoneHelper::formatDate($newEnd), $result['warning']);

        $savedCurrent = TableRegistry::getTableLocator()->get('Warrants')->get($currentWarrant->id);
        $this->assertSame(Warrant::CURRENT_STATUS, $savedCurrent->status);
        $this->assertSame($currentEnd->toDateString(), $savedCurrent->expires_on->toDateString());
    }

    public function testExactPendingWarrantTargetMakesExtensionIdempotent(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $extensionStart = $currentEnd->addDays(1)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant($officer, $memberRole->id, Warrant::CURRENT_STATUS, $currentEnd);
        $pending = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::PENDING_STATUS,
            $newEnd->startOfDay(),
        );
        $pending->start_on = $extensionStart;
        TableRegistry::getTableLocator()->get('Warrants')->saveOrFail($pending);
        $this->expectSuccessfulExtensionRequest(
            $currentEnd,
            $newEnd->startOfDay(),
            $newEnd->startOfDay(),
            4323,
            WarrantManagerInterface::REQUEST_REUSED_REASON,
        );

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertFalse($result['requested']);
        $this->assertSame(4323, $result['rosterId']);
        $this->assertSame('', $result['warning']);
        $this->assertStringContainsString('already covers', $result['warrantMessage']);
    }

    public function testExactPendingPartialTargetIsIdempotentAndKeepsCoverageWarning(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $extensionStart = $currentEnd->addDays(1)->startOfDay();
        $periodEnd = DateTime::now()->addDays(20)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant($officer, $memberRole->id, Warrant::CURRENT_STATUS, $currentEnd);
        $pending = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::PENDING_STATUS,
            $periodEnd,
        );
        $pending->start_on = $extensionStart;
        TableRegistry::getTableLocator()->get('Warrants')->saveOrFail($pending);
        $this->expectSuccessfulExtensionRequest(
            $currentEnd,
            $periodEnd,
            $periodEnd,
            4324,
            WarrantManagerInterface::REQUEST_REUSED_REASON,
        );

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertFalse($result['requested']);
        $this->assertSame(4324, $result['rosterId']);
        $this->assertStringContainsString('already covers the available extension period', $result['warrantMessage']);
        $this->assertStringContainsString(TimezoneHelper::formatDate($periodEnd), $result['warrantMessage']);
        $this->assertStringContainsString(
            'Additional warrant coverage will still be required.',
            $result['warning'],
        );
        $this->assertStringContainsString(TimezoneHelper::formatDate($newEnd), $result['warning']);
    }

    public function testCurrentWarrantEndingOnSameCalendarDateCoversInclusiveTermEnd(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::CURRENT_STATUS,
            $newEnd->startOfDay(),
        );
        $this->warrantManager->expects($this->never())->method('getWarrantPeriod');
        $this->warrantManager->expects($this->never())->method('request');

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertFalse($result['requested']);
        $this->assertSame('', $result['warning']);
        $this->assertStringContainsString('already covers', $result['warrantMessage']);
    }

    public function testPendingWarrantEndingOnSameCalendarDateCoversInclusiveTermEnd(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant($officer, $memberRole->id, Warrant::CURRENT_STATUS, $currentEnd);
        $pending = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::PENDING_STATUS,
            $newEnd->startOfDay(),
        );
        $pending->start_on = $currentEnd->addDays(1)->startOfDay();
        TableRegistry::getTableLocator()->get('Warrants')->saveOrFail($pending);
        $this->expectSuccessfulExtensionRequest(
            $currentEnd,
            $newEnd->startOfDay(),
            $newEnd->startOfDay(),
            4325,
            WarrantManagerInterface::REQUEST_REUSED_REASON,
        );

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertFalse($result['requested']);
        $this->assertSame(4325, $result['rosterId']);
        $this->assertSame('', $result['warning']);
        $this->assertStringContainsString('pending warrant request already covers', $result['warrantMessage']);
    }

    public function testPendingWarrantEndingBeforeNewEndDoesNotSuppressExtension(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant($officer, $memberRole->id, Warrant::CURRENT_STATUS, $currentEnd);
        $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::PENDING_STATUS,
            $newEnd->subDays(1)->endOfDay(),
        );
        $this->expectSuccessfulExtensionRequest(
            $currentEnd,
            $newEnd->startOfDay(),
            $newEnd->startOfDay(),
            5001,
        );

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertTrue($result['requested']);
        $this->assertSame('', $result['warning']);
    }

    public function testMalformedFuturePendingWarrantDoesNotSuppressExtension(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant($officer, $memberRole->id, Warrant::CURRENT_STATUS, $currentEnd);
        $pending = $this->createWarrant(
            $officer,
            $memberRole->id,
            Warrant::PENDING_STATUS,
            $newEnd->addDays(10),
        );
        $pending->start_on = $newEnd->addDays(1)->startOfDay();
        TableRegistry::getTableLocator()->get('Warrants')->saveOrFail($pending);
        $this->expectSuccessfulExtensionRequest(
            $currentEnd,
            $newEnd->startOfDay(),
            $newEnd->startOfDay(),
            5002,
        );

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertTrue($result['requested']);
        $this->assertSame('', $result['warning']);
    }

    public function testWarrantRequestFailureReturnsSafeWarningEnvelope(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant($officer, $memberRole->id, Warrant::CURRENT_STATUS, $currentEnd);
        $period = TableRegistry::getTableLocator()->get('WarrantPeriods')->newEntity([
            'start_date' => DateTime::now()->subDays(1),
            'end_date' => $newEnd->startOfDay(),
        ]);
        $this->warrantManager->expects($this->once())
            ->method('getWarrantPeriod')
            ->willReturn($period);
        $this->warrantManager->expects($this->once())
            ->method('request')
            ->willReturn(new ServiceResult(false, 'Sensitive database transport detail'));

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame($result['error'], $result['data']['warning']);
        $this->assertStringNotContainsString('Sensitive database', $result['error']);
        $this->assertFalse($result['data']['requested']);
    }

    public function testNoCoveringWarrantPeriodReturnsNoRequestWithoutWarning(): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole(2);
        $currentEnd = DateTime::now()->addDays(10)->startOfDay();
        $newEnd = DateTime::now()->addDays(25)->endOfDay();
        $officer->expires_on = $newEnd;
        TableRegistry::getTableLocator()->get('Officers.Officers')->saveOrFail($officer);
        $this->createWarrant($officer, $memberRole->id, Warrant::CURRENT_STATUS, $currentEnd);
        $this->warrantManager->expects($this->once())
            ->method('getWarrantPeriod')
            ->willReturn(null);
        $this->warrantManager->expects($this->never())->method('request');

        $result = $this->actions->requestWarrantExtension([], [
            'officerId' => $officer->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'existingWarrantMessage' => '',
        ]);

        $this->assertFalse($result['requested']);
        $this->assertSame('', $result['warning']);
        $this->assertSame(
            'No warrant extension was requested because no warrant period covers '
                . 'the day after the current warrant ends.',
            $result['warrantMessage'],
        );
    }

    private function expectSuccessfulExtensionRequest(
        DateTime $currentEnd,
        DateTime $periodEnd,
        DateTime $expectedRequestEnd,
        int $rosterId,
        ?string $resultReason = null,
    ): void {
        $extensionStart = $currentEnd->addDays(1)->startOfDay();
        $period = TableRegistry::getTableLocator()->get('WarrantPeriods')->newEntity([
            'start_date' => DateTime::now()->subDays(1),
            'end_date' => $periodEnd,
        ]);
        $this->warrantManager->expects($this->once())
            ->method('getWarrantPeriod')
            ->with(
                $this->callback(
                    fn(DateTime $date): bool => $date->toDateString() === $extensionStart->toDateString()
                        && $date->format('H:i:s') === '00:00:00',
                ),
                null,
            )
            ->willReturn($period);
        $this->warrantManager->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('(Extension)'),
                $this->stringContains('without cancelling'),
                $this->callback(function (array $requests) use ($extensionStart, $expectedRequestEnd): bool {
                    if (count($requests) !== 1 || !$requests[0] instanceof WarrantRequest) {
                        return false;
                    }

                    return $requests[0]->start_on?->toDateString() === $extensionStart->toDateString()
                        && $requests[0]->start_on?->format('H:i:s') === '00:00:00'
                        && $requests[0]->expires_on?->toDateString() === $expectedRequestEnd->toDateString();
                }),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn(new ServiceResult(true, $resultReason, $rosterId));
    }

    private function assertTerminalAssignmentCannotBeUpdated(string $status): void
    {
        [$officer, $memberRole] = $this->createOfficerWithRole();
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer->status = $status;
        $officers->saveOrFail($officer);
        $originalStart = $officer->start_on->toDateTimeString();
        $originalEnd = $officer->expires_on->toDateTimeString();
        $originalRoleStart = $memberRole->start_on->toDateTimeString();
        $originalRoleEnd = $memberRole->expires_on->toDateTimeString();
        $this->warrantManager->expects($this->never())->method('cancel');

        try {
            $this->actions->updateOfficerAssignment([], [
                'officerId' => $officer->id,
                'actorId' => self::ADMIN_MEMBER_ID,
                'startOn' => DateTime::now()->toDateString(),
                'expiresOn' => DateTime::now()->addMonths(12)->toDateString(),
                'emailAddress' => 'crafted-reactivation@example.test',
                'deputyDescription' => 'Crafted reactivation',
                'termNote' => 'Attempt to reactivate a terminal assignment.',
            ]);
            $this->fail('Expected the terminal assignment update to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Only current or upcoming officer assignments can be updated.',
                $e->getMessage(),
            );
        }

        $savedOfficer = $officers->get($officer->id);
        $savedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRole->id);
        $this->assertSame($status, $savedOfficer->status);
        $this->assertSame('office@example.test', $savedOfficer->email_address);
        $this->assertSame($originalStart, $savedOfficer->start_on->toDateTimeString());
        $this->assertSame($originalEnd, $savedOfficer->expires_on->toDateTimeString());
        $this->assertSame($originalRoleStart, $savedRole->start_on->toDateTimeString());
        $this->assertSame($originalRoleEnd, $savedRole->expires_on->toDateTimeString());
    }

    /**
     * @return array{0: \Officers\Model\Entity\Officer, 1: \App\Model\Entity\MemberRole}
     */
    private function createOfficerWithRole(int $officeId = 2): array
    {
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $memberRoles = TableRegistry::getTableLocator()->get('MemberRoles');
        $startOn = DateTime::now()->subMonths(1);
        $expiresOn = DateTime::now()->addMonths(6);

        $officer = $officers->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $officeId,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => $startOn,
            'expires_on' => $expiresOn,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'email_address' => 'office@example.test',
        ]);
        $officers->saveOrFail($officer);

        $memberRole = $memberRoles->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role_id' => self::ADMIN_ROLE_ID,
            'entity_type' => 'Officers.Officers',
            'entity_id' => $officer->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'start_on' => $startOn,
            'expires_on' => $expiresOn,
            'approver_id' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['*' => true]]);
        $memberRoles->saveOrFail($memberRole);

        $officer->granted_member_role_id = $memberRole->id;
        $officers->saveOrFail($officer);

        return [$officers->get($officer->id, contain: ['Offices']), $memberRole];
    }

    private function createWarrant(
        Officer $officer,
        int $memberRoleId,
        string $status,
        DateTime $expiresOn,
    ): Warrant {
        $rosters = TableRegistry::getTableLocator()->get('WarrantRosters');
        $roster = $rosters->newEntity([
            'name' => 'Assignment update test roster ' . uniqid(),
            'description' => 'Workflow action test',
            'approvals_required' => 1,
            'approval_count' => $status === Warrant::CURRENT_STATUS ? 1 : 0,
            'status' => $status,
            'created_by' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['*' => true]]);
        $rosters->saveOrFail($roster);

        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $warrant = $warrants->newEntity([
            'name' => 'Assignment update test warrant ' . uniqid(),
            'member_id' => $officer->member_id,
            'warrant_roster_id' => $roster->id,
            'entity_type' => 'Officers.Officers',
            'entity_id' => $officer->id,
            'member_role_id' => $memberRoleId,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'start_on' => DateTime::now()->subDays(10),
            'expires_on' => $expiresOn,
            'status' => $status,
        ], ['accessibleFields' => ['*' => true]]);
        $warrants->saveOrFail($warrant);

        return $warrant;
    }
}
