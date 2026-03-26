<?php
declare(strict_types=1);

namespace Waivers\Services;

use App\Services\RetentionPolicyService;
use App\Services\ServiceResult;
use Cake\I18n\Date;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use DateTime;

/**
 * Waiver Mobile Service
 *
 * Handles mobile-specific waiver workflows: gathering selection for mobile upload,
 * mobile upload orchestration, and attestation (exemption) processing.
 */
class WaiverMobileService
{
    /**
     * Get gatherings accessible by the user for mobile waiver upload.
     *
     * @param array|null $branchIds Branch IDs user has permission for (null = global)
     * @param array $stewardGatheringIds Gathering IDs where user is a steward
     * @return array Authorized gatherings with waiver status
     */
    public function getAuthorizedGatherings(?array $branchIds, array $stewardGatheringIds): array
    {
        $hasGlobalAccess = ($branchIds === null);
        $hasBranchAccess = !empty($branchIds);
        $hasStewardAccess = !empty($stewardGatheringIds);

        if (!$hasGlobalAccess && !$hasBranchAccess && !$hasStewardAccess) {
            return [];
        }

        $startDate = new DateTime('+7 days');
        $endDate = new DateTime('-30 days');

        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');

        $gatheringIdsQuery = $GatheringActivityWaivers->find()
            ->innerJoinWith('GatheringActivities.Gatherings')
            ->where([
                'GatheringActivityWaivers.deleted IS' => null,
                'GatheringActivities.deleted IS' => null,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
                'OR' => [
                    'Gatherings.start_date <=' => $startDate,
                    'Gatherings.end_date >=' => $endDate,
                ],
            ])
            ->select(['gathering_id' => 'Gatherings.id'])
            ->distinct(['Gatherings.id']);

        if (!$hasGlobalAccess) {
            $accessConditions = [];
            if (!empty($branchIds)) {
                $accessConditions[] = ['Gatherings.branch_id IN' => $branchIds];
            }
            if (!empty($stewardGatheringIds)) {
                $accessConditions[] = ['Gatherings.id IN' => $stewardGatheringIds];
            }
            if (!empty($accessConditions)) {
                $gatheringIdsQuery->where(['OR' => $accessConditions]);
            }
        }

        $gatheringIds = $gatheringIdsQuery->all()->extract('gathering_id')->toArray();

        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds($gatheringIds);
        if (!empty($closedGatheringIds)) {
            $gatheringIds = array_values(array_diff($gatheringIds, $closedGatheringIds));
        }

        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds($gatheringIds);

        if (empty($gatheringIds)) {
            return [];
        }

        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $allGatherings = $Gatherings->find()
            ->where(['Gatherings.id IN' => $gatheringIds])
            ->contain(['Branches', 'GatheringTypes', 'GatheringActivities'])
            ->orderBy(['Gatherings.start_date' => 'DESC'])
            ->all()
            ->toArray();

        $authorizedGatherings = [];
        $now = new DateTime();

        foreach ($allGatherings as $gathering) {
            $gathering->missing_waiver_count = 0;
            $gathering->missing_waiver_names = [];
            $gathering->is_waiver_complete = true;
            $gathering->is_ready_to_close = in_array($gathering->id, $readyToCloseGatheringIds, true);

            $gathering->is_upcoming = $gathering->start_date > $now;
            $gathering->is_ongoing = $gathering->start_date <= $now && $gathering->end_date >= $now;
            $gathering->is_ended = $gathering->end_date < $now;

            if (!empty($gathering->gathering_activities)) {
                $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();

                $requiredWaiverTypes = $GatheringActivityWaivers->find()
                    ->where([
                        'gathering_activity_id IN' => $activityIds,
                        'deleted IS' => null,
                    ])
                    ->select(['waiver_type_id'])
                    ->distinct(['waiver_type_id'])
                    ->all()
                    ->extract('waiver_type_id')
                    ->toArray();

                if (!empty($requiredWaiverTypes)) {
                    $uploadedWaiverTypes = $GatheringWaivers->find()
                        ->where([
                            'gathering_id' => $gathering->id,
                            'deleted IS' => null,
                            'declined_at IS' => null,
                        ])
                        ->select(['waiver_type_id'])
                        ->distinct(['waiver_type_id'])
                        ->all()
                        ->extract('waiver_type_id')
                        ->toArray();

                    $missingWaiverTypes = array_diff($requiredWaiverTypes, $uploadedWaiverTypes);

                    if (!empty($missingWaiverTypes)) {
                        $WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');
                        $missingWaiverNames = $WaiverTypes->find()
                            ->where(['id IN' => $missingWaiverTypes])
                            ->orderBy(['name' => 'ASC'])
                            ->all()
                            ->extract('name')
                            ->toArray();

                        $gathering->missing_waiver_count = count($missingWaiverTypes);
                        $gathering->missing_waiver_names = $missingWaiverNames;
                        $gathering->is_waiver_complete = false;
                    }
                }
            }

            $authorizedGatherings[] = $gathering;
        }

        return $authorizedGatherings;
    }

