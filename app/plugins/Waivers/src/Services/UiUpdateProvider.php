<?php
declare(strict_types=1);

namespace Waivers\Services;

use App\Services\UiUpdateProviderInterface;
use App\Services\UiUpdateRegistry;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;

/** Invalidation metadata is resolved against the active tenant at use time. */
final class UiUpdateProvider implements UiUpdateProviderInterface
{
    /** @inheritDoc */
    public static function definitions(): array
    {
        return [
            'Waivers.WaiverTypes' => ['controller' => 'WaiverTypes',
                'invalidatesScreens' => ['Gatherings', 'Waivers.GatheringWaivers']],
            'Waivers.GatheringWaivers' => [
                'controller' => 'GatheringWaivers',
                'parents' => ['gathering_id' => 'Gatherings'],
                'changes' => ['waivers'],
                'contexts' => [
                    'view' => [self::class, 'viewContext'],
                    'dashboard' => [self::class, 'dashboardContext'],
                    'needingWaivers' => [self::class, 'dashboardContext'],
                ],
            ],
            'Waivers.GatheringActivityWaivers' => [
                'changes' => ['waivers'],
                'parents' => [
                    'gathering_activity_id' => 'GatheringActivities', 'waiver_type_id' => 'Waivers.WaiverTypes',
                ],
                'invalidatesScreens' => ['Gatherings', 'Waivers.GatheringWaivers'],
            ],
            'Waivers.GatheringWaiverLosses' => [
                'parents' => ['gathering_id' => 'Gatherings'],
                'changes' => ['waivers'],
            ],
            'Waivers.GatheringWaiverClosures' => [
                'parents' => ['gathering_id' => 'Gatherings'],
                'changes' => ['waivers'],
            ],
        ];
    }

    /** Pending and persisted waiver details follow the same policies as the normal view. */
    public static function viewContext(ServerRequest $request, array $params): array
    {
        $locator = TableRegistry::getTableLocator();
        $table = $locator->get('Waivers.GatheringWaivers');
        $id = $params['pass'][0] ?? null;
        $action = 'view';
        if ($id !== null) {
            $waiver = $table->get($id, contain: ['Gatherings']);
        } else {
            $gatheringId = (int)$request->getQuery('gathering_id');
            $typeId = (int)$request->getQuery('waiver_type_id');
            if ($gatheringId <= 0 || $typeId <= 0) {
                throw new BadRequestException('Gathering and waiver type are required.');
            }
            $waiver = $table->find()->contain(['Gatherings'])
                ->where(['GatheringWaivers.gathering_id' => $gatheringId, 'GatheringWaivers.waiver_type_id' => $typeId])
                ->orderBy(['GatheringWaivers.created' => 'DESC', 'GatheringWaivers.id' => 'DESC'])->first();
            if (!$waiver) {
                $waiver = $table->newEmptyEntity();
                $waiver->gathering_id = $gatheringId;
                $waiver->waiver_type_id = $typeId;
                $waiver->gathering = $locator->get('Gatherings')->get(
                    $gatheringId,
                    contain: ['GatheringTypes', 'Branches'],
                );
                $waiver->waiver_type = $locator->get('Waivers.WaiverTypes')->get($typeId);
                $waiver->status = 'pending';
                $action = 'viewGatheringWaivers';
                $required = $locator->get('Waivers.GatheringActivityWaivers')->find()
                    ->innerJoinWith('GatheringActivities.Gatherings')->where([
                        'Gatherings.id' => $gatheringId, 'GatheringActivityWaivers.waiver_type_id' => $typeId,
                    ])->count();
                if (
                    !$required && !$locator->get('Waivers.GatheringWaiverLosses')->exists([
                    'gathering_id' => $gatheringId, 'waiver_type_id' => $typeId,
                    ])
                ) {
                    throw new NotFoundException('This waiver is not required for this gathering.');
                }
            }
        }

        return ['resource' => $waiver, 'action' => $action,
            'topic' => UiUpdateRegistry::topic('Gatherings', $waiver->gathering_id)];
    }

    /** Repeat the dashboard's URL policy; all waiver owners invalidate the gathering list. */
    public static function dashboardContext(ServerRequest $request, array $params): array
    {
        $action = $params['action'];

        return [
            'resource' => [
                'controller' => 'GatheringWaivers', 'action' => $action,
                'plugin' => 'Waivers', 'prefix' => null, $params['pass'] ?? [],
            ],
            'action' => $action, 'topic' => UiUpdateRegistry::topic('Gatherings'),
        ];
    }
}
