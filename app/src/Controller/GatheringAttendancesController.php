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
     * Initialize controller
     */
    public function initialize(): void
    {
        parent::initialize();
        
        // Authorize table-level actions
        $this->Authorization->authorizeModel('myRsvps', 'mobileRsvp', 'mobileUnrsvp');
    }

    /**
     * Check if request wants JSON response
     */
    private function wantsJson(): bool
    {
        return $this->request->is('ajax') || 
               $this->request->accepts('application/json') ||
               $this->request->getHeaderLine('Accept') === 'application/json';
    }

    /**
     * Return JSON response
     */
    private function jsonResponse(array $data, int $status = 200): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody(json_encode($data));
    }

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
            $data['is_public'] = '0';

            // Validate that the gathering exists and is valid for attendance
            try {
                $gathering = $this->GatheringAttendances->Gatherings->get($data['gathering_id']);
            } catch (\Exception $e) {
                if ($this->wantsJson()) {
                    return $this->jsonResponse(['success' => false, 'error' => 'Gathering not found'], 404);
                }
                $this->Flash->error(__('Gathering not found.'));
                return $this->redirect($this->referer());
            }
            
            $today = Date::now();

            // Check if gathering has already ended
            if ($gathering->end_date < $today) {
                if ($this->wantsJson()) {
                    return $this->jsonResponse(['success' => false, 'error' => 'Cannot register for a gathering that has already ended'], 400);
                }
                $this->Flash->error(__('Cannot register for a gathering that has already ended.'));
                return $this->redirect($this->referer());
            }

            // Set created_by from current user
            $currentUser = $this->Authentication->getIdentity();
            $data['created_by'] = $currentUser->id;

            $gatheringAttendance = $this->GatheringAttendances->patchEntity($gatheringAttendance, $data);

            if (!$gatheringAttendance->member_id) {
                // If member_id is not set, default to current user's member_id
                $gatheringAttendance->member_id = $currentUser->id;
            }

            // Authorize after patching data so policy can check member_id
            $this->Authorization->authorize($gatheringAttendance);

            if ($this->GatheringAttendances->save($gatheringAttendance)) {
                if ($this->wantsJson()) {
                    return $this->jsonResponse([
                        'success' => true,
                        'message' => 'Your attendance has been registered.',
                        'data' => [
                            'id' => $gatheringAttendance->id,
                            'gathering_id' => $gatheringAttendance->gathering_id,
                            'member_id' => $gatheringAttendance->member_id,
                        ]
                    ]);
                }
                $this->Flash->success(__('Your attendance has been registered.'));
            } else {
                $errors = $gatheringAttendance->getErrors();
                $errorMessage = 'Unable to register your attendance. Please try again.';
                if (!empty($errors)) {
                    $errorMessages = [];
                    foreach ($errors as $field => $fieldErrors) {
                        foreach ($fieldErrors as $error) {
                            $errorMessages[] = "$field: $error";
                        }
                    }
                    $errorMessage = implode(', ', $errorMessages);
                }
                
                if ($this->wantsJson()) {
                    return $this->jsonResponse(['success' => false, 'error' => $errorMessage], 400);
                }
                $this->Flash->error(__($errorMessage));
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
            $data['is_public'] = '0';

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

        try {
            $gatheringAttendance = $this->GatheringAttendances->get($id);
        } catch (\Exception $e) {
            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => false, 'error' => 'Attendance record not found'], 404);
            }
            $this->Flash->error(__('Attendance record not found.'));
            return $this->redirect($this->referer());
        }

        $this->Authorization->authorize($gatheringAttendance);

        if ($this->GatheringAttendances->delete($gatheringAttendance)) {
            if ($this->wantsJson()) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Your attendance has been removed.'
                ]);
            }
            $this->Flash->success(__('Your attendance has been removed.'));
            \Cake\Log\Log::debug('Successfully deleted attendance ID: ' . $id);
        } else {
            $errors = $gatheringAttendance->getErrors();
            $errorMessage = 'Unable to remove your attendance. Please try again.';
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = "$field: $error";
                    }
                }
                $errorMessage = implode(', ', $errorMessages);
                \Cake\Log\Log::error('Failed to delete attendance: ' . $errorMessage);
            } else {
                \Cake\Log\Log::error('Failed to delete attendance ID: ' . $id . ' - no error details');
            }
            
            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => false, 'error' => $errorMessage], 400);
            }
            $this->Flash->error(__($errorMessage));
        }

        return $this->redirect($this->referer());
    }

    /**
     * Mobile RSVP - Quick add attendance from mobile calendar
     *
     * @return \Cake\Http\Response JSON response
     */
    public function mobileRsvp()
    {
        $this->request->allowMethod(['post']);
        
        $data = $this->request->getData();
        
        // Validate gathering_id
        if (empty($data['gathering_id'])) {
            return $this->jsonResponse(['success' => false, 'error' => 'Gathering ID required'], 400);
        }
        
        try {
            $gathering = $this->GatheringAttendances->Gatherings->get($data['gathering_id']);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'error' => 'Gathering not found'], 404);
        }
        
        $today = Date::now();
        if ($gathering->end_date < $today) {
            return $this->jsonResponse(['success' => false, 'error' => 'Cannot RSVP to a past gathering'], 400);
        }
        
        $currentUser = $this->Authentication->getIdentity();
        
        // Check if already attending
        $existing = $this->GatheringAttendances->find()
            ->where([
                'gathering_id' => $data['gathering_id'],
                'member_id' => $currentUser->id,
            ])
            ->first();
        
        if ($existing) {
            return $this->jsonResponse([
                'success' => false, 
                'error' => 'You are already registered for this gathering',
                'attendance_id' => $existing->id
            ], 400);
        }
        
        // Create attendance
        $gatheringAttendance = $this->GatheringAttendances->newEntity([
            'gathering_id' => $data['gathering_id'],
            'member_id' => $currentUser->id,
            'is_public' => '0',
            'share_with_kingdom' => $data['share_with_kingdom'] ?? false,
            'share_with_hosting_group' => $data['share_with_hosting_group'] ?? false,
            'share_with_crown' => $data['share_with_crown'] ?? false,
            'public_note' => $data['public_note'] ?? null,
            'created_by' => $currentUser->id,
        ]);
        
        $this->Authorization->authorize($gatheringAttendance, 'add');
        
        if ($this->GatheringAttendances->save($gatheringAttendance)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'RSVP confirmed!',
                'data' => [
                    'id' => $gatheringAttendance->id,
                    'gathering_id' => $gatheringAttendance->gathering_id,
                ]
            ]);
        }
        
        return $this->jsonResponse(['success' => false, 'error' => 'Failed to save RSVP'], 500);
    }

    /**
     * Mobile un-RSVP - Remove attendance from mobile calendar
     *
     * @param string|null $id Attendance ID
     * @return \Cake\Http\Response JSON response
     */
    public function mobileUnrsvp(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        
        if (!$id) {
            return $this->jsonResponse(['success' => false, 'error' => 'Attendance ID required'], 400);
        }
        
        try {
            $gatheringAttendance = $this->GatheringAttendances->get($id, contain: ['Gatherings']);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'error' => 'Attendance not found'], 404);
        }
        
        $this->Authorization->authorize($gatheringAttendance, 'delete');
        
        if ($this->GatheringAttendances->delete($gatheringAttendance)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'RSVP removed.'
            ]);
        }
        
        return $this->jsonResponse(['success' => false, 'error' => 'Failed to remove RSVP'], 500);
    }

    /**
     * My RSVPs - Get current user's upcoming RSVPs
     *
     * @return \Cake\Http\Response JSON response or view
     */
    public function myRsvps()
    {
        $currentUser = $this->Authentication->getIdentity();
        $today = Date::now();
        
        $attendances = $this->GatheringAttendances->find()
            ->contain([
                'Gatherings' => [
                    'Branches' => ['fields' => ['id', 'name']],
                    'GatheringTypes' => ['fields' => ['id', 'name', 'color']],
                ],
            ])
            ->where([
                'GatheringAttendances.member_id' => $currentUser->id,
                'Gatherings.end_date >=' => $today->format('Y-m-d'),
            ])
            ->order(['Gatherings.start_date' => 'ASC'])
            ->all();
        
        if ($this->wantsJson()) {
            $data = [];
            $userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);
            
            foreach ($attendances as $attendance) {
                $gathering = $attendance->gathering;
                $startLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, $userTimezone);
                $endLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, $userTimezone);
                
                $data[] = [
                    'attendance_id' => $attendance->id,
                    'gathering' => [
                        'id' => $gathering->id,
                        'public_id' => $gathering->public_id,
                        'name' => $gathering->name,
                        'start_date' => $startLocal->format('Y-m-d'),
                        'start_time' => $startLocal->format('H:i'),
                        'end_date' => $endLocal->format('Y-m-d'),
                        'location' => $gathering->location,
                        'is_cancelled' => $gathering->cancelled_at !== null,
                        'branch' => $gathering->branch ? $gathering->branch->name : null,
                        'type' => $gathering->gathering_type ? [
                            'name' => $gathering->gathering_type->name,
                            'color' => $gathering->gathering_type->color,
                        ] : null,
                    ],
                    'sharing' => [
                        'kingdom' => $attendance->share_with_kingdom,
                        'hosting_group' => $attendance->share_with_hosting_group,
                        'crown' => $attendance->share_with_crown,
                    ],
                    'note' => $attendance->public_note,
                ];
            }
            
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        }
        
        // For non-JSON requests, set view variables
        $this->set('attendances', $attendances);
        $this->set('authCardUrl', '/members/view-mobile-card/' . $currentUser->mobile_card_token);
        $this->viewBuilder()->setLayout('mobile_app');
    }
}
