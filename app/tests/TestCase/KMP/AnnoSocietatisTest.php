<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\AnnoSocietatis;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;

class AnnoSocietatisTest extends BaseTestCase
{
    public function testEpochAndMayBoundary(): void
    {
        $this->assertNull(AnnoSocietatis::year(new DateTime('1966-04-30')));
        $this->assertSame('', AnnoSocietatis::format(new DateTime('1966-04-30')));
        $this->assertSame('1 May A.S. I', AnnoSocietatis::format(new DateTime('1966-05-01')));
        $this->assertSame('30 April A.S. LX', AnnoSocietatis::format(new DateTime('2026-04-30')));
        $this->assertSame('1 May A.S. LXI', AnnoSocietatis::format(new DateTime('2026-05-01')));
        $this->assertSame('29 February A.S. LVIII', AnnoSocietatis::format(new DateTime('2024-02-29')));
    }

    public function testRangeUsesLocalDateAcrossSocietyNewYear(): void
    {
        $start = (new DateTime('2026-05-01 00:00:00', 'UTC'))->setTimezone('America/Chicago');
        $end = $start->addDays(1);
        $this->assertSame('30 April A.S. LX → 1 May A.S. LXI', AnnoSocietatis::range($start, $end));
        $this->assertSame('30 April A.S. LX', AnnoSocietatis::range($start, $start->addHours(1)));
    }
}
