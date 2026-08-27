<?php
declare(strict_types=1);

namespace App\Test\TestCase\Plugins\Awards\Feature;

use App\Test\TestCase\BaseTestCase;
use App\Test\TestCase\Support\AnsteorraAwardCatalogSeed;

/**
 * Verifies every managed Ansteorra award catalog row loaded into the test seed.
 */
final class AwardCatalogSeedTest extends BaseTestCase
{
    private const APPROVAL_PROCESS_NAMES = [
        1 => 'Single Approver - Crown',
        2 => 'Single Approver - Local',
        3 => 'Single Approver - Principality Coronet',
        4 => 'Dual Approver - Local then Crown',
    ];

    private const BESTOWAL_TEMPLATE_NAMES = [
        1 => 'Kingdom Award',
        2 => 'Principality Award',
        3 => 'Baronial Award',
    ];

    private const RETAINED_TEST_AWARDS = [
        57 => 'Trees are Cool',
        81 => 'Steppes Test Non Armig Award #1',
        82 => 'Steppes Test Non Armig #2',
        83 => 'Glaslyn Test Non Armig 1',
    ];

    public function testSeededCatalogMatchesManagedAnsteorraCatalog(): void
    {
        $catalog = AnsteorraAwardCatalogSeed::loadCatalog();
        $domains = $this->getTableLocator()->get('Awards.Domains');
        $levels = $this->getTableLocator()->get('Awards.Levels');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $approvalProcesses = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $bestowalTemplates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');

        $this->assertCount((int)$catalog['counts']['domains'], $catalog['domains']);
        $this->assertCount((int)$catalog['counts']['levels'], $catalog['levels']);
        $this->assertCount((int)$catalog['counts']['awards'], $catalog['awards']);
        $this->assertSame(
            (int)$catalog['counts']['domains'],
            $domains->find()->where(['deleted IS' => null])->count(),
        );
        $this->assertSame(
            (int)$catalog['counts']['levels'],
            $levels->find()->where(['deleted IS' => null])->count(),
        );
        $this->assertSame(
            (int)$catalog['counts']['awards'] + count(self::RETAINED_TEST_AWARDS),
            $awards->find()->where(['deleted IS' => null])->count(),
        );

        foreach ($catalog['domains'] as $expected) {
            $actual = $domains->get((int)$expected['id']);
            $this->assertSame($expected['name'], $actual->name);
            $this->assertNull($actual->deleted);
        }
        foreach ($catalog['levels'] as $expected) {
            $actual = $levels->get((int)$expected['id']);
            $this->assertSame($expected['name'], $actual->name);
            $this->assertSame((int)$expected['progression_order'], (int)$actual->progression_order);
            $this->assertNull($actual->deleted);
        }
        foreach ($catalog['awards'] as $expected) {
            $actual = $awards->get((int)$expected['target_id']);
            $message = sprintf('Seed award %d (%s)', $expected['target_id'], $expected['name']);
            foreach (
                ['name', 'abbreviation', 'description', 'insignia', 'badge', 'charter'] as $field
            ) {
                $this->assertSame($expected[$field], $actual->get($field), "{$message} field {$field}");
            }
            $this->assertSame(
                $expected['specialties'] === null
                    ? null
                    : json_decode($expected['specialties'], true, flags: JSON_THROW_ON_ERROR),
                $actual->specialties,
                "{$message} field specialties",
            );
            foreach (
                ['domain_id', 'level_id', 'branch_id'] as $field
            ) {
                $expectedId = $expected[$field] === null ? null : (int)$expected[$field];
                $actualId = $actual->get($field) === null ? null : (int)$actual->get($field);
                $this->assertSame($expectedId, $actualId, "{$message} field {$field}");
            }
            $approvalProcess = $approvalProcesses->get((int)$actual->approval_process_id);
            $this->assertSame(
                self::APPROVAL_PROCESS_NAMES[(int)$expected['approval_process_id']],
                $approvalProcess->name,
                "{$message} approval process",
            );
            $bestowalTemplate = $bestowalTemplates->get((int)$actual->bestowal_todo_template_id);
            $this->assertSame(
                self::BESTOWAL_TEMPLATE_NAMES[(int)$expected['bestowal_todo_template_id']],
                $bestowalTemplate->name,
                "{$message} bestowal template",
            );
            $this->assertSame((bool)$expected['is_active'], (bool)$actual->is_active, "{$message} active state");
            $this->assertNull($actual->deleted, "{$message} deletion state");
        }

        foreach (self::RETAINED_TEST_AWARDS as $id => $name) {
            $award = $awards->get($id);
            $this->assertSame($name, $award->name);
            $this->assertNull($award->deleted);
        }
    }
}
