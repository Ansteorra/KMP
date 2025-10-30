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
        ]);

        $this->Authorization->authorize($gatheringType);

        $this->set(compact('gatheringType'));
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

                return $this->redirect(['action' => 'index']);
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
}
