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
        $this->assertSame('', AnnoSocietatis::label(new DateTime('1966-04-30')));
        $this->assertSame('AS-I', AnnoSocietatis::label(new DateTime('1966-05-01')));
        $this->assertSame('AS-LX', AnnoSocietatis::label(new DateTime('2026-04-30')));
        $this->assertSame('AS-LXI', AnnoSocietatis::label(new DateTime('2026-05-01')));
        $this->assertSame('AS-LVIII', AnnoSocietatis::label(new DateTime('2024-02-29')));
    }

    public function testSuffixUsesLocalDateAndRepeatsOnlyWhenSocietyYearChanges(): void
    {
        $start = (new DateTime('2026-05-01 00:00:00', 'UTC'))->setTimezone('America/Chicago');
        $this->assertSame(' (AS-LX)', AnnoSocietatis::suffix($start));
        $this->assertSame(' (AS-LXI)', AnnoSocietatis::suffix($start->addDays(1), $start));
        $this->assertSame('', AnnoSocietatis::suffix($start->addHours(1), $start));
        $this->assertSame('', AnnoSocietatis::suffix(new DateTime('1966-04-30')));
        $this->assertSame('', AnnoSocietatis::suffix(new DateTime('2027-01-01'), new DateTime('2026-12-31')));
    }
}
