<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use Cake\Core\ContainerInterface;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use ReflectionMethod;

/**
 * Edge case tests for deadline string parsing in DefaultWorkflowEngine.
 */
class DeadlineParsingTest extends TestCase
{
    private DefaultWorkflowEngine $engine;
    private ReflectionMethod $parseDeadline;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->createMock(ContainerInterface::class);
        $this->engine = new DefaultWorkflowEngine($container);

        $this->parseDeadline = new ReflectionMethod(DefaultWorkflowEngine::class, 'parseDeadline');
        $this->parseDeadline->setAccessible(true);
    }

    private function parse(string $deadline): ?DateTime
    {
        return $this->parseDeadline->invoke($this->engine, $deadline);
    }

    // =====================================================
    // Valid formats
    // =====================================================

    public function testParseDays(): void
    {
        $before = DateTime::now();
        $result = $this->parse('3d');
        $after = DateTime::now();

        $this->assertNotNull($result);
        $expectedMin = $before->modify('+3 days');
        $expectedMax = $after->modify('+3 days');
        $this->assertGreaterThanOrEqual($expectedMin->getTimestamp(), $result->getTimestamp());
        $this->assertLessThanOrEqual($expectedMax->getTimestamp(), $result->getTimestamp());
    }

    public function testParseHours(): void
    {
        $before = DateTime::now();
        $result = $this->parse('24h');
        $after = DateTime::now();

        $this->assertNotNull($result);
        $expectedMin = $before->modify('+24 hours');
        $expectedMax = $after->modify('+24 hours');
        $this->assertGreaterThanOrEqual($expectedMin->getTimestamp(), $result->getTimestamp());
        $this->assertLessThanOrEqual($expectedMax->getTimestamp(), $result->getTimestamp());
    }

    public function testParseMinutes(): void
    {
        $before = DateTime::now();
        $result = $this->parse('30m');
        $after = DateTime::now();

        $this->assertNotNull($result);
        $expectedMin = $before->modify('+30 minutes');
        $expectedMax = $after->modify('+30 minutes');
        $this->assertGreaterThanOrEqual($expectedMin->getTimestamp(), $result->getTimestamp());
        $this->assertLessThanOrEqual($expectedMax->getTimestamp(), $result->getTimestamp());
    }

    public function testParseSingleDay(): void
    {
        $result = $this->parse('1d');
        $this->assertNotNull($result);
        $this->assertGreaterThan(DateTime::now()->getTimestamp(), $result->getTimestamp());
    }

    public function testParseSingleHour(): void
    {
        $result = $this->parse('1h');
        $this->assertNotNull($result);
        $this->assertGreaterThan(DateTime::now()->getTimestamp(), $result->getTimestamp());
    }

    public function testParseLargeNumber(): void
    {
        $result = $this->parse('365d');
        $this->assertNotNull($result);

        $expected = DateTime::now()->modify('+365 days');
        // Allow 2 seconds tolerance
        $this->assertEqualsWithDelta($expected->getTimestamp(), $result->getTimestamp(), 2);
    }

    // =====================================================
    // Edge cases
    // =====================================================

    public function testParseZeroDays(): void
    {
        $before = DateTime::now();
        $result = $this->parse('0d');
        $this->assertNotNull($result);

        // 0d means +0 days, so basically "now"
        $this->assertEqualsWithDelta($before->getTimestamp(), $result->getTimestamp(), 2);
    }

    public function testParseZeroHours(): void
    {
        $before = DateTime::now();
        $result = $this->parse('0h');
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta($before->getTimestamp(), $result->getTimestamp(), 2);
    }

    public function testParseInvalidFormatReturnsNull(): void
    {
        // "garbage" is not a valid date string, should return null
        $result = $this->parse('garbage');
        $this->assertNull($result);
    }

    public function testParseEmptyStringFallsThrough(): void
    {
        // Empty string falls through to PHP date parsing (returns current time)
        $result = $this->parse('');
        $this->assertNotNull($result);
    }

    public function testParseWeekFormatNotSupported(): void
    {
        // "1w" doesn't match the d/h/m pattern
        $result = $this->parse('1w');
        $this->assertNull($result);
    }

    public function testParseNegativeNumberFallsThrough(): void
    {
        // Doesn't match d/h/m regex, falls through to PHP date parsing
        $result = $this->parse('-3d');
        $this->assertNotNull($result);
    }

    public function testParseDecimalFallsThrough(): void
    {
        // Doesn't match integer regex, falls through to PHP date parsing
        $result = $this->parse('1.5d');
        $this->assertNotNull($result);
    }

    public function testParseDateStringFallback(): void
    {
        // Valid ISO date string should work via fallback
        $result = $this->parse('2030-01-15');
        $this->assertNotNull($result);
        $this->assertSame(2030, (int)$result->format('Y'));
    }

    public function testParseCaseInsensitive(): void
    {
        // Regex uses /i flag so uppercase should work
        $result = $this->parse('3D');
        $this->assertNotNull($result);
        $expected = DateTime::now()->modify('+3 days');
        $this->assertEqualsWithDelta($expected->getTimestamp(), $result->getTimestamp(), 2);
    }
}
