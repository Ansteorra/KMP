<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Database\Connection;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\TableRegistry;

/** Seeds the normal development catalogue and consolidates known obsolete fixture names. */
class GatheringActivitySeedService
{
    private const ALIASES = [
        'Steel Combat' => ['Rapier Combat', 'Rapier'],
        'Target Archery' => ['Archery'],
        'Arts & Sciences Class' => ['Arts Workshop'],
        'Youth Armored Combat' => ['Youth Combat', 'Youth Activities'],
        'Defense Circle' => ['Order of Defense Circle', 'Defence Circle', 'Defense'],
        'Order of the Mark Circle' => ['Mark Circle', 'The Mark Circle', 'Order of the Mark'],
        'Golden Lance Circle' => ['Golden Lance'],
        'Iris of Merit Circle' => ['Iris of Merit'],
        'Star of Merit Circle' => ['Star of Merit'],
        'Blades of Merit Circle' => ['Blade of Merit Circle', 'Blade of Merit', 'Blades of Merit'],
        'Centurion Circle' => ['Centurions Circle', 'Centurions of the Sable Star Circle', 'Centurion'],
        "Arc d'Or Circle" => ['Arc De Or Circle', 'Arc De Or', 'Arc d’Or Circle', "Arc d'Or"],
    ];

    private const LEGACY_NAMES = [
        '[Calendar Demo] Royal Visit' => 'Royal Visit',
        '[Calendar Demo] Laurel Circle' => 'Laurel Circle',
        '[Calendar Demo] Pelican Circle' => 'Pelican Circle',
        '[Calendar Demo] Kingdom Court' => 'Kingdom Court',
        '[Calendar Demo] Guild Meeting' => 'Guild Meeting',
        '[Calendar Demo] Armored Combat' => 'Armored Combat',
        '[Calendar Demo] Rapier' => 'Steel Combat',
        '[Calendar Demo] Archery' => 'Target Archery',
        '[Calendar Demo] Arts Workshop' => 'Arts & Sciences Class',
        '[Calendar Demo] Bardic' => 'Bardic',
        '[Calendar Demo] Youth Activities' => 'Youth Armored Combat',
        '[Calendar Demo] Feast' => 'Feast',
        'Other Gathering' => 'Other Activity',
        '[Calendar Demo] Other Gathering' => 'Other Activity',
        '[Local icon test] Archery' => 'Target Archery',
        '[Local icon test] Armored Combat' => 'Armored Combat',
        '[Local icon test] Royal Court' => 'Kingdom Court',
    ];

    /** @param \Cake\ORM\Locator\LocatorInterface|null $tables Tenant-scoped tables */
    public function __construct(private ?LocatorInterface $tables = null)
    {
    }

