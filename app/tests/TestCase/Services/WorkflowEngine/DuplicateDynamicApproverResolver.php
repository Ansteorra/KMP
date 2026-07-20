<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Test\TestCase\BaseTestCase;

class DuplicateDynamicApproverResolver
{
    /**
     * @return array<int>
     */
    public function resolveApprovers(): array
    {
        return [
            BaseTestCase::ADMIN_MEMBER_ID,
            BaseTestCase::ADMIN_MEMBER_ID,
            BaseTestCase::TEST_MEMBER_AGATHA_ID,
        ];
    }
}
