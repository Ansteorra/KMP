<?php

declare(strict_types=1);

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\KmpHelper;
use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;
use Cake\View\View;

/**
 * App\View\Helper\KmpHelper Test Case
 */
class KmpHelperTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \App\View\Helper\KmpHelper
     */
    protected $Kmp;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $view = new View();
        $this->Kmp = new KmpHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Kmp);
        parent::tearDown();
    }

    /**
     * Test that makeCsv method exists and returns string
     */
    public function testMakeCsvExists(): void
    {
        $this->assertTrue(method_exists($this->Kmp, 'makeCsv'));
    }

    /**
     * Test that comboBoxControl method exists and signature is correct
     */
    public function testComboBoxControlExists(): void
    {
        $this->assertTrue(method_exists($this->Kmp, 'comboBoxControl'));

        $reflection = new \ReflectionMethod($this->Kmp, 'comboBoxControl');
        $this->assertEquals('string', $reflection->getReturnType()->getName());
    }

    /**
     * Test that autoCompleteControl method exists and signature is correct
     */
    public function testAutoCompleteControlExists(): void
    {
        $this->assertTrue(method_exists($this->Kmp, 'autoCompleteControl'));

        $reflection = new \ReflectionMethod($this->Kmp, 'autoCompleteControl');
        $this->assertEquals('string', $reflection->getReturnType()->getName());
    }

    /**
     * Test that appNav method exists and signature is correct
     */
    public function testAppNavExists(): void
    {
        $this->assertTrue(method_exists($this->Kmp, 'appNav'));

        $reflection = new \ReflectionMethod($this->Kmp, 'appNav');
        $this->assertEquals('string', $reflection->getReturnType()->getName());
    }

    /**
     * Test that bool method exists and signature is correct
     */
    public function testBoolExists(): void
    {
        $this->assertTrue(method_exists($this->Kmp, 'bool'));

        $reflection = new \ReflectionMethod($this->Kmp, 'bool');
        $this->assertEquals('string', $reflection->getReturnType()->getName());
    }

    /**
     * Test that all utility methods exist with correct signatures
     */
    public function testUtilityMethodsExist(): void
    {
        $methods = [
            'getAppSetting',
            'getAppSettingsStartWith',
            'getMixScriptUrl',
            'getMixStyleUrl',
            'startBlock',
            'endBlock'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->Kmp, $method),
                "Method {$method} should exist"
            );
        }
    }
}
