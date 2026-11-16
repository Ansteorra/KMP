<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use Awards\Model\Entity\BestowalDelegation;
use Awards\Policy\BestowalDelegationPolicy;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/** Creates and revokes grants while preserving their audit history. */
class BestowalDelegationService
{
    use LocatorAwareTrait;

    /** Recheck native authority and reuse repeat submissions without rewriting grant history. */
    public function grant(
        KmpIdentityInterface $actor,
        int $gatheringId,
        int $branchId,
        int $delegateId,
    ): BestowalDelegation {
        $table = $this->fetchTable('Awards.BestowalDelegations');

        return $table->getConnection()->transactional(function () use (
            $table,
            $actor,
            $gatheringId,
            $branchId,
            $delegateId,
        ): BestowalDelegation {
            // Serialize grants from one person so repeat submissions reuse an active grant.
            $grantor = $this->fetchTable('Members')->find()->where(['Members.id' => $actor->getIdentifier()])
                ->epilog('FOR UPDATE')->firstOrFail();
            $grant = $table->newEntity([
                'gathering_id' => $gatheringId, 'branch_id' => $branchId, 'delegate_id' => $delegateId,
            ]);
            if (!(new BestowalDelegationPolicy())->canGrant($grantor, $grant)) {
                throw new ForbiddenException(__('You may delegate only your own award permissions.'));
            }
            $this->fetchTable('Members')->find()->where([
                'Members.id' => $delegateId, 'Members.status' => 'verified',
            ])->firstOrFail();
            $existing = $table->find()->where([
                'gathering_id' => $gatheringId, 'branch_id' => $branchId,
                'grantor_id' => (int)$actor->getIdentifier(), 'delegate_id' => $delegateId,
                'revoked_at IS' => null,
            ])->first();
            if ($existing !== null) {
                return $existing;
            }
            $grant->set('grantor_id', (int)$actor->getIdentifier(), ['guard' => false]);
            $grant->set('created_by', (int)$actor->getIdentifier(), ['guard' => false]);

            return $table->saveOrFail($grant);
        });
    }

    /** Record an idempotent revocation without deleting the original grant. */
    public function revoke(KmpIdentityInterface $actor, int $id): BestowalDelegation
    {
        $table = $this->fetchTable('Awards.BestowalDelegations');

        return $table->getConnection()->transactional(function () use ($table, $actor, $id): BestowalDelegation {
            $grant = $table->find()->where(['BestowalDelegations.id' => $id])->epilog('FOR UPDATE')->firstOrFail();
            $currentActor = $this->fetchTable('Members')->get((int)$actor->getIdentifier());
            if (!$currentActor->isSuperUser() && !(new BestowalDelegationPolicy())->canRevoke($currentActor, $grant)) {
                throw new ForbiddenException(__('Only the grantor or an administrator may revoke this delegation.'));
            }
            if ($grant->revoked_at === null) {
                $grant->set('revoked_at', DateTime::now(), ['guard' => false]);
                $grant->set('revoked_by', (int)$actor->getIdentifier(), ['guard' => false]);
                $grant->set('modified_by', (int)$actor->getIdentifier(), ['guard' => false]);
                $table->saveOrFail($grant);
            }

            return $grant;
        });
    }
}
