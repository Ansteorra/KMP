<?php

declare(strict_types=1);

namespace Officers\Controller\Api\V1;

use Cake\Http\Exception\NotFoundException;
use Officers\Services\Api\ReadOnlyOfficerRosterServiceInterface;

class OfficersController extends AppController
{
    public function index(ReadOnlyOfficerRosterServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Officers'), 'index');

        $page = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 50)));
        $filters = [
            'branch_id' => $this->request->getQuery('branch_id'),
            'office_id' => $this->request->getQuery('office_id'),
            'status' => $this->request->getQuery('status'),
        ];

        $result = $service->list($identity, $filters, $page, $limit);
        $this->apiSuccess($result['data'], $result['meta']);
    }

    public function view(int $id, ReadOnlyOfficerRosterServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Officers'), 'index');

        $result = $service->getById($identity, $id);
        if ($result === null) {
            throw new NotFoundException('Officer record not found');
        }

        $this->apiSuccess($result);
    }
}