    /**
     * @param string $name Existing normal catalogue name
     * @return string Canonical seed lookup key, retaining the actual record's display name
     */
    public static function canonicalName(string $name): string
    {
        $name = trim($name);
        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array(strtolower($name), array_map('strtolower', $aliases), true)) {
                return $canonical;
            }
        }

        return $name;
    }

    /** @return array<string, array{activity_type: string, icon_name: string, display_order: int, foreground_color: string, background_color: string}> Normal catalogue defaults */
    public static function definitions(): array
    {
        $palettes = [
            'gold' => ['#745100', '#FFF3CD'],
            'green' => ['#14532D', '#E8F5E9'],
            'crimson' => ['#991B1B', '#FEE2E2'],
            'blue' => ['#1E40AF', '#DBEAFE'],
            'purple' => ['#6B21A8', '#F3E8FF'],
            'slate' => ['#334155', '#F1F5F9'],
            'teal' => ['#115E59', '#CCFBF1'],
            'amber' => ['#92400E', '#FEF3C7'],
            'black-gold' => ['#292524', '#FDE68A'],
        ];
        $rows = [
            'Royal Visit' => ['Royal Progress', 'crown', 0, 'gold'],
            'Crown Presence' => ['Royal Progress', 'crown', 2, 'gold'],
            'Laurel Circle' => ['Order Circle', 'laurel-wreath', 10, 'green'],
            'Pelican Circle' => ['Order Circle', 'pelican-vulning', 11, 'crimson'],
            'Chivalric Circle' => ['Order Circle', 'chain-necklace', 12, 'gold'],
            'Defense Circle' => ['Order Circle', 'master-of-defense', 13, 'blue'],
            'Order of the Mark Circle' => ['Order Circle', 'order-of-the-mark', 14, 'crimson'],
            'Golden Lance Circle' => ['Order Circle', 'lance', 15, 'gold'],
            'Iris of Merit Circle' => ['Order Circle', 'rainbow', 16, 'purple'],
            'Star of Merit Circle' => ['Order Circle', 'ansteorran-star', 17, 'black-gold'],
            'Blades of Merit Circle' => ['Order Circle', 'single-rapier', 18, 'blue'],
            'Centurion Circle' => ['Order Circle', 'phoenix', 19, 'crimson'],
            "Arc d'Or Circle" => ['Order Circle', 'archery', 20, 'green'],
            'Kingdom Court' => ['Court', 'court-crown', 20, 'gold'],
            'Baronial Court' => ['Court', 'court-pearls', 21, 'purple'],
            'Gate' => ['Business', 'door-enter', 30, 'slate'],
            'Armored Combat' => ['Martial', 'armored-combat', 40, 'crimson'],
            'Steel Combat' => ['Martial', 'fencing', 41, 'blue'],
            'Target Archery' => ['Martial', 'archery', 42, 'green'],
            'Thrown Weapons' => ['Martial', 'axe-rounded', 43, 'amber'],
            'Equestrian' => ['Martial', 'equestrian', 44, 'teal'],
            'Arts & Sciences Class' => ['Arts & Sciences', 'group-class', 50, 'purple'],
            'Bardic' => ['Arts & Sciences', 'bardic-circle', 51, 'blue'],
            'Youth Armored Combat' => ['Youth', 'armored-combat', 60, 'teal'],
            'Youth Rapier Combat' => ['Youth', 'fencing', 61, 'teal'],
            'Feast' => ['General', 'shared-feast', 70, 'amber'],
            'Guild Meeting' => ['General', 'round-table', 71, 'purple'],
            'Other Activity' => ['Other', 'encampment', 80, 'slate'],
        ];
        $definitions = [];
        foreach ($rows as $name => [$type, $icon, $order, $palette]) {
            [$foreground, $background] = $palettes[$palette];
            $definitions[$name] = [
                'activity_type' => $type, 'icon_name' => $icon, 'display_order' => $order,
                'foreground_color' => $foreground, 'background_color' => $background,
            ];
        }

        return $definitions;
    }

    /**
     * @param bool $refreshIcons Explicitly reapply seed icon defaults, preserving uploaded artwork
     * @param bool $refreshColors Explicitly reapply seed color defaults, preserving uploaded artwork
     * @return array<string, int> Canonical activity names mapped to retained catalogue IDs
     */
    public function seed(bool $refreshIcons = false, bool $refreshColors = false): array
    {
        $tables = $this->tables ?? TableRegistry::getTableLocator();
        $catalogue = $tables->get('GatheringActivities');
        $connection = $catalogue->getConnection();

        return $connection->transactional(function () use (
            $catalogue,
            $connection,
            $refreshIcons,
            $refreshColors,
        ): array {
            $connection->execute('SELECT pg_advisory_xact_lock(731928042)');
            $existing = [];
            foreach ($catalogue->find()->orderBy(['id' => 'ASC']) as $activity) {
                $existing[strtolower(trim($activity->name))] ??= $activity;
            }
            $ids = [];
            foreach (self::definitions() as $name => $defaults) {
                $activity = null;
                foreach ([$name, ...(self::ALIASES[$name] ?? [])] as $candidate) {
                    $activity = $existing[strtolower($candidate)] ?? null;
                    if ($activity !== null) {
                        break;
                    }
                }
                if ($activity === null) {
                    foreach (self::LEGACY_NAMES as $legacy => $canonical) {
                        if ($canonical === $name && isset($existing[strtolower($legacy)])) {
                            $activity = $existing[strtolower($legacy)];
                            $activity->name = $name;
                            if (str_starts_with($legacy, '[')) {
                                $activity->description = '';
                                $activity->activity_type = $defaults['activity_type'];
                            }
                            break;
                        }
                    }
                }
                if ($activity === null) {
                    $activity = $catalogue->newEntity(['name' => $name, 'icon_source' => 'catalog'] + $defaults);
                } elseif (($activity->activity_type ?? 'General') === 'General') {
                    $activity->activity_type = $defaults['activity_type'];
                }
                if ($refreshIcons && $activity->icon_source !== 'upload') {
                    $activity->icon_name = $defaults['icon_name'];
                    $activity->icon_source = 'catalog';
                }
                if ($refreshColors && $activity->icon_source !== 'upload') {
                    $activity->foreground_color = $defaults['foreground_color'];
                    $activity->background_color = $defaults['background_color'];
                }
                $catalogue->saveOrFail($activity);
                $ids[$name] = $activity->id;
            }
            foreach (self::LEGACY_NAMES as $legacy => $canonical) {
                $old = $existing[strtolower($legacy)] ?? null;
                if ($old !== null && $old->id !== $ids[$canonical]) {
                    $this->mergeReferences($connection, $old->id, $ids[$canonical]);
                    $catalogue->deleteAll(['id' => $old->id]);
                }
            }

            return $ids;
        });
    }

    /**
     * @param \Cake\Database\Connection $connection Current tenant connection
     * @param int $old Obsolete fixture activity
     * @param int $normal Retained normal activity
     * @return void
     */
    private function mergeReferences(Connection $connection, int $old, int $normal): void
    {
        $relations = [
            'gatherings_gathering_activities' => 'gathering_id',
            'gathering_type_gathering_activities' => 'gathering_type_id',
            'award_gathering_activities' => 'award_id',
            'waivers_gathering_activity_waivers' => 'waiver_type_id',
            'gathering_activity_assignment_permissions' => 'permission_id',
        ];
        $available = array_fill_keys($connection->getSchemaCollection()->listTables(), true);
        foreach ($relations as $table => $parent) {
            if (!isset($available[$table])) {
                continue;
            }
            $rows = $connection->execute(
                "SELECT * FROM $table WHERE gathering_activity_id = ?",
                [$old],
            )->fetchAll('assoc');
            foreach ($rows as $row) {
                $conditions = ['gathering_activity_id' => $normal, $parent => $row[$parent]];
                if (array_key_exists('deleted', $row)) {
                    $conditions[$row['deleted'] === null ? 'deleted IS' : 'deleted'] = $row['deleted'];
                }
                $query = $connection->selectQuery()->select('*')->from($table)->where($conditions);
                $duplicate = $query->execute()->fetch('assoc');
                if (!$duplicate) {
                    $connection->update($table, ['gathering_activity_id' => $normal], ['id' => $row['id']]);
                    continue;
                }
                $merged = [];
                if (array_key_exists('not_removable', $row)) {
                    $merged['not_removable'] = $row['not_removable'] || $duplicate['not_removable'];
                }
                if (
                    !empty($row['custom_description'])
                    && $row['custom_description'] !== $duplicate['custom_description']
                ) {
                    $merged['custom_description'] = implode("\n\n", array_filter([
                        $duplicate['custom_description'], $row['custom_description'],
                    ]));
                }
                if ($merged !== []) {
                    $connection->update($table, $merged, ['id' => $duplicate['id']], ['not_removable' => 'boolean']);
                }
                $connection->delete($table, ['id' => $row['id']]);
            }
        }
        if (isset($available['gathering_scheduled_activities'])) {
            $connection->update(
                'gathering_scheduled_activities',
                ['gathering_activity_id' => $normal],
                ['gathering_activity_id' => $old],
            );
        }
    }
}
