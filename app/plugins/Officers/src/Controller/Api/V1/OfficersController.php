<?php

declare(strict_types=1);

namespace Officers\Controller\Api\V1;


use Officers\Services\Api\ReadOnlyOfficerRosterServiceInterface;

/**
 * API controller for read-only officer roster listing and detail.
 *
 * Authorizes against entity policy since OfficersTablePolicy uses SKIP_BASE.
 */
class OfficersController extends AppController
{
    /**
     * List officers with optional filters and pagination.
     *
     * @param \Officers\Services\Api\ReadOnlyOfficerRosterServiceInterface $service
     * @return void
     */
    public function index(ReadOnlyOfficerRosterServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        // Authorize against entity policy (OfficerPolicy::canIndex) since
        // OfficersTablePolicy uses SKIP_BASE and has no registered methods.
        $this->Authorization->authorize($this->fetchTable('Officers.Officers')->newEmptyEntity(), 'index');

        $page = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 50)));
        $filters = [
            'branch' => $this->request->getQuery('branch'),
            'office_id' => $this->request->getQuery('office_id'),
            'status' => $this->request->getQuery('status'),
        ];

        $result = $service->list($identity, $filters, $page, $limit);
        $this->apiSuccess($result['data'], $result['meta']);
    }

    /**
     * View a single officer record by ID.
     *
     * @param int $id Officer ID
     * @param \Officers\Services\Api\ReadOnlyOfficerRosterServiceInterface $service
     * @return void
     */
    public function view(int $id, ReadOnlyOfficerRosterServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Officers')->newEmptyEntity(), 'view');

        $result = $service->getById($identity, $id);
        if ($result === null) {
            $this->apiError('NOT_FOUND', 'Officer record not found', [], 404);
            return;
        }

        $this->apiSuccess($result);
    }
}

