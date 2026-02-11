<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Policy;

use App\Test\TestCase\BaseTestCase;
use Waivers\Policy\WaiverTypePolicy;

/**
 * Waivers\Policy\WaiverTypePolicy Test Case
 * 
 * Note: KMP uses database-driven authorization with BasePolicy._hasPolicy()
 * Policy classes extend BasePolicy with empty bodies, so authorization
 * logic is tested through integration tests in the controller tests.
 * 
 * These tests verify the policy class exists and extends BasePolicy correctly.
 */
class WaiverTypePolicyTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \Waivers\Policy\WaiverTypePolicy
     */
    protected $WaiverTypePolicy;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->WaiverTypePolicy = new WaiverTypePolicy();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WaiverTypePolicy);
        parent::tearDown();
    }

    /**
     * Test policy class exists and can be instantiated
     *
     * @return void
     */
    public function testPolicyClassExists(): void
    {
        $this->assertInstanceOf(
            WaiverTypePolicy::class,
            $this->WaiverTypePolicy,
            'WaiverTypePolicy should be instantiable'
        );
    }

    /**
     * Test policy extends BasePolicy
     *
     * @return void
     */
    public function testExtendsBasePolicy(): void
    {
        $this->assertInstanceOf(
            'App\Policy\BasePolicy',
            $this->WaiverTypePolicy,
            'WaiverTypePolicy should extend BasePolicy'
        );
    }

    /**
     * Test policy implements BeforePolicyInterface
     *
     * @return void
     */
    public function testImplementsBeforePolicyInterface(): void
    {
        $this->assertInstanceOf(
            'Authorization\Policy\BeforePolicyInterface',
            $this->WaiverTypePolicy,
            'WaiverTypePolicy should implement BeforePolicyInterface (via BasePolicy)'
        );
    }
}
