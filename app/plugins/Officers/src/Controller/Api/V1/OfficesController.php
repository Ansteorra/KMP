<?php

declare(strict_types=1);

namespace Officers\Controller\Api\V1;


use Officers\Services\Api\ReadOnlyOfficeServiceInterface;

/**
 * API controller for read-only office listing and detail.
 *
 * @property \Cake\ORM\Table $Offices
 */
class OfficesController extends AppController
{
    /**
     * List offices with optional filters and pagination.
     *
     * @param \Officers\Services\Api\ReadOnlyOfficeServiceInterface $service
     * @return void
     */
    public function index(ReadOnlyOfficeServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Offices'), 'index');

        $page = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 50)));
        $filters = [
            'department_id' => $this->request->getQuery('department_id'),
            'requires_warrant' => $this->request->getQuery('requires_warrant'),
            'search' => $this->request->getQuery('search'),
        ];

        $result = $service->list($identity, $filters, $page, $limit);
        $this->apiSuccess($result['data'], $result['meta']);
    }

    /**
     * View a single office by ID.
     *
     * @param int $id Office ID
     * @param \Officers\Services\Api\ReadOnlyOfficeServiceInterface $service
     * @return void
     */
    public function view(int $id, ReadOnlyOfficeServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Offices'), 'index');

        $result = $service->getById($identity, $id);
        if ($result === null) {
            $this->apiError('NOT_FOUND', 'Office not found', [], 404);
            return;
        }

        $this->apiSuccess($result);
    }
}

