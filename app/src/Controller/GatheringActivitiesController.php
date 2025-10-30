<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * GatheringActivities Controller
 *
 * @property \App\Model\Table\GatheringActivitiesTable $GatheringActivities
 * @method \App\Model\Entity\GatheringActivity[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class GatheringActivitiesController extends AppController
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
        $query = $this->GatheringActivities->find()
            ->order(['GatheringActivities.name' => 'ASC']);

        $gatheringActivities = $this->paginate($query);

        $this->set(compact('gatheringActivities'));
    }

    /**
     * View method
     *
     * @param string|null $id Gathering Activity id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $gatheringActivity = $this->GatheringActivities->get($id, contain: [
            'Gatherings'
        ]);

        $this->Authorization->authorize($gatheringActivity);

        $this->set(compact('gatheringActivity'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $gatheringActivity = $this->GatheringActivities->newEmptyEntity();
        $this->Authorization->authorize($gatheringActivity);

        if ($this->request->is('post')) {
            $gatheringActivity = $this->GatheringActivities->patchEntity($gatheringActivity, $this->request->getData());

            if ($this->GatheringActivities->save($gatheringActivity)) {
                $this->Flash->success(__(
                    'The activity "{0}" has been created successfully.',
                    $gatheringActivity->name
                ));

                return $this->redirect(['action' => 'index']);
            }

            $errors = $gatheringActivity->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering activity could not be saved: {0}',
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__('The gathering activity could not be saved. Please, try again.'));
            }
        }

        $this->set(compact('gatheringActivity'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Gathering Activity id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $gatheringActivity = $this->GatheringActivities->get($id);
        $this->Authorization->authorize($gatheringActivity);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $gatheringActivity = $this->GatheringActivities->patchEntity($gatheringActivity, $this->request->getData());

            if ($this->GatheringActivities->save($gatheringActivity)) {
                $this->Flash->success(__(
                    'The activity "{0}" has been updated successfully.',
                    $gatheringActivity->name
                ));

                return $this->redirect(['action' => 'view', $id]);
            }

            $errors = $gatheringActivity->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering activity could not be updated: {0}',
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__('The gathering activity could not be saved. Please, try again.'));
            }
        }

        $this->set(compact('gatheringActivity'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Gathering Activity id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $gatheringActivity = $this->GatheringActivities->get($id);
        $this->Authorization->authorize($gatheringActivity);

        $activityName = $gatheringActivity->name;

        // Check if activity is used by any gatherings
        $gatheringCount = $this->GatheringActivities->Gatherings->find()
            ->matching('GatheringActivities', function ($q) use ($id) {
                return $q->where(['GatheringActivities.id' => $id]);
            })
            ->count();

        if ($gatheringCount > 0) {
            $this->Flash->error(__(
                'Cannot delete activity "{0}" because it is used by {1} gathering(s). Please remove this activity from those gatherings first.',
                $activityName,
                $gatheringCount
            ));

            return $this->redirect(['action' => 'index']);
        }

        // Check if activity has waiver requirements
        $waiverCount = $this->fetchTable('Waivers.GatheringActivityWaivers')->find()
            ->where(['gathering_activity_id' => $id])
            ->count();

        if ($waiverCount > 0) {
            $this->Flash->error(__(
                'Cannot delete activity "{0}" because it has {1} waiver requirement(s) associated with it. Please remove the waiver requirements first.',
                $activityName,
                $waiverCount
            ));

            return $this->redirect(['action' => 'index']);
        }

        if ($this->GatheringActivities->delete($gatheringActivity)) {
            $this->Flash->success(__(
                'The activity "{0}" has been deleted successfully.',
                $activityName
            ));
        } else {
            $errors = $gatheringActivity->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The activity "{0}" could not be deleted: {1}',
                    $activityName,
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__(
                    'The activity "{0}" could not be deleted. Please, try again.',
                    $activityName
                ));
            }
        }

        return $this->redirect(['action' => 'index']);
    }
}
