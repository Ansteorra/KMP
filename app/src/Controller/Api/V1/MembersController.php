<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiController;
use Cake\Http\Exception\NotFoundException;

/**
 * Members API Controller
 *
 * Provides read-only access to member data for API clients.
 */
class MembersController extends ApiController
{
    /**
     * List members with pagination and filtering.
     *
     * @return void
     */
    public function index(): void
    {
        $this->paginate = [
            'limit' => 20,
            'maxLimit' => 100,
            'order' => ['Members.sca_name' => 'asc'],
        ];

        $query = $this->fetchTable('Members')->find();

        // Apply authorization scope
        $identity = $this->Authentication->getIdentity();
        $query = $identity->applyScope('index', $query);

        // Optional filters
        if ($this->request->getQuery('branch_id')) {
            $query->where(['Members.branch_id' => $this->request->getQuery('branch_id')]);
        }

        if ($this->request->getQuery('status')) {
            $query->where(['Members.status' => $this->request->getQuery('status')]);
        }

        if ($this->request->getQuery('search')) {
            $search = '%' . $this->request->getQuery('search') . '%';
            $query->where([
                'OR' => [
                    'Members.sca_name LIKE' => $search,
                    'Members.first_name LIKE' => $search,
                    'Members.last_name LIKE' => $search,
                    'Members.email_address LIKE' => $search,
                ],
            ]);
        }

        $members = $this->paginate($query);

        // Transform to API response format
        $data = [];
        foreach ($members as $member) {
            $data[] = $this->formatMember($member);
        }

        $this->apiSuccess($data, $this->getPaginationMeta());
    }

    /**
     * View a single member.
     *
     * @param int $id Member ID
     * @return void
     */
    public function view(int $id): void
    {
        $member = $this->fetchTable('Members')->find()
            ->where(['Members.id' => $id])
            ->contain(['Branches'])
            ->first();

        if (!$member) {
            throw new NotFoundException('Member not found');
        }

        // Check authorization
        $this->Authorization->authorize($member, 'view');

        $this->apiSuccess($this->formatMember($member, true));
    }

    /**
     * Format member data for API response.
     *
     * @param \App\Model\Entity\Member $member Member entity
     * @param bool $detailed Include detailed information
     * @return array
     */
    protected function formatMember($member, bool $detailed = false): array
    {
        $data = [
            'id' => $member->id,
            'sca_name' => $member->sca_name,
            'status' => $member->status,
            'branch_id' => $member->branch_id,
        ];

        if ($detailed) {
            $data += [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email_address' => $member->email_address,
                'membership_number' => $member->membership_number,
                'membership_expires_on' => $member->membership_expires_on?->toDateString(),
                'warrantable' => $member->warrantable,
                'created' => $member->created?->toIso8601String(),
                'modified' => $member->modified?->toIso8601String(),
            ];

            if ($member->branch) {
                $data['branch'] = [
                    'id' => $member->branch->id,
                    'name' => $member->branch->name,
                ];
            }
        }

        return $data;
    }
}
