<?php
declare(strict_types=1);

namespace App\Test\TestCase\Log\Formatter;

use App\KMP\Telemetry\RequestQueryCounter;
use App\Log\Formatter\QueryLogFormatter;
use Cake\TestSuite\TestCase;

class QueryLogFormatterTest extends TestCase
{
    protected function tearDown(): void
    {
        RequestQueryCounter::instance()->clearRequest();
        parent::tearDown();
    }

    public function testPrefixesQueryLogLineWithCurrentRequestContext(): void
    {
        $counter = new RequestQueryCounter();
        $counter->beginRequest(
            'abc123',
            'get',
            'example.test',
            '/members/view/1',
            '/members/view/1?tab=profile',
            '',
            false,
        );

        $formatter = new QueryLogFormatter();
        $line = $formatter->format('debug', 'connection= role=write duration=0.8 rows=1 SELECT 1');

        $this->assertStringContainsString(
            '[request_id=abc123 query_number=1 method=GET host=example.test '
            . 'path=/members/view/1 target=/members/view/1?tab=profile turbo_frame=- ajax=0]',
            $line,
        );
    }

    public function testUsesDelegatedRequestQueryNumberWhenAvailable(): void
    {
        $counter = new RequestQueryCounter();
        $counter->beginRequest('abc123', 'post', 'example.test', '/login', '/login', 'login-frame', true);

        $formatter = new QueryLogFormatter();
        $line = $formatter->format('debug', 'SELECT 1', ['request_query_number' => 4]);

        $this->assertStringContainsString(
            '[request_id=abc123 query_number=4 method=POST host=example.test '
            . 'path=/login target=/login turbo_frame=login-frame ajax=1]',
            $line,
        );
    }

    public function testDoesNotPrefixWithoutCurrentRequestContext(): void
    {
        RequestQueryCounter::instance()->clearRequest();

        $formatter = new QueryLogFormatter();
        $line = $formatter->format('debug', 'SELECT 1');

        $this->assertStringEndsWith('debug: SELECT 1', $line);
    }
}
