<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\ControllerResolver;
use App\Policy\ReportsControllerPolicy;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\Exception\MissingPolicyException;

class ControllerResolverTest extends BaseTestCase
{
    protected ControllerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->resolver = new ControllerResolver();
    }

    public function testResolvePolicyForControllerByArray(): void
    {
        $policy = $this->resolver->getPolicy([
            'controller' => 'Reports',
            'plugin' => null,
            'prefix' => null,
        ]);

        $this->assertInstanceOf(ReportsControllerPolicy::class, $policy);
    }

    public function testResolvePolicyForArrayWithFalsePlugin(): void
    {
        $policy = $this->resolver->getPolicy([
            'controller' => 'Reports',
            'plugin' => false,
            'prefix' => null,
        ]);

        $this->assertInstanceOf(ReportsControllerPolicy::class, $policy);
    }

    public function testResolvePolicyForPluginController(): void
    {
        $policy = $this->resolver->getPolicy([
            'controller' => 'Reports',
            'plugin' => 'Officers',
            'prefix' => null,
        ]);

        $this->assertNotNull($policy, 'Should resolve a policy for Officers plugin Reports controller');
    }

    public function testMissingPolicyThrowsException(): void
    {
        $this->expectException(MissingPolicyException::class);
        $this->resolver->getPolicy([
            'controller' => 'NonExistentController',
            'plugin' => null,
            'prefix' => null,
        ]);
    }

    public function testGetControllerClassForCoreController(): void
    {
        $class = $this->resolver->getControllerClass('Members', null, null);

        $this->assertEquals('App\Controller\MembersController', $class);
    }

    public function testGetControllerClassForPluginController(): void
    {
        $class = $this->resolver->getControllerClass('Reports', 'Officers', null);

        $this->assertNotNull($class, 'Should resolve Officers plugin Reports controller class');
        $this->assertStringContainsString('Controller', $class);
    }

    public function testInvalidControllerNameWithBackslashThrowsException(): void
    {
        $this->expectException(MissingPolicyException::class);
        $this->resolver->getControllerClass('Invalid\\Name', null, null);
    }

    public function testInvalidControllerNameWithSlashThrowsException(): void
    {
        $this->expectException(MissingPolicyException::class);
        $this->resolver->getControllerClass('Invalid/Name', null, null);
    }

    public function testInvalidControllerNameWithDotThrowsException(): void
    {
        $this->expectException(MissingPolicyException::class);
        $this->resolver->getControllerClass('Invalid.Name', null, null);
    }

    public function testInvalidControllerNameLowercaseFirstCharThrowsException(): void
    {
        $this->expectException(MissingPolicyException::class);
        $this->resolver->getControllerClass('invalidName', null, null);
    }

    public function testResolvePolicyForStringThrowsMissingPolicy(): void
    {
        $this->expectException(MissingPolicyException::class);
        $this->resolver->getPolicy('NonExistentController');
    }

    public function testResolvePolicyForUnsupportedTypeThrowsException(): void
    {
        $this->expectException(MissingPolicyException::class);
        $this->resolver->getPolicy(12345);
    }
}
