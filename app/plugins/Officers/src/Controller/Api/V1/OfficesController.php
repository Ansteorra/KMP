<?php

declare(strict_types=1);

namespace Officers\Controller\Api\V1;

use Cake\Http\Exception\NotFoundException;
use Officers\Services\Api\ReadOnlyOfficeServiceInterface;

class OfficesController extends AppController
{
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

    public function view(int $id, ReadOnlyOfficeServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Offices'), 'index');

        $result = $service->getById($identity, $id);
        if ($result === null) {
            throw new NotFoundException('Office not found');
        }

        $this->apiSuccess($result);
    }
}

