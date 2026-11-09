<?php
declare(strict_types=1);

namespace App\Services;

use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\TableRegistry;

/** Refreshes development events by stored public ID using the normal activity catalogue. */
class PublicCalendarDemoService
{
    public const LEGACY_PREFIX = '[Calendar Demo] ';

    public const EVENT_IDS_SETTING = 'Development.PublicCalendar.EventIds';

    /** @param \Cake\ORM\Locator\LocatorInterface|null $tables Tenant-scoped tables */
    public function __construct(private ?LocatorInterface $tables = null)
    {
    }

    /**
     * @param \Cake\I18n\DateTime|null $today Reference day in the demo's event timezone
     * @param bool $refreshActivityIcons Explicitly apply the normal catalogue's latest icon defaults
     * @param bool $refreshActivityColors Explicitly apply the normal catalogue's latest color defaults
     * @return array{events: int, activities: int} Refreshed fixture counts
     */
    public function refresh(
        ?DateTime $today = null,
        bool $refreshActivityIcons = false,
        bool $refreshActivityColors = false,
    ): array {
        $tables = $this->tables ?? TableRegistry::getTableLocator();
        $events = $tables->get('Gatherings');

        return $events->getConnection()->transactional(function () use (
            $tables,
            $events,
            $today,
            $refreshActivityIcons,
            $refreshActivityColors,
        ): array {
            $events->getConnection()->execute('SELECT pg_advisory_xact_lock(731928041)');
            $today = ($today ?? DateTime::now('America/Chicago'))->startOfDay();
            $friday = $today->modify('next friday');
            $admin = $tables->get('Members')->find()
                ->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
            $branch = $tables->get('Branches')->get($admin->branch_id);
            $hostGroups = $tables->get('Branches')->find()->where(['type' => 'Local Group'])
                ->orderBy(['name' => 'ASC', 'id' => 'ASC'])->all()->toList();
            $hostGroups = $hostGroups ?: [$branch];
            $settings = $tables->get('AppSettings');
            $state = $settings->find()->where(['name' => self::EVENT_IDS_SETTING])->first()
                ?? $settings->newEntity(['name' => self::EVENT_IDS_SETTING, 'type' => 'string', 'value' => '{}']);
            $publicIds = json_decode($state->value, true, flags: JSON_THROW_ON_ERROR);
            $eventType = $tables->get('GatheringTypes')->find()->orderBy(['id' => 'ASC'])->firstOrFail();
            $activityIds = (new GatheringActivitySeedService($tables))
                ->seed($refreshActivityIcons, $refreshActivityColors);
            $fixtures = [
                'crown-circles' => ['Crown & Order Circles', 0, 2, [
                    'Royal Visit', 'Laurel Circle', 'Pelican Circle', 'Chivalric Circle',
                    'Kingdom Court', 'Feast', 'Target Archery', 'Gate',
                ]],
                'martial-muster' => ['Martial Muster', 7, 2, [
                    'Armored Combat', 'Steel Combat', 'Target Archery', 'Youth Armored Combat',
                ]],
                'war-arts' => ['War & Arts Encampment', 21, 9, [
                    'Armored Combat', 'Target Archery', 'Arts & Sciences Class',
                    'Bardic', 'Feast', 'Youth Armored Combat',
                ]],
                'guild-classes' => ['Guild Business & Classes', 35, 2, [
                    'Guild Meeting', 'Arts & Sciences Class', 'Bardic', 'Other Activity', 'Gate',
                ]],
                'youth-family' => ['Youth & Family Day', 49, 2, [
                    'Youth Armored Combat', 'Feast', 'Arts & Sciences Class',
                ]],
                'crown-feast' => ['Crown Court & Feast', 63, 2, [
                    'Royal Visit', 'Kingdom Court', 'Laurel Circle', 'Feast', 'Defense Circle',
                    'Order of the Mark Circle', 'Golden Lance Circle', 'Iris of Merit Circle',
                    'Star of Merit Circle', 'Blades of Merit Circle', 'Centurion Circle', "Arc d'Or Circle",
                ]],
            ];
            $links = $tables->get('GatheringsGatheringActivities');
            $index = 0;
            foreach ($fixtures as $key => [$name, $offset, $days, $names]) {
                $event = isset($publicIds[$key])
                    ? $events->find()->where(['public_id' => $publicIds[$key]])->first() : null;
                $event ??= $events->find()->where(['name' => self::LEGACY_PREFIX . $name])->first()
                    ?? $events->find()->where(['name' => $name])->first() ?? $events->newEmptyEntity();
                if (!$event->isNew() && !str_starts_with($event->name, self::LEGACY_PREFIX)) {
                    $name = $event->name;
                }
                $start = $friday->addDays($offset)->setTime(18, 0);
                $hostBranchId = $event->isNew() || $event->branch_id === $admin->branch_id
                    ? $hostGroups[$index % count($hostGroups)]->id : $event->branch_id;
                $event = $events->patchEntity($event, [
                    'name' => $name, 'branch_id' => $hostBranchId, 'gathering_type_id' => $eventType->id,
                    'created_by' => $admin->id,
                    'timezone' => 'America/Chicago',
                    'start_date' => $start->setTimezone('UTC'),
                    'end_date' => $start->addDays($days)->setTime(17, 0)->setTimezone('UTC'),
                    'location' => (100 + $index) . ' Demonstration Way, Example Town, TX 75001',
                    'description' => 'Join us for ' . $name . '. See the activities and event information below.',
                    'public_page_enabled' => true, 'is_cancelled' => false,
                ]);
                $event->set('published', true);
                $event->set('published_on', $today->setTimezone('UTC'));
                $events->saveOrFail($event);
                $publicIds[$key] = $event->public_id;
                $index++;
                foreach ($names as $order => $activityName) {
                    $conditions = [
                        'gathering_id' => $event->id, 'gathering_activity_id' => $activityIds[$activityName],
                    ];
                    $link = $links->find()->where($conditions)->first() ?? $links->newEmptyEntity();
                    $links->saveOrFail($links->patchEntity($link, $conditions + ['sort_order' => $order]));
                }
            }
            $state->value = json_encode($publicIds, JSON_THROW_ON_ERROR);
            $settings->saveOrFail($state);

            return ['events' => count($fixtures), 'activities' => count($activityIds)];
        });
    }
}
