<?php

declare(strict_types=1);

namespace Officers\Controller\Api\V1;


use Officers\Services\Api\ReadOnlyDepartmentServiceInterface;

class DepartmentsController extends AppController
{
    public function index(ReadOnlyDepartmentServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Departments'), 'index');

        $page = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 50)));
        $filters = [
            'search' => $this->request->getQuery('search'),
        ];

        $result = $service->list($identity, $filters, $page, $limit);
        $this->apiSuccess($result['data'], $result['meta']);
    }

    public function view(int $id, ReadOnlyDepartmentServiceInterface $service): void
    {
        $identity = $this->getKmpIdentity();
        $this->Authorization->authorize($this->fetchTable('Officers.Departments'), 'index');

        $result = $service->getById($identity, $id);
        if ($result === null) {
            $this->apiError('NOT_FOUND', 'Department not found', [], 404);
            return;
        }

        $this->apiSuccess($result);
    }
}

