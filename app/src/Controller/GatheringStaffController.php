<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

/**
 * GatheringStaff Controller
 *
 * Manages staff assignments for gatherings including stewards and other roles.
 *
 * @property \App\Model\Table\GatheringStaffTable $GatheringStaff
 * @method \App\Model\Entity\GatheringStaff[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class GatheringStaffController extends AppController
{
    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // SECURITY: Skip authorization for getMemberContactInfo since it has its own checks
        $this->Authorization->skipAuthorization(['getMemberContactInfo']);
    }

    /**
     * Add method - Add a staff member to a gathering
     *
     * @param int|null $gatheringId Gathering ID
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add(?int $gatheringId = null)
    {
        if (!$gatheringId) {
            throw new NotFoundException(__('Gathering not specified'));
        }

        // Load the gathering to verify it exists and for authorization
        $gathering = $this->GatheringStaff->Gatherings->get($gatheringId);
        $this->Authorization->authorize($gathering, 'edit');

        $staff = $this->GatheringStaff->newEmptyEntity();
        $staff->gathering_id = $gatheringId;

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Convert member_public_id to member_id if provided
            if (!empty($data['member_public_id'])) {
                $member = $this->GatheringStaff->Members->find('byPublicId', [$data['member_public_id']])->first();
                if ($member) {
                    $data['member_id'] = $member->id;
                }
                unset($data['member_public_id']);
            }

            // Handle autocomplete data - convert member_sca_name to sca_name if no member_id
            if (empty($data['member_id']) && !empty($data['member_sca_name'])) {
                $data['sca_name'] = $data['member_sca_name'];
                unset($data['member_sca_name']);
            }

            // Ensure gathering_id is set (it's in the URL, not the form)
            $data['gathering_id'] = $gatheringId;

            // BUSINESS RULE: Stewards must always be visible on the public page
            if (!empty($data['is_steward'])) {
                $data['show_on_public_page'] = true;
            }

            $staff = $this->GatheringStaff->patchEntity($staff, $data);

            // Set sort order - stewards first, then others
            if ($staff->is_steward) {
                // Get max steward sort order
                $maxOrder = $this->GatheringStaff->find()
                    ->where([
                        'gathering_id' => $gatheringId,
                        'is_steward' => true
                    ])
                    ->select(['max_order' => 'MAX(sort_order)'])
                    ->first();
                $staff->sort_order = ($maxOrder && isset($maxOrder->max_order)) ? $maxOrder->max_order + 1 : 0;
            } else {
                // Get max non-steward sort order
                $maxOrder = $this->GatheringStaff->find()
                    ->where([
                        'gathering_id' => $gatheringId,
                        'is_steward' => false
                    ])
                    ->select(['max_order' => 'MAX(sort_order)'])
                    ->first();
                $staff->sort_order = ($maxOrder && isset($maxOrder->max_order)) ? $maxOrder->max_order + 1 : 100;
            }

            if ($this->GatheringStaff->save($staff)) {
                $this->Flash->success(__('The staff member has been added.'));

                return $this->redirect(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]);
            }

            // If save failed, redirect back with error
            $this->Flash->error(__('The staff member could not be added. Please check the form and try again.'));

            // Add validation errors to flash if any
            if ($staff->hasErrors()) {
                $errors = [];
                foreach ($staff->getErrors() as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errors[] = "$field: $error";
                    }
                }
                if (!empty($errors)) {
                    $this->Flash->error(__('Validation errors: {0}', implode(', ', $errors)));
                }
            }

            return $this->redirect(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]);
        }

        // This shouldn't be reached in normal flow since we're using modals
        // But if someone accesses this directly, redirect to gathering view
        $this->Flash->warning(__('Please use the Add Staff button on the gathering page.'));
        return $this->redirect(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]);
    }

    /**
     * Edit method - Edit a staff member
     *
     * @param int|null $id Staff ID.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function edit(?int $id = null)
    {
        $staff = $this->GatheringStaff->get($id, [
            'contain' => ['Gatherings', 'Members']
        ]);

        // Authorize based on the gathering
        $this->Authorization->authorize($staff->gathering, 'edit');

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();

            // BUSINESS RULE: Stewards must always be visible on the public page
            if (!empty($data['is_steward'])) {
                $data['show_on_public_page'] = true;
            }

            $staff = $this->GatheringStaff->patchEntity($staff, $data);
            if ($this->GatheringStaff->save($staff)) {
                $this->Flash->success(__('The staff member has been updated.'));

                return $this->redirect(['controller' => 'Gatherings', 'action' => 'view', $staff->gathering->public_id]);
            }

            // If save failed, redirect back with error
            $this->Flash->error(__('The staff member could not be updated. Please check the form and try again.'));

            // Add validation errors to flash if any
            if ($staff->hasErrors()) {
                $errors = [];
                foreach ($staff->getErrors() as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errors[] = "$field: $error";
                    }
                }
                if (!empty($errors)) {
                    $this->Flash->error(__('Validation errors: {0}', implode(', ', $errors)));
                }
            }

            return $this->redirect(['controller' => 'Gatherings', 'action' => 'view', $staff->gathering->public_id]);
        }

        // This shouldn't be reached in normal flow since we're using modals
        // But if someone accesses this directly, redirect to gathering view
        $this->Flash->warning(__('Please use the Edit button on the gathering page.'));
        return $this->redirect(['controller' => 'Gatherings', 'action' => 'view', $staff->gathering->public_id]);
    }

    /**
     * Delete method - Remove a staff member from a gathering
     *
     * @param int|null $id Staff ID.
     * @return \Cake\Http\Response|null Redirects to gathering view.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function delete(?int $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $staff = $this->GatheringStaff->get($id, [
            'contain' => ['Gatherings']
        ]);

        // Authorize based on the gathering
        $this->Authorization->authorize($staff->gathering, 'edit');

        $gatheringPublicId = $staff->gathering->public_id;

        if ($this->GatheringStaff->delete($staff)) {
            $this->Flash->success(__('The staff member has been removed.'));
        } else {
            $this->Flash->error(__('The staff member could not be removed. Please, try again.'));
        }

        return $this->redirect(['controller' => 'Gatherings', 'action' => 'view', $gatheringPublicId]);
    }

    /**
     * AJAX method to get member contact info using public IDs
     * 
     * SECURITY: Public ID-based access control:
     * 1. Requires gathering_public_id that user has edit permission on
     * 2. Uses non-sequential public IDs instead of internal database IDs
     * 3. No enumeration possible without valid gathering access
     *
     * @return \Cake\Http\Response|null JSON response
     */
    public function getMemberContactInfo()
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Json');

        $memberPublicId = $this->request->getQuery('member_public_id');
        $gatheringPublicId = $this->request->getQuery('gathering_public_id');

        if (!$memberPublicId || !$gatheringPublicId) {
            $this->set('data', ['error' => 'Member public ID and Gathering public ID required']);
            $this->viewBuilder()->setOption('serialize', 'data');
            return;
        }

        try {
            // SECURITY CHECK: Verify user can edit this gathering
            // This prevents users from harvesting PII by trying different member public IDs
            $gathering = $this->GatheringStaff->Gatherings->find('byPublicId', [$gatheringPublicId])->firstOrFail();
            $this->Authorization->authorize($gathering, 'edit');

            // Only after authorization passes, fetch member info
            $member = $this->GatheringStaff->Members->find('byPublicId', [$memberPublicId])
                ->select(['id', 'public_id', 'sca_name', 'email_address', 'phone_number'])
                ->firstOrFail();

            $this->set('data', [
                'email' => $member->email_address,
                'phone' => $member->phone_number,
            ]);
        } catch (\Cake\Http\Exception\ForbiddenException $e) {
            $this->set('data', ['error' => 'Not authorized to access this information']);
        } catch (\Exception $e) {
            $this->set('data', ['error' => 'Member or gathering not found']);
        }

        $this->viewBuilder()->setOption('serialize', 'data');
    }
}
