<?php
declare(strict_types=1);

namespace Awards\Controller;

use Awards\Model\Entity\BestowalDelegation;
use Awards\Services\BestowalDelegationService;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

/** Gathering-specific grant and revoke UI; never changes a recipient's roles. */
class BestowalDelegationsController extends AppController
{
    /** Show a member's event delegation history and native granting choices. */
    public function gathering(?string $gatheringId = null): ?Response
    {
        $tables = TableRegistry::getTableLocator();
        $gathering = $tables->get('Gatherings')->get($gatheringId);
        $context = new BestowalDelegation(['gathering_id' => $gathering->id]);
        $this->Authorization->authorize($context, 'gathering');
        $user = $this->request->getAttribute('identity');
        $branchOptions = [];
        $branches = $tables->get('Branches')->find()->where([
            'Branches.id IN' => $tables->get('Awards.Awards')->find()->select(['branch_id'])
                ->where(['branch_id IS NOT' => null]),
        ])->orderBy(['Branches.name' => 'ASC'])->all();
        foreach ($branches as $branch) {
            $probe = new BestowalDelegation(['gathering_id' => $gathering->id, 'branch_id' => $branch->id]);
            if ($user->checkCan('grant', $probe)) {
                $branchOptions[(int)$branch->id] = $branch->name;
            }
        }
        $query = $tables->get('Awards.BestowalDelegations')->find()
            ->where(['BestowalDelegations.gathering_id' => $gathering->id])
            ->contain(['Grantors', 'Delegates', 'Branches', 'Revokers'])
            ->orderBy(['BestowalDelegations.created' => 'DESC', 'BestowalDelegations.id' => 'DESC']);
        if (!$user->isSuperUser()) {
            $query->where(['OR' => [
                'BestowalDelegations.grantor_id' => (int)$user->getIdentifier(),
                'BestowalDelegations.delegate_id' => (int)$user->getIdentifier(),
            ]]);
        }
        $delegations = $query->all();
        $this->set(compact('gathering', 'branchOptions', 'delegations'));
        $this->viewBuilder()->disableAutoLayout();

        return null;
    }

    /** Grant only the authenticated member's own event award authority. */
    public function grant(?string $gatheringId = null): ?Response
    {
        $this->request->allowMethod(['post']);
        $tables = TableRegistry::getTableLocator();
        $gathering = $tables->get('Gatherings')->get($gatheringId);
        $grant = new BestowalDelegation([
            'gathering_id' => $gathering->id,
            'branch_id' => (int)$this->request->getData('branch_id'),
        ]);
        $this->Authorization->authorize($grant, 'grant');
        $recipient = $tables->get('Members')->find()->where([
            'Members.public_id' => (string)$this->request->getData('delegate_public_id'),
            'Members.status' => 'verified',
        ])->first();
        $user = $this->request->getAttribute('identity');
        if ($recipient === null || (int)$recipient->id === (int)$user->getIdentifier()) {
            $this->Flash->error(__('Choose another verified member from the member search.'));
        } else {
            (new BestowalDelegationService())->grant(
                $user,
                (int)$gathering->id,
                (int)$grant->branch_id,
                (int)$recipient->id,
            );
            $this->Flash->success(__('Event award permissions delegated. Access remains active until revoked.'));
        }

        return $this->redirect(['action' => 'gathering', $gathering->id]);
    }

    /** Retain grant history while immediately removing its effective access. */
    public function revoke(?string $id = null): ?Response
    {
        $this->request->allowMethod(['post']);
        $grant = TableRegistry::getTableLocator()->get('Awards.BestowalDelegations')->get($id);
        $this->Authorization->authorize($grant, 'revoke');
        (new BestowalDelegationService())->revoke($this->request->getAttribute('identity'), (int)$grant->id);
        $this->Flash->success(__('Delegated access revoked.'));

        return $this->redirect(['action' => 'gathering', $grant->gathering_id]);
    }
}
