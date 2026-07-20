<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Model\Entity;

use App\Test\TestCase\BaseTestCase;
use Officers\Model\Entity\Office;

class OfficeTest extends BaseTestCase
{
    public function testSettingDeputyToIdSetsReportsToId(): void
    {
        $office = new Office();

        $office->deputy_to_id = 12;

        $this->assertSame(12, $office->deputy_to_id);
        $this->assertSame(12, $office->reports_to_id);
    }

    public function testSettingReportsToIdClearsDeputyToId(): void
    {
        $office = new Office(['deputy_to_id' => 12]);

        $office->reports_to_id = 34;

        $this->assertNull($office->deputy_to_id);
        $this->assertSame(34, $office->reports_to_id);
    }

    public function testSettingBranchTypesUpdatesApplicableBranchTypes(): void
    {
        $office = new Office();

        $office->branch_types = ['kingdom', 'barony'];

        $this->assertSame('"kingdom","barony"', $office->applicable_branch_types);
        $this->assertSame(['kingdom', 'barony'], $office->branch_types);
    }
}
