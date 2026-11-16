<?php
declare(strict_types=1);

namespace Waivers\Services;

use Cake\ORM\Locator\LocatorAwareTrait;

/** Separate uploaded documents, exemptions and still-unfulfilled waiver requirements. */
class GatheringWaiverSummaryService
{
    use LocatorAwareTrait;

    /** Return active lost waiver type IDs per gathering without counting them as uploads. */
    public function getLostTypeIdsByGathering(array $gatheringIds): array
    {
        if ($gatheringIds === []) {
            return [];
        }

        $result = [];
        $losses = $this->fetchTable('Waivers.GatheringWaiverLosses')->find()
            ->select(['gathering_id', 'waiver_type_id'])
            ->where(['gathering_id IN' => $gatheringIds, 'reversed_at IS' => null]);
        foreach ($losses as $loss) {
            $result[(int)$loss->gathering_id][] = (int)$loss->waiver_type_id;
        }

        return $result;
    }

    /** Count valid submissions and identify types needing a synthetic summary row. */
    public function summarize(int $gatheringId, array $requiredWaiverTypes): array
    {
        $countsMap = [];
        $exemptionCounts = [];
        $submittedTypeIds = [];
        $waivers = $this->fetchTable('Waivers.GatheringWaivers')->find()
            ->select(['waiver_type_id', 'is_exemption', 'declined_at'])
            ->where(['gathering_id' => $gatheringId]);
        foreach ($waivers as $waiver) {
            $id = $waiver->waiver_type_id;
            $submittedTypeIds[$id] = true;
            if ($waiver->declined_at !== null) {
                continue;
            }
            if ($waiver->is_exemption) {
                $exemptionCounts[$id] = ($exemptionCounts[$id] ?? 0) + 1;
            } else {
                $countsMap[$id] = ($countsMap[$id] ?? 0) + 1;
            }
        }
        $pendingWaiverTypes = [];
        foreach ($requiredWaiverTypes as $requirement) {
            $id = $requirement->waiver_type_id;
            if (!isset($submittedTypeIds[$id])) {
                $pendingWaiverTypes[] = $requirement->waiver_type;
            }
        }

        return compact('countsMap', 'exemptionCounts', 'pendingWaiverTypes');
    }
}
