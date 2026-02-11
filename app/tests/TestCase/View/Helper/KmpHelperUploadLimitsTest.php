<?php

declare(strict_types=1);

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\KmpHelper;
use App\Test\TestCase\BaseTestCase;
use Cake\View\View;

/**
 * App\View\Helper\KmpHelper Test Case - Upload Limits
 */
class KmpHelperUploadLimitsTest extends BaseTestCase
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
     * Test getUploadLimits method returns expected structure
     *
     * @return void
     */
    public function testGetUploadLimitsStructure(): void
    {
        $limits = $this->Kmp->getUploadLimits();

        $this->assertIsArray($limits);
        $this->assertArrayHasKey('maxFileSize', $limits);
        $this->assertArrayHasKey('formatted', $limits);
        $this->assertArrayHasKey('uploadMaxFilesize', $limits);
        $this->assertArrayHasKey('postMaxSize', $limits);
    }

    /**
     * Test that maxFileSize is an integer
     *
     * @return void
     */
    public function testMaxFileSizeIsInteger(): void
    {
        $limits = $this->Kmp->getUploadLimits();

        $this->assertIsInt($limits['maxFileSize']);
        $this->assertGreaterThan(0, $limits['maxFileSize']);
    }

    /**
     * Test that formatted is a string
     *
     * @return void
     */
    public function testFormattedIsString(): void
    {
        $limits = $this->Kmp->getUploadLimits();

        $this->assertIsString($limits['formatted']);
        $this->assertNotEmpty($limits['formatted']);

        // Should end with B, KB, MB, or GB
        $this->assertMatchesRegularExpression('/\d+(B|KB|MB|GB)$/', $limits['formatted']);
    }

    /**
     * Test that maxFileSize is the minimum of upload_max_filesize and post_max_size
     *
     * @return void
     */
    public function testMaxFileSizeIsMinimum(): void
    {
        $limits = $this->Kmp->getUploadLimits();

        $this->assertEquals(
            min($limits['uploadMaxFilesize'], $limits['postMaxSize']),
            $limits['maxFileSize']
        );
    }

    /**
     * Test that upload limits are positive numbers
     *
     * @return void
     */
    public function testUploadLimitsArePositive(): void
    {
        $limits = $this->Kmp->getUploadLimits();

        $this->assertGreaterThan(0, $limits['maxFileSize'], 'maxFileSize should be positive');
        $this->assertGreaterThan(0, $limits['uploadMaxFilesize'], 'uploadMaxFilesize should be positive');
        $this->assertGreaterThan(0, $limits['postMaxSize'], 'postMaxSize should be positive');
    }
}
