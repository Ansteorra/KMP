<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\MailerDiscoveryService;
use App\Test\TestCase\BaseTestCase;

class MailerDiscoveryServiceTest extends BaseTestCase
{
    private MailerDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->service = new MailerDiscoveryService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(MailerDiscoveryService::class, $this->service);
    }

    public function testDiscoverAllMailersReturnsArray(): void
    {
        $mailers = $this->service->discoverAllMailers();
        $this->assertIsArray($mailers);
    }

    public function testDiscoverAllMailersFindsAtLeastOneMailer(): void
    {
        $mailers = $this->service->discoverAllMailers();
        $this->assertNotEmpty($mailers, 'Should discover at least one mailer in the application');
    }

    public function testDiscoveredMailersHaveRequiredKeys(): void
    {
        $mailers = $this->service->discoverAllMailers();

        foreach ($mailers as $mailer) {
            $this->assertArrayHasKey('class', $mailer);
            $this->assertArrayHasKey('shortName', $mailer);
            $this->assertArrayHasKey('namespace', $mailer);
            $this->assertArrayHasKey('filePath', $mailer);
            $this->assertArrayHasKey('methods', $mailer);
        }
    }

    public function testDiscoveredMailerMethodsHaveRequiredKeys(): void
    {
        $mailers = $this->service->discoverAllMailers();
        $foundMethod = false;

        foreach ($mailers as $mailer) {
            foreach ($mailer['methods'] as $method) {
                $foundMethod = true;
                $this->assertArrayHasKey('name', $method);
                $this->assertArrayHasKey('parameters', $method);
                $this->assertArrayHasKey('availableVars', $method);
                $this->assertArrayHasKey('defaultSubject', $method);
                $this->assertArrayHasKey('docComment', $method);
            }
        }

        $this->assertTrue($foundMethod, 'Should discover at least one mailer method');
    }

    public function testGetMailerInfoReturnsNullForNonexistentClass(): void
    {
        $result = $this->service->getMailerInfo('NonExistent\\FakeMailer');
        $this->assertNull($result);
    }

    public function testGetMailerInfoReturnsNullForNonMailerClass(): void
    {
        $result = $this->service->getMailerInfo(MailerDiscoveryService::class);
        $this->assertNull($result);
    }

    public function testGetMailerInfoReturnsDataForValidMailer(): void
    {
        $mailers = $this->service->discoverAllMailers();
        if (empty($mailers)) {
            $this->markTestSkipped('No mailers discovered to test getMailerInfo');
        }

        $firstMailerClass = $mailers[0]['class'];
        $info = $this->service->getMailerInfo($firstMailerClass);

        $this->assertNotNull($info);
        $this->assertEquals($firstMailerClass, $info['class']);
        $this->assertArrayHasKey('methods', $info);
    }

    public function testGetMailerMethodInfoReturnsNullForNonexistentClass(): void
    {
        $result = $this->service->getMailerMethodInfo('NonExistent\\FakeMailer', 'someMethod');
        $this->assertNull($result);
    }

    public function testGetMailerMethodInfoReturnsNullForNonexistentMethod(): void
    {
        $mailers = $this->service->discoverAllMailers();
        if (empty($mailers)) {
            $this->markTestSkipped('No mailers discovered');
        }

        $firstMailerClass = $mailers[0]['class'];
        $result = $this->service->getMailerMethodInfo($firstMailerClass, 'nonExistentMethod_xyz');
        $this->assertNull($result);
    }

    public function testGetMailerMethodInfoReturnsDataForValidMethod(): void
    {
        $mailers = $this->service->discoverAllMailers();
        if (empty($mailers) || empty($mailers[0]['methods'])) {
            $this->markTestSkipped('No mailer methods discovered');
        }

        $firstMailerClass = $mailers[0]['class'];
        $firstMethodName = $mailers[0]['methods'][0]['name'];

        $info = $this->service->getMailerMethodInfo($firstMailerClass, $firstMethodName);

        $this->assertNotNull($info);
        $this->assertEquals($firstMethodName, $info['name']);
        $this->assertArrayHasKey('parameters', $info);
    }

    public function testMethodParametersHaveRequiredKeys(): void
    {
        $mailers = $this->service->discoverAllMailers();

        foreach ($mailers as $mailer) {
            foreach ($mailer['methods'] as $method) {
                foreach ($method['parameters'] as $param) {
                    $this->assertArrayHasKey('name', $param);
                    $this->assertArrayHasKey('type', $param);
                    $this->assertArrayHasKey('required', $param);
                    $this->assertIsBool($param['required']);
                }
            }
        }
    }

    public function testDiscoveredMailerShortNamesEndWithMailer(): void
    {
        $mailers = $this->service->discoverAllMailers();

        foreach ($mailers as $mailer) {
            $this->assertStringEndsWith(
                'Mailer',
                $mailer['shortName'],
                "Mailer short name '{$mailer['shortName']}' should end with 'Mailer'",
            );
        }
    }

    public function testDiscoveredMailerFilePathsExist(): void
    {
        $mailers = $this->service->discoverAllMailers();

        foreach ($mailers as $mailer) {
            $this->assertFileExists($mailer['filePath'], "Mailer file should exist: {$mailer['filePath']}");
        }
    }

    public function testDiscoverAllMailersIsIdempotent(): void
    {
        $firstRun = $this->service->discoverAllMailers();
        $secondRun = $this->service->discoverAllMailers();

        $this->assertEquals(
            array_column($firstRun, 'class'),
            array_column($secondRun, 'class'),
            'Multiple calls should return the same mailer classes',
        );
    }
}
