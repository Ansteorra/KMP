<?php
declare(strict_types=1);

namespace Waivers\Test\TestCase\Model\Entity;

use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Waivers\Model\Entity\GatheringWaiver;

class GatheringWaiverTest extends BaseTestCase
{
    public function testCanBeDeclinedWithinExtendedWindow(): void
    {
        $waiver = new GatheringWaiver([
            'status' => 'active',
            'created' => new DateTime('-31 days'),
            'declined_at' => null,
        ]);

        $this->assertTrue($waiver->can_be_declined);
    }

    public function testCanBeDeclinedOutsideExtendedWindow(): void
    {
        $waiver = new GatheringWaiver([
            'status' => 'active',
            'created' => new DateTime('-91 days'),
            'declined_at' => null,
        ]);

        $this->assertFalse($waiver->can_be_declined);
    }
}
