<?php
declare(strict_types=1);

namespace App\Test\TestCase\Config;

use App\Test\TestCase\BaseTestCase;
use Migrations\Migration\Environment;
use RemoveRoyalProgressFlagFromOffices;
use RemoveRoyalProgressFromGatheringAttendances;

class RemoveRoyalProgressRsvpMigrationTest extends BaseTestCase
{
    public function testUpgradePreservesAttendanceAndOfficesAndRemovesRetiredSavedViewFilters(): void
    {
        require_once ROOT . '/config/Migrations/20261104220000_RemoveRoyalProgressFromGatheringAttendances.php';
        require_once ROOT . '/plugins/Officers/config/Migrations/20261104220100_RemoveRoyalProgressFlagFromOffices.php';
        $adapter = (new Environment('retired-royal-rsvp-test', ['connection' => 'test']))->getAdapter();
        $core = (new RemoveRoyalProgressFromGatheringAttendances(20261104220000))->setAdapter($adapter);
        $plugin = (new RemoveRoyalProgressFlagFromOffices(20261104220100))->setAdapter($adapter);
        $attendances = $this->getTableLocator()->get('GatheringAttendances');
        $connection = $attendances->getConnection();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $attendance = $attendances->saveOrFail($attendances->newEntity([
            'gathering_id' => $gathering->id, 'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'created_by' => self::ADMIN_MEMBER_ID, 'share_with_kingdom' => false,
            'public_note' => 'Keep this ordinary attendance note.',
        ]));
        $office = $this->getTableLocator()->get('Officers.Offices')->find()->firstOrFail();
        $views = $this->getTableLocator()->get('GridViews');
        $remaining = ['field' => 'branch_id', 'operator' => 'eq', 'value' => self::KINGDOM_BRANCH_ID];
        $retired = ['field' => 'royal_progress', 'operator' => 'in', 'value' => ['any']];
        $config = [
            'filters' => [$retired, $remaining],
            'expression' => ['type' => 'AND', 'conditions' => [
                ['type' => 'OR', 'conditions' => [$retired]], $remaining,
            ]],
            'columns' => [['key' => 'name', 'visible' => true], ['key' => 'royal_progress', 'visible' => true]],
            'sort' => [['field' => 'start_date', 'direction' => 'asc']],
            'pageSize' => 50, 'optional' => null,
        ];
        $view = $views->saveOrFail($views->newEntity([
            'name' => 'Retired Royal Progress filter', 'grid_key' => 'Gatherings.calendar.main',
            'member_id' => self::ADMIN_MEMBER_ID, 'config' => json_encode($config, JSON_THROW_ON_ERROR),
        ]));
        $other = $views->saveOrFail($views->newEntity([
            'name' => 'Unrelated saved view', 'grid_key' => 'Members.index.main',
            'member_id' => self::ADMIN_MEMBER_ID, 'config' => json_encode($config, JSON_THROW_ON_ERROR),
        ]));
        $emptyConfig = ['expression' => ['type' => 'OR', 'conditions' => []], 'filters' => []];
        $emptyView = $views->saveOrFail($views->newEntity([
            'name' => 'Unrelated empty expression', 'grid_key' => 'Gatherings.index.main',
            'member_id' => self::ADMIN_MEMBER_ID, 'config' => json_encode($emptyConfig, JSON_THROW_ON_ERROR),
        ]));
        $core->down();
        $plugin->down();
        $connection->execute('UPDATE gathering_attendances SET is_royal_progress = TRUE, progress_office_name = ? WHERE id = ?', ['Crown', $attendance->id]);
        $connection->execute('UPDATE officers_offices SET is_royal_progress = TRUE WHERE id = ?', [$office->id]);
        $core->up();
        $plugin->up();
        $this->assertSame('Keep this ordinary attendance note.', $attendances->get($attendance->id)->public_note);
        $this->assertFalse($attendances->get($attendance->id)->share_with_kingdom);
        $this->assertSame($office->name, $this->getTableLocator()->get('Officers.Offices')->get($office->id)->name);
        $cleaned = $views->get($view->id)->getConfigArray();
        $this->assertSame([$remaining], $cleaned['filters']);
        $this->assertSame(['type' => 'AND', 'conditions' => [$remaining]], $cleaned['expression']);
        $this->assertSame([['key' => 'name', 'visible' => true]], $cleaned['columns']);
        $this->assertSame($config['sort'], $cleaned['sort']);
        $this->assertSame(50, $cleaned['pageSize']);
        $this->assertArrayHasKey('optional', $cleaned);
        $this->assertNull($cleaned['optional']);
        $this->assertSame($config, $views->get($other->id)->getConfigArray());
        $this->assertSame($emptyConfig, $views->get($emptyView->id)->getConfigArray());
        $schema = $connection->getSchemaCollection()->describe('gathering_attendances');
        $this->assertFalse($schema->hasColumn('is_royal_progress'));
        $this->assertNotContains('idx_gathering_attendances_progress', $schema->indexes());
        $this->assertFalse($connection->getSchemaCollection()->describe('officers_offices')->hasColumn('is_royal_progress'));
    }
}
