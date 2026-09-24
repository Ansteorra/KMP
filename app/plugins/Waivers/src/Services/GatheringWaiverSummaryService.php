<?php
declare(strict_types=1);

namespace Waivers\Services;

use Cake\ORM\Locator\LocatorAwareTrait;

/** Separate uploaded documents, exemptions and still-unfulfilled waiver requirements. */
class GatheringWaiverSummaryService
{
    use LocatorAwareTrait;

    /** Count valid uploads and identify requirements still awaiting a submission. */
    public function summarize(int $gatheringId, array $requiredWaiverTypes): array
    {
        $countsMap = [];
        $exemptionCounts = [];
        $waivers = $this->fetchTable('Waivers.GatheringWaivers')->find()
            ->select(['waiver_type_id', 'is_exemption'])
            ->where(['gathering_id' => $gatheringId, 'declined_at IS' => null]);
        foreach ($waivers as $waiver) {
            $id = $waiver->waiver_type_id;
            if ($waiver->is_exemption) {
                $exemptionCounts[$id] = ($exemptionCounts[$id] ?? 0) + 1;
            } else {
                $countsMap[$id] = ($countsMap[$id] ?? 0) + 1;
            }
        }
        $pendingWaiverTypes = [];
        foreach ($requiredWaiverTypes as $requirement) {
            $id = $requirement->waiver_type_id;
            if (empty($countsMap[$id]) && empty($exemptionCounts[$id])) {
                $pendingWaiverTypes[] = $requirement->waiver_type;
            }
        }

        return compact('countsMap', 'exemptionCounts', 'pendingWaiverTypes');
    }
}
