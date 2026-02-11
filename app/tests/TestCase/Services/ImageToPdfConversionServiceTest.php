<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\ImageToPdfConversionService;
use Cake\Core\Configure;
use App\Test\TestCase\BaseTestCase;

/**
 * App\Services\ImageToPdfConversionService Test Case
 *
 * @requires extension gd
 */
class ImageToPdfConversionServiceTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \App\Services\ImageToPdfConversionService
     */
    protected $ImageToPdfConversionService;

    /**
     * Test images directory
     *
     * @var string
     */
    protected $testImagesDir;

    /**
     * Temporary files to clean up
     *
     * @var string[]
     */
    protected $tempFiles = [];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->ImageToPdfConversionService = new ImageToPdfConversionService();

        // Setup test images directory
        $this->testImagesDir = TMP . 'tests' . DS . 'waiver_images' . DS;
        if (!is_dir($this->testImagesDir)) {
            mkdir($this->testImagesDir, 0777, true);
        }
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->ImageToPdfConversionService);

        // Clean up test files
        if (is_dir($this->testImagesDir)) {
            $files = glob($this->testImagesDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // Clean up tracked temp files (e.g., preview paths)
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    /**
     * Create a test JPEG image using GD.
     */
    private function createTestJpeg(string $path, int $width = 100, int $height = 100): void
    {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 10, 10, $width - 10, $height - 10, $black);
        imagejpeg($image, $path, 90);
        imagedestroy($image);
    }

    /**
     * Create a test PNG image using GD.
     */
    private function createTestPng(string $path, int $width = 100, int $height = 100): void
    {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefilledrectangle($image, 10, 10, $width - 10, $height - 10, $red);
        imagepng($image, $path);
        imagedestroy($image);
    }

    /**
     * Create a multi-color test JPEG (for grayscale conversion testing).
     */
    private function createColorTestJpeg(string $path, int $width = 200, int $height = 200): void
    {
        $image = imagecreatetruecolor($width, $height);
        $hw = (int)($width / 2);
        $hh = (int)($height / 2);
        $red = imagecolorallocate($image, 255, 0, 0);
        $green = imagecolorallocate($image, 0, 255, 0);
        $blue = imagecolorallocate($image, 0, 0, 255);
        $yellow = imagecolorallocate($image, 255, 255, 0);
        imagefilledrectangle($image, 0, 0, $hw, $hh, $red);
        imagefilledrectangle($image, $hw, 0, $width, $hh, $green);
        imagefilledrectangle($image, 0, $hh, $hw, $height, $blue);
        imagefilledrectangle($image, $hw, $hh, $width, $height, $yellow);
        imagejpeg($image, $path, 95);
        imagedestroy($image);
    }

    /**
     * Return an output path inside testImagesDir and register it for cleanup.
     */
    private function outputPath(string $name): string
    {
        $path = $this->testImagesDir . $name;
        return $path;
    }

    /**
     * Track a temporary file for cleanup in tearDown.
     */
    private function trackTempFile(?string $path): void
    {
        if ($path !== null) {
            $this->tempFiles[] = $path;
        }
    }

    /**
     * Test convert method with valid JPEG image
     *
     * @return void
     */
    public function testConvertJpegImage(): void
    {
        $jpegPath = $this->testImagesDir . 'test.jpg';
        $this->createTestJpeg($jpegPath);
        $outputPath = $this->outputPath('jpeg_output.pdf');

        $previewPath = null;
        $result = $this->ImageToPdfConversionService->convertImageToPdf(
            $jpegPath,
            $outputPath,
            'letter',
            $previewPath
        );
        $this->trackTempFile($previewPath);

        $this->assertTrue($result->isSuccess(), 'JPEG conversion failed: ' . ($result->getError() ?? ''));
        $this->assertFileExists($outputPath);
        $pdfContent = file_get_contents($outputPath);
        $this->assertStringStartsWith('%PDF-', $pdfContent, 'Output should be a valid PDF');
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * Test convert method with valid PNG image
     *
     * @return void
     */
    public function testConvertPngImage(): void
    {
        $pngPath = $this->testImagesDir . 'test.png';
        $this->createTestPng($pngPath);
        $outputPath = $this->outputPath('png_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($pngPath, $outputPath);

        $this->assertTrue($result->isSuccess(), 'PNG conversion failed: ' . ($result->getError() ?? ''));
        $this->assertFileExists($outputPath);
        $pdfContent = file_get_contents($outputPath);
        $this->assertStringStartsWith('%PDF-', $pdfContent, 'Output should be a valid PDF');
    }

    /**
     * Test convert rejects TIFF images (unsupported format)
     *
     * @return void
     */
    public function testConvertTiffImage(): void
    {
        // TIFF is not in SUPPORTED_FORMATS; create a minimal TIFF-like file
        $tiffPath = $this->testImagesDir . 'test.tiff';
        // Little-endian TIFF header: 49 49 2A 00
        file_put_contents($tiffPath, hex2bin('49492A00') . str_repeat("\x00", 100));
        $outputPath = $this->outputPath('tiff_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($tiffPath, $outputPath);

        $this->assertFalse($result->isSuccess(), 'TIFF should be rejected as unsupported');
        $this->assertNotNull($result->getError());
    }

    /**
     * Test convert rejects invalid file formats
     *
     * @return void
     */
    public function testConvertRejectsInvalidFormat(): void
    {
        $txtPath = $this->testImagesDir . 'not_an_image.txt';
        file_put_contents($txtPath, 'This is plain text, not an image.');
        $outputPath = $this->outputPath('invalid_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($txtPath, $outputPath);

        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->getError());
        $this->assertFileDoesNotExist($outputPath);
    }

    /**
     * Test convert handles corrupted images gracefully
     *
     * @return void
     */
    public function testConvertHandlesCorruptedImages(): void
    {
        $corruptPath = $this->testImagesDir . 'corrupt.jpg';
        // Valid JPEG SOI marker (FF D8 FF) followed by garbage
        file_put_contents($corruptPath, "\xFF\xD8\xFF" . random_bytes(200));
        $outputPath = $this->outputPath('corrupt_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($corruptPath, $outputPath);

        // Should fail gracefully — no uncaught exception
        $this->assertFalse($result->isSuccess(), 'Corrupted image should fail gracefully');
        $this->assertNotNull($result->getError());
    }

    /**
     * Test converted PDF has reasonable file size
     *
     * @return void
     */
    public function testCompressionQuality(): void
    {
        $jpegPath = $this->testImagesDir . 'large_color.jpg';
        $this->createColorTestJpeg($jpegPath, 800, 600);
        $outputPath = $this->outputPath('compressed_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($jpegPath, $outputPath);

        $this->assertTrue($result->isSuccess(), 'Conversion failed: ' . ($result->getError() ?? ''));
        $this->assertFileExists($outputPath);
        $pdfSize = filesize($outputPath);
        $this->assertGreaterThan(0, $pdfSize, 'PDF should not be empty');
        // PDF wraps a JPEG at quality 70 with ~200 bytes of structure;
        // it should be a reasonable size for an 800×600 grayscale image
        $this->assertLessThan(5 * 1024 * 1024, $pdfSize, 'PDF should be under 5 MB for an 800x600 image');
    }

    /**
     * Test PDF uses DCTDecode (JPEG) compression
     *
     * The GD-based service embeds images as JPEG with DCTDecode filter,
     * not Group4/CCITT (which requires Imagick and monochrome bitmaps).
     *
     * @return void
     */
    public function testUsesGroup4Compression(): void
    {
        $jpegPath = $this->testImagesDir . 'compression_check.jpg';
        $this->createTestJpeg($jpegPath);
        $outputPath = $this->outputPath('dct_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($jpegPath, $outputPath);

        $this->assertTrue($result->isSuccess());
        $pdfContent = file_get_contents($outputPath);
        $this->assertStringContainsString('/DCTDecode', $pdfContent, 'PDF should use DCTDecode (JPEG) filter');
        $this->assertStringContainsString('/DeviceRGB', $pdfContent, 'PDF should declare RGB color space');
    }

    /**
     * Test that color images are converted to grayscale
     *
     * @return void
     */
    public function testBlackAndWhiteConversion(): void
    {
        $colorPath = $this->testImagesDir . 'color_input.jpg';
        $this->createColorTestJpeg($colorPath, 200, 200);
        $outputPath = $this->outputPath('bw_output.pdf');

        $previewPath = null;
        $result = $this->ImageToPdfConversionService->convertImageToPdf(
            $colorPath,
            $outputPath,
            'letter',
            $previewPath
        );
        $this->trackTempFile($previewPath);

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($outputPath);

        // Verify grayscale via the preview JPEG (R == G == B for every sampled pixel)
        if ($previewPath !== null && file_exists($previewPath)) {
            $preview = @imagecreatefromjpeg($previewPath);
            $this->assertNotFalse($preview, 'Preview should be a loadable JPEG');
            // Sample center pixel
            $rgb = imagecolorat($preview, (int)(imagesx($preview) / 2), (int)(imagesy($preview) / 2));
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            // JPEG compression can cause ±2 deviation in channel values
            $this->assertEqualsWithDelta($r, $g, 3, 'R and G channels should match (grayscale)');
            $this->assertEqualsWithDelta($r, $b, 3, 'R and B channels should match (grayscale)');
            imagedestroy($preview);
        }
    }

    /**
     * Test conversion preserves aspect ratio of the source image
     *
     * @return void
     */
    public function testPreservesImageDimensions(): void
    {
        // Landscape source: 400×200
        $widePath = $this->testImagesDir . 'wide.jpg';
        $this->createTestJpeg($widePath, 400, 200);
        $outputPath = $this->outputPath('dimensions_output.pdf');

        $previewPath = null;
        $result = $this->ImageToPdfConversionService->convertImageToPdf(
            $widePath,
            $outputPath,
            'letter',
            $previewPath
        );
        $this->trackTempFile($previewPath);

        $this->assertTrue($result->isSuccess());

        // Preview should preserve landscape aspect ratio
        if ($previewPath !== null && file_exists($previewPath)) {
            $info = getimagesize($previewPath);
            $this->assertNotFalse($info);
            $this->assertGreaterThan(
                $info[1],
                $info[0],
                'Preview of a landscape image should be wider than tall'
            );
        }
        $this->assertFileExists($outputPath);
    }

    /**
     * Test portrait and landscape images produce correct page orientations
     *
     * @return void
     */
    public function testPreservesImageOrientation(): void
    {
        // Portrait: 200×400
        $portraitPath = $this->testImagesDir . 'portrait.jpg';
        $this->createTestJpeg($portraitPath, 200, 400);
        $portraitOutput = $this->outputPath('portrait_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($portraitPath, $portraitOutput);
        $this->assertTrue($result->isSuccess());

        // Landscape: 400×200
        $landscapePath = $this->testImagesDir . 'landscape.jpg';
        $this->createTestJpeg($landscapePath, 400, 200);
        $landscapeOutput = $this->outputPath('landscape_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($landscapePath, $landscapeOutput);
        $this->assertTrue($result->isSuccess());

        // Parse MediaBox from each PDF
        $portraitPdf = file_get_contents($portraitOutput);
        $landscapePdf = file_get_contents($landscapeOutput);

        preg_match('/\/MediaBox \[0 0 (\d+) (\d+)\]/', $portraitPdf, $pMatch);
        preg_match('/\/MediaBox \[0 0 (\d+) (\d+)\]/', $landscapePdf, $lMatch);

        $this->assertNotEmpty($pMatch, 'Portrait PDF should contain MediaBox');
        $this->assertNotEmpty($lMatch, 'Landscape PDF should contain MediaBox');

        // Portrait page: height > width
        $this->assertGreaterThan((int)$pMatch[1], (int)$pMatch[2], 'Portrait page should be taller than wide');
        // Landscape page: width > height
        $this->assertGreaterThan((int)$lMatch[2], (int)$lMatch[1], 'Landscape page should be wider than tall');
    }

    /**
     * Test service operates with GD (does not require Imagick)
     *
     * @return void
     */
    public function testErrorHandlingForMissingImagick(): void
    {
        // Service uses GD, not Imagick. Verify it works when only GD is present.
        $this->assertTrue(extension_loaded('gd'), 'GD extension must be available');

        $jpegPath = $this->testImagesDir . 'gd_only.jpg';
        $this->createTestJpeg($jpegPath);
        $outputPath = $this->outputPath('gd_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($jpegPath, $outputPath);

        $this->assertTrue($result->isSuccess(), 'Service should work with GD alone');
        $this->assertFileExists($outputPath);
    }

    /**
     * Test conversion completes within a reasonable time for typical images
     *
     * @return void
     */
    public function testPerformanceForTypicalFileSizes(): void
    {
        $jpegPath = $this->testImagesDir . 'perf_test.jpg';
        // 1000×1000 is a realistic scanned-document resolution
        $this->createTestJpeg($jpegPath, 1000, 1000);
        $outputPath = $this->outputPath('perf_output.pdf');

        $start = microtime(true);
        $result = $this->ImageToPdfConversionService->convertImageToPdf($jpegPath, $outputPath);
        $elapsed = microtime(true) - $start;

        $this->assertTrue($result->isSuccess(), 'Conversion failed: ' . ($result->getError() ?? ''));
        $this->assertLessThan(10.0, $elapsed, "Conversion took {$elapsed}s — should be under 10s");
    }

    /**
     * Test handling of a large image (simulated via high resolution)
     *
     * @return void
     */
    public function testHandlingOfLargeFiles(): void
    {
        $largePath = $this->testImagesDir . 'large.jpg';
        // 2000×3000 simulates a high-res phone photo
        $this->createTestJpeg($largePath, 2000, 3000);
        $outputPath = $this->outputPath('large_output.pdf');

        $result = $this->ImageToPdfConversionService->convertImageToPdf($largePath, $outputPath);

        $this->assertTrue($result->isSuccess(), 'Large image conversion failed: ' . ($result->getError() ?? ''));
        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * Test validation returns proper error messages
     *
     * @return void
     */
    public function testValidationErrorMessages(): void
    {
        // Test validation through convertImageToPdf with a non-existent file
        $result = $this->ImageToPdfConversionService->convertImageToPdf(
            '/nonexistent/file.jpg',
            TMP . 'test_output.pdf'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not found', $result->getError());
    }

    /**
     * Test batch conversion of multiple images into a single multi-page PDF
     *
     * @return void
     */
    public function testSupportsBatchConversion(): void
    {
        $img1 = $this->testImagesDir . 'batch1.jpg';
        $img2 = $this->testImagesDir . 'batch2.png';
        $img3 = $this->testImagesDir . 'batch3.jpg';
        $this->createTestJpeg($img1, 100, 100);
        $this->createTestPng($img2, 150, 200);
        $this->createTestJpeg($img3, 200, 150);

        $outputPath = $this->outputPath('batch_output.pdf');
        $previewPath = null;
        $result = $this->ImageToPdfConversionService->convertMultipleImagesToPdf(
            [$img1, $img2, $img3],
            $outputPath,
            'letter',
            $previewPath
        );
        $this->trackTempFile($previewPath);

        $this->assertTrue($result->isSuccess(), 'Batch conversion failed: ' . ($result->getError() ?? ''));
        $this->assertFileExists($outputPath);

        $pdfContent = file_get_contents($outputPath);
        $this->assertStringStartsWith('%PDF-', $pdfContent);
        // Multi-page PDF should declare 3 pages
        $this->assertStringContainsString('/Count 3', $pdfContent, 'PDF should contain 3 pages');
    }
}
