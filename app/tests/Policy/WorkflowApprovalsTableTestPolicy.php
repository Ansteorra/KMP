<?php
declare(strict_types=1);

namespace App\Test\Policy;

class WorkflowApprovalsTableTestPolicy
{
    public function canApprove($identity, $entity): bool
    {
        return (int)$identity->id === (int)$entity->id;
    }
}
