<?php
declare(strict_types=1);

namespace Awards\View\Cell;

use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalFormService;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\View\Cell;
use Exception;

/**
 * Gathering Bestowals View Cell
 *
 * Displays award bestowals associated with a gathering in the gathering detail view.
 */
class GatheringBestowalsCell extends Cell
{
    /**
     * Display gathering bestowals grid for a gathering detail tab.
     *
     * @param int $gatheringId Gathering integer ID
     * @param string|null $model Optional model name (unused)
     * @return void
     */
    public function display(int $gatheringId, ?string $model = null): void
    {
        $currentUser = $this->request->getAttribute('identity');

        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        try {
            $gathering = $gatheringsTable->get($gatheringId);
        } catch (Exception $e) {
            Log::error('GatheringBestowalsCell: Gathering not found: ' . $e->getMessage());

            return;
        }

        $canView = $currentUser->can('ViewGatheringBestowals', 'Awards.Bestowals', $gathering);
        if (!$canView) {
            return;
        }

        $bestowalsTable = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $isEmpty = $bestowalsTable->find()
            ->where(['gathering_id' => $gathering->id])
            ->count() === 0;

        $emptyBestowal = $bestowalsTable->newEmptyEntity();
        $emptyBestowal->gathering_id = $gathering->id;
        $emptyBestowal->gathering = $gathering;
        $canManage = $currentUser->can('edit', $emptyBestowal);

        $statusList = Bestowal::getStatuses();
        foreach ($statusList as $key => $value) {
            $states = $value;
            $statusList[$key] = [];
            foreach ($states as $state) {
                $statusList[$key][$state] = $state;
            }
        }

        $rules = Bestowal::getStateRules();
        $gatheringList = [];
        $adHocFormData = (new BestowalFormService())->prepareAdHocFormData($currentUser);

        $this->set('gatheringId', $gathering->id);
        $this->set('isEmpty', $isEmpty);
        $this->set(compact('rules', 'statusList', 'gatheringList', 'canManage', 'adHocFormData'));
    }
}
