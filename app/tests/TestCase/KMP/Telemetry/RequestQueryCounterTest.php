<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP\Telemetry;

use App\KMP\Telemetry\RequestQueryCounter;
use Cake\Database\Log\LoggedQuery;
use Cake\TestSuite\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class RequestQueryCounterTest extends TestCase
{
    protected function tearDown(): void
    {
        RequestQueryCounter::instance()->clearRequest();
        parent::tearDown();
    }

    public function testCountsQueriesAndAccumulatesDuration(): void
    {
        $counter = new RequestQueryCounter();

        $counter->log(LogLevel::DEBUG, 'q1', ['query' => $this->makeLoggedQuery(12.5)]);
        $counter->log(LogLevel::DEBUG, 'q2', ['query' => $this->makeLoggedQuery(3.25)]);

        $this->assertSame(2, $counter->count());
        $this->assertEqualsWithDelta(15.75, $counter->totalMs(), 0.001);
    }

    public function testCountsWithoutLoggedQuery(): void
    {
        $counter = new RequestQueryCounter();
        $counter->log(LogLevel::DEBUG, 'raw query without context');
        $this->assertSame(1, $counter->count());
        $this->assertSame(0.0, $counter->totalMs());
    }

    public function testResetClearsCounters(): void
    {
        $counter = new RequestQueryCounter();
        $counter->log(LogLevel::DEBUG, 'q', ['query' => $this->makeLoggedQuery(5.0)]);
        $counter->reset();

        $this->assertSame(0, $counter->count());
        $this->assertSame(0.0, $counter->totalMs());
    }

    public function testDelegatesToInnerLogger(): void
    {
        $captured = [];
        $inner = new class ($captured) extends AbstractLogger {
            /**
             * @var array<int,array<string,mixed>>
             */
            private array $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->captured[] = ['level' => $level, 'message' => (string)$message];
            }
        };

        $counter = new RequestQueryCounter($inner);
        $counter->log(LogLevel::DEBUG, 'forward me');

        $this->assertCount(1, $captured);
        $this->assertSame('forward me', $captured[0]['message']);
    }

    public function testAddsRequestContextToDelegatedQueryLogs(): void
    {
        $captured = [];
        $inner = new class ($captured) extends AbstractLogger {
            /**
             * @var array<int,array<string,mixed>>
             */
            private array $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->captured[] = [
                    'level' => $level,
                    'message' => (string)$message,
                    'context' => $context,
                ];
            }
        };

        $counter = new RequestQueryCounter($inner);
        $counter->beginRequest('abc123', 'get', 'example.test', '/members/view/1', '/members/view/1?tab=profile', '', false);
        $counter->log(LogLevel::DEBUG, 'SELECT 1', ['query' => $this->makeLoggedQuery(1.5)]);

        $this->assertCount(1, $captured);
        $this->assertStringStartsWith(
            '[request_id=abc123 query_number=1 method=GET host=example.test '
            . 'path=/members/view/1 target=/members/view/1?tab=profile turbo_frame=- ajax=0]',
            $captured[0]['message'],
        );
        $this->assertSame('abc123', $captured[0]['context']['request_id']);
        $this->assertSame(1, $captured[0]['context']['request_query_number']);
        $this->assertSame('/members/view/1?tab=profile', $captured[0]['context']['request_target']);
    }

    public function testClearRequestRemovesDelegatedQueryLogContext(): void
    {
        $captured = [];
        $inner = new class ($captured) extends AbstractLogger {
            /**
             * @var array<int,array<string,mixed>>
             */
            private array $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->captured[] = ['message' => (string)$message, 'context' => $context];
            }
        };

        $counter = new RequestQueryCounter($inner);
        $counter->beginRequest('abc123', 'get', 'example.test', '/members/view/1', '/members/view/1', '', false);
        $counter->clearRequest();
        $counter->log(LogLevel::DEBUG, 'SELECT 1');

        $this->assertSame('SELECT 1', $captured[0]['message']);
        $this->assertArrayNotHasKey('request_id', $captured[0]['context']);
    }

    public function testSharedInstanceIsReusable(): void
    {
        RequestQueryCounter::setInstance(new RequestQueryCounter());
        $first = RequestQueryCounter::instance();
        $first->log(LogLevel::DEBUG, 'q');

        $second = RequestQueryCounter::instance();
        $this->assertSame($first, $second);
        $this->assertSame(1, $second->count());

        // Clean up so subsequent tests start fresh.
        RequestQueryCounter::setInstance(new RequestQueryCounter());
    }

    private function makeLoggedQuery(float $took): LoggedQuery
    {
        $query = new LoggedQuery();
        $query->setContext(['query' => 'SELECT 1', 'took' => $took, 'numRows' => 0]);

        return $query;
    }
}
