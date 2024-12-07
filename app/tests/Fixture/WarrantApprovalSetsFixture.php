<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * WarrantApprovalSetsFixture
 */
class WarrantApprovalSetsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet',
                'planned_expires_on' => '2024-12-07 15:18:35',
                'planned_start_on' => '2024-12-07 15:18:35',
                'approvals_required' => 1,
                'approval_count' => 1,
                'created_by' => 1,
                'created' => 1733584715,
            ],
        ];
        parent::init();
    }
}
