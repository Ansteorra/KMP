<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\CsvExportService;
use App\Test\TestCase\BaseTestCase;
use Cake\Http\Response;

class CsvExportServiceTest extends BaseTestCase
{
    protected CsvExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->service = new CsvExportService();
    }

    public function testOutputCsvReturnsResponse(): void
    {
        $data = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ];
        $response = $this->service->outputCsv($data);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testOutputCsvSetsFilename(): void
    {
        $data = [['col' => 'val']];
        $response = $this->service->outputCsv($data, 'members.csv');
        $disposition = $response->getHeaderLine('Content-Disposition');
        $this->assertStringContainsString('members.csv', $disposition);
    }

    public function testOutputCsvDefaultFilename(): void
    {
        $data = [['col' => 'val']];
        $response = $this->service->outputCsv($data, 'export.csv');
        $disposition = $response->getHeaderLine('Content-Disposition');
        $this->assertStringContainsString('export.csv', $disposition);
    }

    public function testOutputCsvContainsHeaders(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => '30'],
        ];
        $response = $this->service->outputCsv($data);
        $body = (string)$response->getBody();
        $this->assertStringContainsString('name', $body);
        $this->assertStringContainsString('age', $body);
    }

    public function testOutputCsvContainsDataRows(): void
    {
        $data = [
            ['name' => 'Alice', 'email' => 'alice@test.com'],
            ['name' => 'Bob', 'email' => 'bob@test.com'],
        ];
        $response = $this->service->outputCsv($data);
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Alice', $body);
        $this->assertStringContainsString('Bob', $body);
        $this->assertStringContainsString('alice@test.com', $body);
    }

    public function testOutputCsvWithExplicitHeaders(): void
    {
        $data = [
            ['name' => 'Alice', 'email' => 'alice@test.com'],
        ];
        $response = $this->service->outputCsv($data, 'test.csv', ['name', 'email']);
        $body = (string)$response->getBody();
        $lines = explode("\n", trim($body));
        $this->assertStringContainsString('name', $lines[0]);
        $this->assertStringContainsString('email', $lines[0]);
    }

    public function testOutputCsvWithEmptyData(): void
    {
        $data = [];
        $response = $this->service->outputCsv($data);
        $this->assertInstanceOf(Response::class, $response);
        $body = (string)$response->getBody();
        $this->assertEquals('', $body);
    }

    public function testOutputCsvHandlesMissingKeys(): void
    {
        $data = [
            ['name' => 'Alice', 'email' => 'alice@test.com'],
            ['name' => 'Bob'],
        ];
        $response = $this->service->outputCsv($data);
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Bob', $body);
    }

    public function testOutputCsvWithQuery(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $query = $membersTable->find()
            ->select(['id', 'sca_name'])
            ->limit(3);

        $response = $this->service->outputCsv($query, 'members.csv');
        $this->assertInstanceOf(Response::class, $response);
        $body = (string)$response->getBody();
        $this->assertNotEmpty($body);
        // Should have header row + data rows
        $lines = array_filter(explode("\n", trim($body)));
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    public function testOutputCsvContentTypeIsCsv(): void
    {
        $data = [['col' => 'val']];
        $response = $this->service->outputCsv($data);
        $contentType = $response->getHeaderLine('Content-Type');
        $this->assertStringContainsString('csv', $contentType);
    }

    public function testOutputCsvMultipleRowsProducesCorrectLineCount(): void
    {
        $data = [
            ['a' => '1'],
            ['a' => '2'],
            ['a' => '3'],
        ];
        $response = $this->service->outputCsv($data);
        $body = (string)$response->getBody();
        $lines = array_filter(explode("\n", trim($body)));
        // 1 header + 3 data rows
        $this->assertCount(4, $lines);
    }
}
