<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * GatheringTypes Controller
 *
 * @property \App\Model\Table\GatheringTypesTable $GatheringTypes
 * @method \App\Model\Entity\GatheringType[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class GatheringTypesController extends AppController
{
    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Authorize model-level operations
        $this->Authorization->authorizeModel('index', 'add');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $gatheringTypes = $this->paginate($this->GatheringTypes);

        $this->set(compact('gatheringTypes'));
    }

    /**
     * View method
     *
     * @param string|null $id Gathering Type id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $gatheringType = $this->GatheringTypes->get($id, contain: [
            'Gatherings' => ['Branches'],
            'GatheringActivities',
        ]);

        $this->Authorization->authorize($gatheringType);

        // Get all available activities for the add activity modal
        $availableActivities = $this->GatheringTypes->GatheringActivities->find('list', order: ['name' => 'ASC'])->all();

        $this->set(compact('gatheringType', 'availableActivities'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $gatheringType = $this->GatheringTypes->newEmptyEntity();
        $this->Authorization->authorize($gatheringType);

        if ($this->request->is('post')) {
            $gatheringType = $this->GatheringTypes->patchEntity($gatheringType, $this->request->getData());
            if ($this->GatheringTypes->save($gatheringType)) {
                $this->Flash->success(__(
                    'The gathering type "{0}" has been created successfully.',
                    $gatheringType->name
                ));

                return $this->redirect(['action' => 'view', $gatheringType->id]);
            }

            $errors = $gatheringType->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering type could not be saved: {0}',
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__('The gathering type could not be saved. Please, try again.'));
            }
        }

        $this->set(compact('gatheringType'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Gathering Type id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $gatheringType = $this->GatheringTypes->get($id);
        $this->Authorization->authorize($gatheringType);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $gatheringType = $this->GatheringTypes->patchEntity($gatheringType, $this->request->getData());
            if ($this->GatheringTypes->save($gatheringType)) {
                $this->Flash->success(__(
                    'The gathering type "{0}" has been updated successfully.',
                    $gatheringType->name
                ));

                return $this->redirect(['action' => 'view', $id]);
            }

            $errors = $gatheringType->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering type could not be saved: {0}',
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__('The gathering type could not be saved. Please, try again.'));
            }
        }

        $this->set(compact('gatheringType'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Gathering Type id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $gatheringType = $this->GatheringTypes->get($id);
        $this->Authorization->authorize($gatheringType);

        // Check if gathering type is in use
        $gatheringCount = $this->GatheringTypes->Gatherings->find()
            ->where(['gathering_type_id' => $id])
            ->count();

        if ($gatheringCount > 0) {
            $this->Flash->error(__(
                'Cannot delete gathering type "{0}" because it is used by {1} gathering(s). Please reassign or delete those gatherings first.',
                $gatheringType->name,
                $gatheringCount
            ));

            return $this->redirect(['action' => 'index']);
        }

        if ($this->GatheringTypes->delete($gatheringType)) {
            $this->Flash->success(__(
                'The gathering type "{0}" has been deleted successfully.',
                $gatheringType->name
            ));
        } else {
            $this->Flash->error(__(
                'The gathering type "{0}" could not be deleted. Please, try again.',
                $gatheringType->name
            ));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Add Activity method
     *
     * Adds a template activity to a gathering type.
     *
     * @param string|null $id Gathering Type id.
     * @return \Cake\Http\Response|null Redirects to view.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function addActivity($id = null)
    {
        $this->request->allowMethod(['post']);
        $gatheringType = $this->GatheringTypes->get($id, contain: ['GatheringActivities']);
        $this->Authorization->authorize($gatheringType, "edit");

        $activityId = $this->request->getData('activity_id');
        $notRemovable = (bool)$this->request->getData('not_removable', false);

        if (empty($activityId)) {
            $this->Flash->error(__('Please select an activity to add.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Get existing activity IDs
        $existingIds = array_column($gatheringType->gathering_activities, 'id');

        // Check if activity is already linked
        if (in_array($activityId, $existingIds)) {
            $this->Flash->warning(__('This activity is already part of this gathering type.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Link the new activity
        $GatheringTypeGatheringActivities = $this->fetchTable('GatheringTypeGatheringActivities');

        $linkData = [
            'gathering_type_id' => $id,
            'gathering_activity_id' => $activityId,
            'not_removable' => $notRemovable,
        ];

        $link = $GatheringTypeGatheringActivities->newEntity($linkData);

        if ($GatheringTypeGatheringActivities->save($link)) {
            $this->Flash->success(__('Template activity added successfully.'));
        } else {
            $this->Flash->error(__('Unable to add template activity. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Remove Activity method
     *
     * Removes a template activity from a gathering type.
     *
     * @param string|null $gatheringTypeId Gathering Type id.
     * @param string|null $activityId Activity id.
     * @return \Cake\Http\Response|null Redirects to view.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function removeActivity($gatheringTypeId = null, $activityId = null)
    {
        $this->request->allowMethod(['post']);
        $gatheringType = $this->GatheringTypes->get($gatheringTypeId);
        $this->Authorization->authorize($gatheringType, "edit");

        $GatheringTypeGatheringActivities = $this->fetchTable('GatheringTypeGatheringActivities');
        $link = $GatheringTypeGatheringActivities->find()
            ->where([
                'gathering_type_id' => $gatheringTypeId,
                'gathering_activity_id' => $activityId
            ])
            ->first();

        if (!$link) {
            $this->Flash->error(__('Template activity link not found.'));
            return $this->redirect(['action' => 'view', $gatheringTypeId]);
        }

        // Check if the activity is marked as not removable
        if ($link->not_removable) {
            $this->Flash->error(__('This template activity cannot be removed.'));
            return $this->redirect(['action' => 'view', $gatheringTypeId]);
        }

        if ($GatheringTypeGatheringActivities->delete($link)) {
            $this->Flash->success(__('Template activity removed successfully.'));
        } else {
            $this->Flash->error(__('Unable to remove template activity. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $gatheringTypeId]);
    }
}
