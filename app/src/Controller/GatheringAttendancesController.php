<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\I18n\Date;

/**
 * GatheringAttendances Controller
 *
 * @property \App\Model\Table\GatheringAttendancesTable $GatheringAttendances
 * @method \App\Model\Entity\GatheringAttendance[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class GatheringAttendancesController extends AppController
{
    /**
     * Add method - Create new gathering attendance
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->request->allowMethod(['post']);
        $gatheringAttendance = $this->GatheringAttendances->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Validate that the gathering exists and is valid for attendance
            $gathering = $this->GatheringAttendances->Gatherings->get($data['gathering_id']);
            $today = Date::now();

            // Check if gathering has already ended
            if ($gathering->end_date < $today) {
                $this->Flash->error(__('Cannot register for a gathering that has already ended.'));
                return $this->redirect($this->referer());
            }

            // Set created_by from current user
            $currentUser = $this->Authentication->getIdentity();
            $data['created_by'] = $currentUser->id;

            $gatheringAttendance = $this->GatheringAttendances->patchEntity($gatheringAttendance, $data);

            // Authorize after patching data so policy can check member_id
            $this->Authorization->authorize($gatheringAttendance);

            if ($this->GatheringAttendances->save($gatheringAttendance)) {
                $this->Flash->success(__('Your attendance has been registered.'));
            } else {
                $errors = $gatheringAttendance->getErrors();
                if (!empty($errors)) {
                    $errorMessages = [];
                    foreach ($errors as $field => $fieldErrors) {
                        foreach ($fieldErrors as $error) {
                            $errorMessages[] = "$field: $error";
                        }
                    }
                    $this->Flash->error(__('Validation errors: {0}', implode(', ', $errorMessages)));
                } else {
                    $this->Flash->error(__('Unable to register your attendance. Please try again.'));
                }
            }
        }

        return $this->redirect($this->referer());
    }

    /**
     * Edit method
     *
     * @param string|null $id Gathering Attendance id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);

        $gatheringAttendance = $this->GatheringAttendances->get($id, contain: ['Gatherings']);

        $this->Authorization->authorize($gatheringAttendance);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();

            // Validate that the gathering hasn't ended
            $today = Date::now();
            if ($gatheringAttendance->gathering->end_date < $today) {
                $this->Flash->error(__('Cannot update attendance for a gathering that has already ended.'));
                return $this->redirect($this->referer());
            }

            // Set modified_by from current user
            $currentUser = $this->Authentication->getIdentity();
            $data['modified_by'] = $currentUser->id;

            $gatheringAttendance = $this->GatheringAttendances->patchEntity($gatheringAttendance, $data);

            if ($this->GatheringAttendances->save($gatheringAttendance)) {
                $this->Flash->success(__('Your attendance has been updated.'));
            } else {
                $errors = $gatheringAttendance->getErrors();
                if (!empty($errors)) {
                    $errorMessages = [];
                    foreach ($errors as $field => $fieldErrors) {
                        foreach ($fieldErrors as $error) {
                            $errorMessages[] = "$field: $error";
                        }
                    }
                    $this->Flash->error(__('Validation errors: {0}', implode(', ', $errorMessages)));
                } else {
                    $this->Flash->error(__('Unable to update your attendance. Please try again.'));
                }
            }
        }

        return $this->redirect($this->referer());
    }

    /**
     * Delete method
     *
     * @param string|null $id Gathering Attendance id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $gatheringAttendance = $this->GatheringAttendances->get($id);

        $this->Authorization->authorize($gatheringAttendance);

        if ($this->GatheringAttendances->delete($gatheringAttendance)) {
            $this->Flash->success(__('Your attendance has been removed.'));
            \Cake\Log\Log::debug('Successfully deleted attendance ID: ' . $id);
        } else {
            $errors = $gatheringAttendance->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = "$field: $error";
                    }
                }
                $this->Flash->error(__('Delete errors: {0}', implode(', ', $errorMessages)));
                \Cake\Log\Log::error('Failed to delete attendance: ' . implode(', ', $errorMessages));
            } else {
                $this->Flash->error(__('Unable to remove your attendance. Please try again.'));
                \Cake\Log\Log::error('Failed to delete attendance ID: ' . $id . ' - no error details');
            }
        }

        return $this->redirect($this->referer());
    }
}
