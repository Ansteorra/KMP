<?php

declare(strict_types=1);

namespace Activities\Controller\Api\V1;

use Activities\Model\Entity\Authorization;

/**
 * Authorizations API Controller
 *
 * Provides authenticated lookup of a member's current activity authorizations
 * by membership number, SCA name, or email address.
 */
class AuthorizationsController extends AppController
{
    /**
     * List current (approved, non-expired) authorizations for a member.
     *
     * Lookup is by exactly one of: membership_number, sca_name, or email.
     *
     * @return void
     */
    public function memberAuthorizations(): void
    {
        $this->Authorization->authorize(
            $this->fetchTable('Activities.Authorizations')->newEmptyEntity(),
            'memberAuthorizations'
        );

        $membershipNumber = $this->request->getQuery('membership_number');
        $scaName = $this->request->getQuery('sca_name');
        $email = $this->request->getQuery('email');

        $providedCount = (int)!empty($membershipNumber) + (int)!empty($scaName) + (int)!empty($email);

        if ($providedCount === 0) {
            $this->apiError(
                'MISSING_PARAMETER',
                'Provide exactly one of: membership_number, sca_name, or email',
                [],
                400
            );
            return;
        }

        if ($providedCount > 1) {
            $this->apiError(
                'INVALID_PARAMETER',
                'Provide only one of: membership_number, sca_name, or email',
                [],
                400
            );
            return;
        }

        // Look up the member
        $membersTable = $this->fetchTable('Members');
        $memberQuery = $membersTable->find()
            ->select(['id', 'public_id', 'sca_name', 'membership_number', 'email_address']);

        if (!empty($membershipNumber)) {
            $memberQuery->where(['Members.membership_number' => $membershipNumber]);
        } elseif (!empty($scaName)) {
            $memberQuery->where(['Members.sca_name' => $scaName]);
        } else {
            $memberQuery->where(['Members.email_address' => $email]);
        }

        $member = $memberQuery->first();

        if (!$member) {
            $this->apiError('NOT_FOUND', 'Member not found', [], 404);
            return;
        }

        // Get current approved authorizations
        $authorizations = $this->fetchTable('Activities.Authorizations')
            ->find('current')
            ->contain([
                'Activities' => fn($q) => $q->select(['id', 'name']),
                'Activities.ActivityGroups' => fn($q) => $q->select(['id', 'name']),
            ])
            ->where([
                'Authorizations.member_id' => $member->id,
                'Authorizations.status' => Authorization::APPROVED_STATUS,
            ])
            ->orderBy(['Activities.name' => 'ASC'])
            ->all();

        $data = [];
        foreach ($authorizations as $auth) {
            $data[] = [
                'activity' => $auth->activity->name,
                'activity_group' => $auth->activity->activity_group->name ?? null,
                'status' => $auth->status,
                'start_on' => $auth->start_on?->toIso8601String(),
                'expires_on' => $auth->expires_on?->toIso8601String(),
            ];
        }

        $this->apiSuccess([
            'member' => [
                'id' => $member->public_id,
                'sca_name' => $member->sca_name,
                'membership_number' => $member->membership_number,
            ],
            'authorizations' => $data,
        ]);
    }
}
