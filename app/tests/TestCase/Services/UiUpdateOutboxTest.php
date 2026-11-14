<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\UiBulkMutation;
use App\Services\UiUpdateOutbox;
use App\Services\UiUpdateRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\Configure;
use Cake\Utility\Text;
use RuntimeException;

class UiUpdateOutboxTest extends BaseTestCase
{
    private function tenant(): TenantMetadata
    {
        return new TenantMetadata('11111111-1111-4111-8111-111111111111', 'test', 'Test', 'active', 'db', 'test', 'test');
    }

    public function testNoteInvalidationSharesOnlyMetadataAndRollsBackWithTheWrite(): void
    {
        $notes = $this->getTableLocator()->get('Notes');
        $connection = $notes->getConnection();
        $savePoints = $connection->isSavePointsEnabled();
        $connection->enableSavePoints(true);
        Configure::write('Turbo.enabled', true);
        Configure::write('Turbo.liveEnabled', true);
        try {
            TenantContext::with($this->tenant(), function () use ($notes, $connection): void {
                $before = (int)$connection->execute('SELECT COUNT(*) FROM ui_update_outbox')->fetchColumn(0);
                $connection->transactional(function () use ($notes, $connection, $before): bool {
                    $notes->saveOrFail($notes->newEntity([
                        'entity_type' => 'Members', 'entity_id' => self::ADMIN_MEMBER_ID,
                        'author_id' => self::ADMIN_MEMBER_ID, 'subject' => 'Private test subject',
                        'body' => 'Secret text never sent over live channels', 'private' => true,
                    ]));
                    $rows = $connection->execute('SELECT topics FROM ui_update_outbox')->fetchAll('assoc');
                    $this->assertCount($before + 1, $rows);
                    $encoded = json_encode($rows, JSON_THROW_ON_ERROR);
                    $this->assertStringContainsString('Members:' . self::ADMIN_MEMBER_ID, $encoded);
                    $this->assertStringNotContainsString('Private test subject', $encoded);
                    $this->assertStringNotContainsString('Secret text', $encoded);

                    return false;
                });
                $this->assertSame($before, (int)$connection->execute('SELECT COUNT(*) FROM ui_update_outbox')->fetchColumn(0));
            });
        } finally {
            $connection->enableSavePoints($savePoints);
            Configure::delete('Turbo');
        }
    }

    public function testMovingAttendanceInvalidatesBothOldAndNewGatherings(): void
    {
        $table = $this->getTableLocator()->get('GatheringAttendances');
        $entity = $table->newEmptyEntity();
        $entity->patch(['id' => 'attendance', 'gathering_id' => 'new-event', 'member_id' => self::ADMIN_MEMBER_ID]);
        $changes = UiUpdateRegistry::changes($table, $entity, ['gathering_id' => 'old-event']);
        $this->assertSame(['attendance'], $changes['Gatherings:old-event']);
        $this->assertSame(['attendance'], $changes['Gatherings:new-event']);
        $this->assertSame(['attendance'], $changes['Members:' . self::ADMIN_MEMBER_ID]);
    }

    public function testNotesInvalidateTheirOwnerListWithoutPretendingTheOwnerCatalogueChanged(): void
    {
        $table = $this->getTableLocator()->get('Notes');
        $note = $table->newEmptyEntity()->patch([
            'id' => 'note', 'entity_type' => 'Members', 'entity_id' => self::ADMIN_MEMBER_ID,
        ], ['guard' => false]);
        $topics = UiUpdateRegistry::changes($table, $note);
        $this->assertSame(['notes'], $topics['Members:list']);
        $this->assertArrayNotHasKey('Members:catalogue', $topics);
        $members = $this->getTableLocator()->get('Members');
        $member = $members->get(self::ADMIN_MEMBER_ID);
        $this->assertSame(['record'], UiUpdateRegistry::changes($members, $member)['Members:catalogue']);
    }

    public function testCompositeJoinSavesAndBulkDeletesRetainTheirOwnerInvalidations(): void
    {
        $links = $this->getTableLocator()->get('RolesPermissions');
        $link = $links->find()->firstOrFail();
        Configure::write('Turbo.enabled', true);
        Configure::write('Turbo.liveEnabled', true);
        try {
            TenantContext::with($this->tenant(), function () use ($links, $link): void {
                $link->setDirty('role_id', true);
                $links->saveOrFail($link);
                $this->assertSame(1, UiBulkMutation::delete($links, [
                    'permission_id' => $link->permission_id, 'role_id' => $link->role_id,
                ]));
                $topics = [];
                foreach ($links->getConnection()->execute('SELECT topics FROM ui_update_outbox')->fetchAll('assoc') as $row) {
                    $topics += json_decode($row['topics'], true, flags: JSON_THROW_ON_ERROR);
                }
                $this->assertSame(['permissions'], $topics['Roles:' . $link->role_id]);
                $this->assertSame(['permissions'], $topics['Permissions:' . $link->permission_id]);
                $this->assertSame(['permissions'], $topics['Security:list']);
            });
        } finally {
            Configure::delete('Turbo');
        }
    }

    public function testDisabledLiveDeliveryDoesNotCreateOutboxRows(): void
    {
        Configure::write('Turbo.liveEnabled', false);
        $table = $this->getTableLocator()->get('Members');
        $connection = $table->getConnection();
        $before = (int)$connection->execute('SELECT COUNT(*) FROM ui_update_outbox')->fetchColumn(0);
        try {
            $member = $table->get(self::ADMIN_MEMBER_ID);
            $table->saveOrFail($member);
            $this->assertSame($before, (int)$connection->execute('SELECT COUNT(*) FROM ui_update_outbox')->fetchColumn(0));
        } finally {
            Configure::delete('Turbo');
        }
    }

