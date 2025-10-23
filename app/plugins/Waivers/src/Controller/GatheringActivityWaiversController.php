<?php

declare(strict_types=1);

namespace Waivers\Controller;

use App\Controller\AppController;

/**
 * GatheringActivityWaivers Controller
 *
 * Manages waiver requirements for gathering activities, allowing administrators
 * to specify which waiver types are required for participants in specific activities.
 *
 * @property \Waivers\Model\Table\GatheringActivityWaiversTable $GatheringActivityWaivers
 */
class GatheringActivityWaiversController extends AppController
{
    /**
     * Add waiver requirement to a gathering activity
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add
     */
    public function add()
    {
        $gatheringActivityWaiver = $this->GatheringActivityWaivers->newEmptyEntity();
        $this->Authorization->authorize($gatheringActivityWaiver);

        if ($this->request->is('post')) {
            $gatheringActivityWaiver = $this->GatheringActivityWaivers->patchEntity(
                $gatheringActivityWaiver,
                $this->request->getData()
            );

            if ($this->GatheringActivityWaivers->save($gatheringActivityWaiver)) {
                $this->Flash->success(__('The waiver requirement has been added.'));

                // Redirect back to gathering activity view
                if ($this->request->getData('gathering_activity_id')) {
                    return $this->redirect([
                        'controller' => 'GatheringActivities',
                        'action' => 'view',
                        'plugin' => null,
                        $this->request->getData('gathering_activity_id')
                    ]);
                }

                return $this->redirect(['action' => 'index']);
            }

            $this->Flash->error(__('The waiver requirement could not be added. Please try again.'));
        }

        // Get available waiver types
        $waiverTypes = $this->GatheringActivityWaivers->WaiverTypes->find('list')
            ->where(['WaiverTypes.deleted IS' => null, 'WaiverTypes.is_active' => true])
            ->order(['WaiverTypes.name' => 'ASC']);

        $this->set(compact('gatheringActivityWaiver', 'waiverTypes'));
    }

    /**
     * Delete waiver requirement from a gathering activity
     *
     * @param string|null $gatheringActivityId Gathering Activity ID
     * @param string|null $waiverTypeId Waiver Type ID
     * @return \Cake\Http\Response|null Redirects to referring page
     */
    public function delete($gatheringActivityId = null, $waiverTypeId = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $gatheringActivityWaiver = $this->GatheringActivityWaivers->find()
            ->where([
                'gathering_activity_id' => $gatheringActivityId,
                'waiver_type_id' => $waiverTypeId
            ])
            ->firstOrFail();

        $this->Authorization->authorize($gatheringActivityWaiver);

        if ($this->GatheringActivityWaivers->delete($gatheringActivityWaiver)) {
            $this->Flash->success(__('The waiver requirement has been removed.'));
        } else {
            $this->Flash->error(__('The waiver requirement could not be removed. Please try again.'));
        }

        // Redirect back to gathering activity view
        return $this->redirect([
            'controller' => 'GatheringActivities',
            'action' => 'view',
            'plugin' => null,
            $gatheringActivityId
        ]);
    }

    /**
     * Get available waiver types for a gathering activity (AJAX endpoint)
     *
     * Returns list of waiver types not already required for the activity
     *
     * @param string|null $gatheringActivityId Gathering Activity ID
     * @return \Cake\Http\Response|null JSON response with available waiver types
     */
    public function availableWaiverTypes($gatheringActivityId = null)
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->authorize($this->GatheringActivityWaivers);

        // Get waiver types already assigned to this activity
        $assignedWaiverTypeIds = $this->GatheringActivityWaivers->find()
            ->where(['gathering_activity_id' => $gatheringActivityId])
            ->all()
            ->extract('waiver_type_id')
            ->toArray();

        // Get available waiver types (active, not deleted, not already assigned)
        $conditions = [
            'WaiverTypes.deleted IS' => null,
            'WaiverTypes.is_active' => true
        ];

        if (!empty($assignedWaiverTypeIds)) {
            $conditions['WaiverTypes.id NOT IN'] = $assignedWaiverTypeIds;
        }

        $waiverTypes = $this->GatheringActivityWaivers->WaiverTypes->find()
            ->where($conditions)
            ->order(['WaiverTypes.name' => 'ASC'])
            ->all();

        // Format for JSON response (only return id and name for dropdown)
        $waiverTypesArray = [];
        foreach ($waiverTypes as $waiverType) {
            $waiverTypesArray[] = [
                'id' => $waiverType->id,
                'name' => $waiverType->name
            ];
        }

        // Return JSON response
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['waiverTypes' => $waiverTypesArray]));
    }
}
