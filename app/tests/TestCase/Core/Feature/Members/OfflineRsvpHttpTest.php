<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\ORM\Table;
use Cake\Utility\Text;

/** Replay requests must preserve the current actor and the original private RSVP. */
class OfflineRsvpHttpTest extends HttpIntegrationTestCase
{
    private bool $originalSavePoints;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise a real unique-key violation without aborting the surrounding fixture transaction.
        $this->originalSavePoints = $this->connection->isSavePointsEnabled();
        $this->connection->enableSavePoints();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
    }

    protected function tearDown(): void
    {
        $connection = $this->connection;
        parent::tearDown();
        $connection->enableSavePoints($this->originalSavePoints);
    }

    public function testQueuedActorACannotWriteUsingActorBSession(): void
    {
        $gathering = $this->gathering();
        $context = $this->contextFor(self::TEST_MEMBER_AGATHA_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->post('/gathering-attendances/mobile-rsvp', $this->payload($gathering->id, $context));
        $this->assertResponseCode(403);
        $this->assertFalse($this->json()['success']);
        $this->assertSame(0, $this->attendances()->find()->where(['gathering_id' => $gathering->id])->count());
    }

    public function testOldEpochCannotReplayAfterFreshLogin(): void
    {
        $gathering = $this->gathering();
        $context = $this->contextFor(self::TEST_MEMBER_AGATHA_ID);
        $members = $this->getTableLocator()->get('Members');
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $member->password = 'SyntheticChangedPasswordForOffline123!';
        $members->saveOrFail($member);
        $fresh = $this->contextFor(self::TEST_MEMBER_AGATHA_ID);
        $this->assertSame($context['owner'], $fresh['owner']);
        $this->assertNotSame($context['epoch'], $fresh['epoch']);
        $this->post('/gathering-attendances/mobile-rsvp', $this->payload($gathering->id, $context));
        $this->assertResponseCode(403);
        $this->assertFalse($this->json()['success']);
        $this->assertSame(0, $this->attendances()->find()->where(['gathering_id' => $gathering->id])->count());
    }

    public function testDuplicateReplayCreatesOnePrivateRsvpAndPreservesLaterEdits(): void
    {
        $gathering = $this->gathering();
        $context = $this->contextFor(self::TEST_MEMBER_AGATHA_ID);
        $data = $this->payload($gathering->id, $context) + [
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'share_with_kingdom' => true,
            'share_with_crown' => true,
            'is_public' => true,
            'public_note' => 'Untrusted queued text',
        ];
        $this->post('/gathering-attendances/mobile-rsvp', $data);
        $this->assertResponseOk();
        $this->assertTrue($this->json()['success']);
        $attendance = $this->attendances()->find()->where(['gathering_id' => $gathering->id])->firstOrFail();
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$attendance->member_id);
        $this->assertFalse($attendance->share_with_kingdom);
        $this->assertFalse($attendance->share_with_crown);
        $this->assertFalse($attendance->share_with_hosting_group);
        $this->assertFalse($attendance->is_public);
        $this->assertNull($attendance->public_note);
        $this->assertSame($data['offline_request_id'], $attendance->offline_request_id);

        $attendance->share_with_crown = true;
        $attendance->public_note = 'Later authorized online edit';
        $this->attendances()->saveOrFail($attendance);
        $this->post('/gathering-attendances/mobile-rsvp', $data);
        $this->assertResponseOk();
        $this->assertTrue($this->json()['success']);
        $this->assertSame((int)$attendance->id, (int)$this->json()['attendance_id']);
        $this->assertSame(1, $this->attendances()->find()->where(['gathering_id' => $gathering->id])->count());
        $fresh = $this->attendances()->get($attendance->id);
        $this->assertTrue($fresh->share_with_crown);
        $this->assertSame('Later authorized online edit', $fresh->public_note);
    }

    public function testRequestIdCannotBeReusedForAnotherGathering(): void
    {
        $first = $this->gathering();
        $second = $this->gathering();
        $context = $this->contextFor(self::TEST_MEMBER_AGATHA_ID);
        $requestId = Text::uuid();
        $this->post('/gathering-attendances/mobile-rsvp', $this->payload($first->id, $context, $requestId));
        $this->assertResponseOk();
        $this->post('/gathering-attendances/mobile-rsvp', $this->payload($second->id, $context, $requestId));
        $this->assertResponseCode(500);
        $this->assertFalse($this->json()['success']);
        $this->assertSame(1, $this->attendances()->find()->where(['offline_request_id' => $requestId])->count());
        $this->assertSame(0, $this->attendances()->find()->where(['gathering_id' => $second->id])->count());
    }

    public function testImpersonationCannotReplayEvenWithTheTargetsValidBinding(): void
    {
        $gathering = $this->gathering();
        $context = $this->contextFor(self::TEST_MEMBER_AGATHA_ID);
        $this->authenticateAsSuperUser();
        $this->post('/members/impersonate/' . self::TEST_MEMBER_AGATHA_ID);
        $this->assertRedirect();
        $this->assertSession(self::TEST_MEMBER_AGATHA_ID, 'Impersonation.impersonated_member_id');
        $this->post('/gathering-attendances/mobile-rsvp', $this->payload($gathering->id, $context));
        $this->assertResponseCode(403);
        $this->assertFalse($this->json()['success']);
        $this->assertSame(0, $this->attendances()->find()->where(['gathering_id' => $gathering->id])->count());
    }

    private function contextFor(int $memberId): array
    {
        $this->authenticateAsMember($memberId);
        $this->get('/offline/context');
        $this->assertResponseOk();
        $this->assertHeader('Cache-Control', 'no-store');
        $this->assertTrue($this->json()['success']);

        return $this->json()['data'];
    }

    private function payload(int $gatheringId, array $context, ?string $requestId = null): array
    {
        return [
            'gathering_id' => $gatheringId,
            'offline_request_id' => $requestId ?? Text::uuid(),
            'offline_owner' => $context['owner'],
            'offline_epoch' => $context['epoch'],
        ];
    }

    private function gathering(): object
    {
        $table = $this->getTableLocator()->get('Gatherings');

        return $table->saveOrFail($table->newEntity([
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => 1,
            'name' => 'Offline Replay HTTP Gathering',
            'start_date' => '2099-08-10 10:00:00',
            'end_date' => '2099-08-10 16:00:00',
            'timezone' => 'America/Chicago',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
    }

    private function attendances(): Table
    {
        return $this->getTableLocator()->get('GatheringAttendances');
    }

    private function json(): array
    {
        return json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