    /**
     * Process a waiver attestation (exemption).
     *
     * @param int $gatheringId Gathering ID
     * @param int $waiverTypeId Waiver type ID
     * @param string $reason Exemption reason
     * @param string|null $notes Optional notes
     * @param string|null $referer HTTP referer for redirect URL
     * @return \App\Services\ServiceResult
     */
    public function processAttestation(
        int $gatheringId,
        int $waiverTypeId,
        string $reason,
        ?string $notes,
        ?string $referer,
    ): ServiceResult {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');
        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');

        if (!$waiverTypeId || !$gatheringId || empty($reason)) {
            return new ServiceResult(false, __('Missing required fields'));
        }

        $gathering = $Gatherings->get($gatheringId);

        if ($GatheringWaiverClosures->isGatheringClosed($gatheringId)) {
            return new ServiceResult(false, __('Waiver collection is closed for this gathering.'));
        }

        $waiverType = $WaiverTypes->get($waiverTypeId);

        $validReasons = $waiverType->exemption_reasons_parsed ?? [];
        if (!in_array($reason, $validReasons)) {
            return new ServiceResult(false, __('Invalid exemption reason'));
        }

        $existing = $GatheringWaivers->find()
            ->where([
                'GatheringWaivers.waiver_type_id' => $waiverTypeId,
                'GatheringWaivers.gathering_id' => $gatheringId,
                'GatheringWaivers.is_exemption' => true,
                'GatheringWaivers.status !=' => 'declined',
            ])
            ->first();

        if ($existing) {
            if ($existing->exemption_reason === $reason) {
                return new ServiceResult(false, __('An exemption with this reason already exists for this gathering and waiver type'));
            }

            return new ServiceResult(false, __('An exemption already exists for this gathering and waiver type. Please delete the existing exemption before creating a new one with a different reason.'));
        }

        $retentionPolicyService = new RetentionPolicyService();
        $gatheringEndDate = Date::parse($gathering->end_date->format('Y-m-d'));
        $retentionResult = $retentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gatheringEndDate,
            Date::now(),
        );

        if (!$retentionResult->success) {
            return new ServiceResult(false, __('Failed to calculate retention date: {0}', $retentionResult->reason));
        }

        $retentionDate = $retentionResult->getData();

        $exemption = $GatheringWaivers->newEntity([
            'gathering_id' => $gatheringId,
            'waiver_type_id' => $waiverTypeId,
            'document_id' => null,
            'is_exemption' => true,
            'exemption_reason' => $reason,
            'notes' => $notes,
            'status' => 'active',
            'retention_date' => $retentionDate,
        ]);

        if ($GatheringWaivers->save($exemption)) {
            $redirectUrl = null;

            if ($referer && strpos($referer, 'mobile-upload') !== false) {
                $redirectUrl = Router::url([
                    'controller' => 'Members',
                    'action' => 'viewMobileCard',
                    'plugin' => null,
                ], true);
            } else {
                $redirectUrl = Router::url([
                    'plugin' => false,
                    'controller' => 'Gatherings',
                    'action' => 'view',
                    $gathering->public_id,
                    '?' => ['tab' => 'gathering-waivers'],
                ], true);
            }

            return new ServiceResult(true, __('Exemption recorded successfully'), [
                'redirectUrl' => $redirectUrl,
            ]);
        }

        $errors = $exemption->getErrors();
        $errorMessages = [];
        foreach ($errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $errorMessages[] = $error;
            }
        }

        return new ServiceResult(false, __('Failed to save exemption: {0}', implode(', ', $errorMessages)));
    }
}
