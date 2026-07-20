<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\DataverseGridTrait;
use App\KMP\TimezoneHelper;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * Test the convertDateBoundaryToUtc method in DataverseGridTrait.
 *
 * Verifies that date-only strings used in date-range filters are correctly
 * converted from kingdom timezone to UTC before SQL comparison, so that
 * UTC-stored datetimes are filtered properly.
 */
class DateBoundaryConversionTest extends TestCase
{
    use DataverseGridTrait;

    /**
     * Date-only start boundary should become start-of-day in kingdom TZ, then UTC.
     *
     * If kingdom is America/New_York (UTC-4 in summer):
     *   April 10 00:00:00 EDT → April 10 04:00:00 UTC
     */
    public function testStartBoundaryEasternTimezone(): void
    {
        $this->forceAppTimezone('America/New_York');

        $result = $this->convertDateBoundaryToUtc('2026-04-10', true);

        // April 10 00:00:00 EDT = April 10 04:00:00 UTC
        $this->assertEquals('2026-04-10 04:00:00', $result);
    }

    /**
     * Date-only end boundary should become end-of-day in kingdom TZ, then UTC.
     *
     * If kingdom is America/New_York (UTC-4 in summer):
     *   April 10 23:59:59 EDT → April 11 03:59:59 UTC
     */
    public function testEndBoundaryEasternTimezone(): void
    {
        $this->forceAppTimezone('America/New_York');

        $result = $this->convertDateBoundaryToUtc('2026-04-10', false);

        // April 10 23:59:59 EDT = April 11 03:59:59 UTC
        $this->assertEquals('2026-04-11 03:59:59', $result);
    }

    /**
     * Kingdom ahead of UTC (e.g., Australia/Sydney UTC+10).
     *
     * April 10 00:00:00 AEST → April 9 14:00:00 UTC
     */
    public function testStartBoundarySydneyTimezone(): void
    {
        $this->forceAppTimezone('Australia/Sydney');

        $result = $this->convertDateBoundaryToUtc('2026-04-10', true);

        // April 10 00:00:00 AEST = April 9 14:00:00 UTC
        $this->assertEquals('2026-04-09 14:00:00', $result);
    }

    /**
     * Kingdom ahead of UTC end boundary.
     *
     * April 10 23:59:59 AEST → April 10 13:59:59 UTC
     */
    public function testEndBoundarySydneyTimezone(): void
    {
        $this->forceAppTimezone('Australia/Sydney');

        $result = $this->convertDateBoundaryToUtc('2026-04-10', false);

        // April 10 23:59:59 AEST = April 10 13:59:59 UTC
        $this->assertEquals('2026-04-10 13:59:59', $result);
    }

    /**
     * UTC kingdom: no conversion needed, just appends time component.
     */
    public function testUtcTimezonePassthrough(): void
    {
        $this->forceAppTimezone('UTC');

        $this->assertEquals('2026-04-10', $this->convertDateBoundaryToUtc('2026-04-10', true));
        $this->assertEquals('2026-04-10 23:59:59', $this->convertDateBoundaryToUtc('2026-04-10', false));
    }

    /**
     * Full datetime strings (not date-only) should pass through unchanged.
     */
    public function testFullDatetimePassthrough(): void
    {
        $this->forceAppTimezone('America/New_York');

        $input = '2026-04-10 15:30:00';
        $this->assertEquals($input, $this->convertDateBoundaryToUtc($input, true));
        $this->assertEquals($input, $this->convertDateBoundaryToUtc($input, false));
    }

    /**
     * Warrant activated at 8pm Eastern (midnight UTC) should be included
     * in "today" Current view filter when kingdom is Eastern.
     *
     * This is the core regression scenario: start_on = '2026-04-10 00:30:00' UTC
     * should match <= end-of-day April 9 Eastern (converted to UTC: April 10 03:59:59).
     */
    public function testRegressionWarrantActivatedNearMidnightUtc(): void
    {
        $this->forceAppTimezone('America/New_York');

        // "Today" in Eastern is April 9
        $todayEastern = '2026-04-09';

        // End of today boundary, converted to UTC
        $endBoundaryUtc = $this->convertDateBoundaryToUtc($todayEastern, false);

        // Warrant activated at 8:30pm Eastern = 12:30am UTC April 10
        $warrantStartUtc = '2026-04-10 00:30:00';

        // The boundary should extend past the warrant's UTC start time
        $this->assertGreaterThanOrEqual($warrantStartUtc, $endBoundaryUtc,
            'End-of-day boundary must include warrant activated near midnight UTC');
    }

    /**
     * Force TimezoneHelper to return a specific timezone for testing.
     */
    private function forceAppTimezone(string $tz): void
    {
        // Use reflection to set the internal cache
        $ref = new \ReflectionClass(TimezoneHelper::class);
        $prop = $ref->getProperty('appTimezoneCache');
        $prop->setAccessible(true);
        $prop->setValue(null, $tz);
    }

    protected function tearDown(): void
    {
        // Reset the timezone cache
        $ref = new \ReflectionClass(TimezoneHelper::class);
        $prop = $ref->getProperty('appTimezoneCache');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }
}
