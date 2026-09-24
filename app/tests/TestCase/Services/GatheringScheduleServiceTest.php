<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\Gathering;
use App\Services\GatheringScheduleService;
use App\Test\TestCase\BaseTestCase;

class GatheringScheduleServiceTest extends BaseTestCase
{
    /** Durations are elapsed minutes even when the gathering crosses a daylight-saving change. */
    public function testDurationAcrossDaylightSavingChange(): void
    {
        $service = new GatheringScheduleService();
        $gathering = new Gathering(['timezone' => 'America/Chicago']);
        $data = $service->prepareData([
            'start_datetime' => '2026-03-08T01:45', 'duration_minutes' => '30',
        ], $gathering, null);
        $this->assertSame('2026-03-08 07:45', $data['start_datetime']->format('Y-m-d H:i'));
        $this->assertSame('2026-03-08 08:15', $data['end_datetime']->format('Y-m-d H:i'));
        $this->assertTrue($data['has_end_time']);
    }
}
