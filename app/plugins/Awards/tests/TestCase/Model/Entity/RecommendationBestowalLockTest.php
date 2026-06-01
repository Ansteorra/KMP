<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Model\Entity;

use Awards\Model\Entity\Recommendation;
use Cake\TestSuite\TestCase;

/**
 * Tests for recommendation bestowal lock helper.
 */
class RecommendationBestowalLockTest extends TestCase
{
    public function testIsLockedByBestowalWhenLinked(): void
    {
        $recommendation = new Recommendation();
        $recommendation->bestowal_id = 42;

        $this->assertTrue($recommendation->isLockedByBestowal());
    }

    public function testIsLockedByBestowalWhenNotLinked(): void
    {
        $recommendation = new Recommendation();

        $this->assertFalse($recommendation->isLockedByBestowal());
    }
}