    public function testBulkMoveInvalidatesBothOwnersAndBulkDeleteRetainsTheFormerOwner(): void
    {
        $notes = $this->getTableLocator()->get('Notes');
        $connection = $notes->getConnection();
        Configure::write('Turbo.enabled', true);
        Configure::write('Turbo.liveEnabled', false);
        $note = $notes->saveOrFail($notes->newEntity([
            'entity_type' => 'Members', 'entity_id' => self::ADMIN_MEMBER_ID,
            'author_id' => self::ADMIN_MEMBER_ID, 'subject' => 'Bulk test', 'body' => 'Private body',
        ]));
        Configure::write('Turbo.liveEnabled', true);
        try {
            TenantContext::with($this->tenant(), function () use ($notes, $connection, $note): void {
                $this->assertSame(1, UiBulkMutation::update(
                    $notes,
                    ['entity_id' => self::TEST_MEMBER_AGATHA_ID],
                    ['id' => $note->id],
                ));
                $rows = $connection->execute('SELECT topics FROM ui_update_outbox')->fetchAll('assoc');
                $topics = json_decode(end($rows)['topics'], true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(['notes'], $topics['Members:' . self::ADMIN_MEMBER_ID]);
                $this->assertSame(['notes'], $topics['Members:' . self::TEST_MEMBER_AGATHA_ID]);
                $this->assertStringNotContainsString('Private body', json_encode($rows));
                $this->assertSame(1, UiBulkMutation::delete($notes, ['id' => $note->id]));
                $rows = $connection->execute('SELECT topics FROM ui_update_outbox')->fetchAll('assoc');
                $topics = json_decode(end($rows)['topics'], true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(['notes'], $topics['Members:' . self::TEST_MEMBER_AGATHA_ID]);
                $this->assertSame(0, UiBulkMutation::update($notes, ['subject' => 'Absent'], ['id' => $note->id]));
            });
        } finally {
            Configure::delete('Turbo');
        }
    }

    public function testBulkWriteAndItsInvalidationRollBackTogether(): void
    {
        $members = $this->getTableLocator()->get('Members');
        $connection = $members->getConnection();
        $savePoints = $connection->isSavePointsEnabled();
        $connection->enableSavePoints(true);
        $name = $members->get(self::ADMIN_MEMBER_ID)->sca_name;
        Configure::write('Turbo.enabled', true);
        Configure::write('Turbo.liveEnabled', true);
        try {
            TenantContext::with($this->tenant(), function () use ($members, $connection): void {
                $before = (int)$connection->execute('SELECT COUNT(*) FROM ui_update_outbox')->fetchColumn(0);
                $connection->transactional(function () use ($members): bool {
                    $this->assertSame(1, UiBulkMutation::update(
                        $members,
                        ['sca_name' => 'Rolled back name'],
                        ['id' => self::ADMIN_MEMBER_ID],
                    ));

                    return false;
                });
                $this->assertSame($before, (int)$connection->execute('SELECT COUNT(*) FROM ui_update_outbox')->fetchColumn(0));
            });
            $this->assertSame($name, $members->get(self::ADMIN_MEMBER_ID)->sca_name);
        } finally {
            $connection->enableSavePoints($savePoints);
            Configure::delete('Turbo');
        }
    }

    public function testFailedDeliveryRemainsRetryableAndCannotPublishAnotherTenantsRows(): void
    {
        $connection = $this->getTableLocator()->get('Members')->getConnection();
        $id = Text::uuid();
        $foreignId = Text::uuid();
        foreach ([$id => $this->tenant()->id, $foreignId => Text::uuid()] as $eventId => $tenantId) {
            $connection->insert('ui_update_outbox', [
                'id' => $eventId, 'tenant_id' => $tenantId,
                'topics' => '{"Members:list":["record"]}',
            ]);
        }
        TenantContext::with($this->tenant(), function () use ($connection, $id, $foreignId): void {
            $attempted = [];
            $failing = new UiUpdateOutbox(function (array $event) use (&$attempted): void {
                $attempted[] = $event['id'];
                throw new RuntimeException('Synthetic delivery outage');
            });
            $this->assertSame(0, $failing->dispatch($connection));
            $this->assertSame([$id], $attempted);
            $row = $connection->execute(
                'SELECT attempts, delivered FROM ui_update_outbox WHERE id = :id',
                ['id' => $id],
            )->fetch('assoc');
            $this->assertSame(1, (int)$row['attempts']);
            $this->assertNull($row['delivered']);
            $successful = new UiUpdateOutbox(function (array $event) use (&$attempted): void {
                $attempted[] = $event['id'];
            });
            $this->assertSame(0, $successful->dispatch($connection));
            $connection->execute(
                'UPDATE ui_update_outbox SET available_after = CURRENT_TIMESTAMP WHERE id = :id',
                ['id' => $id],
            );
            $this->assertSame(1, $successful->dispatch($connection));
            $this->assertSame(0, $successful->dispatch($connection));
            $this->assertSame([$id, $id], $attempted);
            $foreignRow = $connection->execute(
                'SELECT id, delivered FROM ui_update_outbox WHERE id = :id',
                ['id' => $foreignId],
            )->fetch('assoc');
            $this->assertSame($foreignId, $foreignRow['id']);
            $this->assertNull($foreignRow['delivered']);
        });
    }
}
