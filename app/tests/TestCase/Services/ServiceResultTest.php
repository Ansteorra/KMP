<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;

class ServiceResultTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
    }

    public function testConstructorWithSuccessOnly(): void
    {
        $result = new ServiceResult(true);
        $this->assertTrue($result->success);
        $this->assertNull($result->reason);
        $this->assertNull($result->data);
    }

    public function testConstructorWithFailureAndReason(): void
    {
        $result = new ServiceResult(false, 'Something went wrong');
        $this->assertFalse($result->success);
        $this->assertEquals('Something went wrong', $result->reason);
        $this->assertNull($result->data);
    }

    public function testConstructorWithAllArguments(): void
    {
        $data = ['id' => 42, 'name' => 'test'];
        $result = new ServiceResult(true, 'Created', $data);
        $this->assertTrue($result->success);
        $this->assertEquals('Created', $result->reason);
        $this->assertEquals($data, $result->data);
    }

    public function testIsSuccessReturnsTrue(): void
    {
        $result = new ServiceResult(true);
        $this->assertTrue($result->isSuccess());
    }

    public function testIsSuccessReturnsFalse(): void
    {
        $result = new ServiceResult(false);
        $this->assertFalse($result->isSuccess());
    }

    public function testGetDataReturnsPayload(): void
    {
        $result = new ServiceResult(true, null, 99);
        $this->assertEquals(99, $result->getData());
    }

    public function testGetDataReturnsNullWhenNoData(): void
    {
        $result = new ServiceResult(true);
        $this->assertNull($result->getData());
    }

    public function testGetErrorReturnsReason(): void
    {
        $result = new ServiceResult(false, 'Error occurred');
        $this->assertEquals('Error occurred', $result->getError());
    }

    public function testGetErrorReturnsNullOnSuccess(): void
    {
        $result = new ServiceResult(true);
        $this->assertNull($result->getError());
    }

    public function testDataCanBeEntityArray(): void
    {
        $entities = [['id' => 1], ['id' => 2]];
        $result = new ServiceResult(true, null, $entities);
        $this->assertIsArray($result->getData());
        $this->assertCount(2, $result->getData());
    }

    public function testDataCanBeInteger(): void
    {
        $result = new ServiceResult(true, '', 42);
        $this->assertEquals(42, $result->getData());
    }

    public function testDataCanBeString(): void
    {
        $result = new ServiceResult(true, null, 'some-string-id');
        $this->assertEquals('some-string-id', $result->getData());
    }

    public function testReasonNullWhenNotProvided(): void
    {
        $result = new ServiceResult(true);
        $this->assertNull($result->reason);
    }

    public function testReasonSetWhenProvided(): void
    {
        $result = new ServiceResult(false, 'Validation failed');
        $this->assertEquals('Validation failed', $result->reason);
    }

    public function testSuccessWithEmptyStringReason(): void
    {
        $result = new ServiceResult(true, '');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('', $result->reason);
    }

    public function testPublicPropertiesDirectlyAccessible(): void
    {
        $result = new ServiceResult(true, 'ok', ['key' => 'value']);
        $this->assertTrue($result->success);
        $this->assertEquals('ok', $result->reason);
        $this->assertEquals(['key' => 'value'], $result->data);
    }
}
