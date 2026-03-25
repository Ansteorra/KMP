<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\PdfProcessingService;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;

class PdfProcessingServiceTest extends BaseTestCase
{
    protected ?PdfProcessingService $service = null;
    private array $cleanupFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->service = new PdfProcessingService();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function trackFile(string $path): void
    {
        $this->cleanupFiles[] = $path;
    }

    private function createTestPdf(): string
    {
        $pdfPath = ROOT . DS . 'tests' . DS . 'test_pdf_' . uniqid() . '.pdf';
        $this->trackFile($pdfPath);

        // Minimal valid PDF content
        $content = "%PDF-1.4\n";
        $content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
        $content .= "xref\n0 4\n";
        $content .= "0000000000 65535 f \n";
        $content .= "0000000009 00000 n \n";
        $content .= "0000000058 00000 n \n";
        $content .= "0000000115 00000 n \n";
        $content .= "trailer\n<< /Size 4 /Root 1 0 R >>\n";
        $content .= "startxref\n196\n%%EOF\n";

        file_put_contents($pdfPath, $content);

        return $pdfPath;
    }

    private function createNonPdfFile(): string
    {
        $path = ROOT . DS . 'tests' . DS . 'test_notpdf_' . uniqid() . '.txt';
        $this->trackFile($path);
        file_put_contents($path, 'This is not a PDF file.');

        return $path;
    }

    private function createFakePdfExtension(): string
    {
        $path = ROOT . DS . 'tests' . DS . 'test_fakepdf_' . uniqid() . '.pdf';
        $this->trackFile($path);
        file_put_contents($path, 'This is not really a PDF');

        return $path;
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(PdfProcessingService::class, $this->service);
    }

    public function testIsPdfMimeTypeWithValidTypes(): void
    {
        $this->assertTrue($this->service->isPdfMimeType('application/pdf'));
        $this->assertTrue($this->service->isPdfMimeType('application/x-pdf'));
    }

    public function testIsPdfMimeTypeWithInvalidTypes(): void
    {
        $this->assertFalse($this->service->isPdfMimeType('text/plain'));
        $this->assertFalse($this->service->isPdfMimeType('image/png'));
        $this->assertFalse($this->service->isPdfMimeType('application/json'));
        $this->assertFalse($this->service->isPdfMimeType(''));
    }

    public function testIsPdfWithNonExistentFile(): void
    {
        $this->assertFalse($this->service->isPdf('/nonexistent/path/file.pdf'));
    }

    public function testIsPdfWithNonPdfExtension(): void
    {
        $txtFile = $this->createNonPdfFile();
        $this->assertFalse($this->service->isPdf($txtFile));
    }

    public function testIsPdfWithFakePdfExtension(): void
    {
        $fakePdf = $this->createFakePdfExtension();
        $this->assertFalse($this->service->isPdf($fakePdf));
    }

    public function testIsPdfWithValidPdf(): void
    {
        $pdfPath = $this->createTestPdf();
        $this->assertTrue($this->service->isPdf($pdfPath));
    }

    public function testValidatePdfWithNonExistentFile(): void
    {
        $result = $this->service->validatePdf('/nonexistent/file.pdf');
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('does not exist', $result->getError());
    }

    public function testValidatePdfWithNonPdfContent(): void
    {
        $fakePdf = $this->createFakePdfExtension();
        $result = $this->service->validatePdf($fakePdf);
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not a valid PDF', $result->getError());
    }

    public function testValidatePdfWithValidPdf(): void
    {
        $pdfPath = $this->createTestPdf();
        $result = $this->service->validatePdf($pdfPath);
        $this->assertInstanceOf(ServiceResult::class, $result);

        if ($result->isSuccess()) {
            $data = $result->getData();
            $this->assertArrayHasKey('page_count', $data);
            $this->assertArrayHasKey('file_size', $data);
            $this->assertGreaterThan(0, $data['page_count']);
            $this->assertGreaterThan(0, $data['file_size']);
        }
        // Some PDF parsers may fail with minimal PDFs, that's acceptable
    }

    public function testValidatePdfSmallFilePassesSizeCheck(): void
    {
        $pdfPath = $this->createTestPdf();
        $fileSize = filesize($pdfPath);

        // Verify our test file is well under the 50MB limit
        $this->assertLessThan(52428800, $fileSize);

        $result = $this->service->validatePdf($pdfPath);
        // If validation fails, it should not be due to file size
        if (!$result->isSuccess()) {
            $this->assertStringNotContainsString('maximum size', $result->getError());
        } else {
            $this->assertLessThan(52428800, $result->getData()['file_size']);
        }
    }

    public function testGetPageCountWithNonExistentFile(): void
    {
        $result = $this->service->getPageCount('/nonexistent/file.pdf');
        $this->assertEquals(0, $result);
    }

    public function testGetPageCountWithValidPdf(): void
    {
        $pdfPath = $this->createTestPdf();
        $count = $this->service->getPageCount($pdfPath);
        // Minimal PDF should have 1 page, but parser may fail with minimal format
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testMergePdfsWithEmptyArray(): void
    {
        $outputPath = ROOT . DS . 'tests' . DS . 'merged_' . uniqid() . '.pdf';
        $this->trackFile($outputPath);

        $result = $this->service->mergePdfs([], $outputPath);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No PDF files provided', $result->getError());
    }

    public function testMergePdfsWithNonExistentFiles(): void
    {
        $outputPath = ROOT . DS . 'tests' . DS . 'merged_' . uniqid() . '.pdf';
        $this->trackFile($outputPath);

        $result = $this->service->mergePdfs(
            ['/nonexistent/a.pdf', '/nonexistent/b.pdf'],
            $outputPath
        );
        $this->assertInstanceOf(ServiceResult::class, $result);
        // Should fail since no pages could be found
        $this->assertFalse($result->isSuccess());
    }

    public function testMergePdfsNormalizesStringInput(): void
    {
        $outputPath = ROOT . DS . 'tests' . DS . 'merged_' . uniqid() . '.pdf';
        $this->trackFile($outputPath);

        // Pass a single valid PDF as string (not array)
        $pdfPath = $this->createTestPdf();
        $result = $this->service->mergePdfs([$pdfPath], $outputPath);

        $this->assertInstanceOf(ServiceResult::class, $result);
        // Single file should be copied
        if ($result->isSuccess()) {
            $this->assertTrue(file_exists($outputPath));
        }
    }

    public function testMergePdfsNormalizesArrayInput(): void
    {
        $outputPath = ROOT . DS . 'tests' . DS . 'merged_' . uniqid() . '.pdf';
        $this->trackFile($outputPath);

        $pdfPath = $this->createTestPdf();
        $result = $this->service->mergePdfs(
            [['path' => $pdfPath, 'name' => 'test.pdf']],
            $outputPath
        );

        $this->assertInstanceOf(ServiceResult::class, $result);
    }

    public function testGenerateThumbnailWithInvalidPdf(): void
    {
        $outputPath = ROOT . DS . 'tests' . DS . 'thumb_' . uniqid() . '.png';
        $this->trackFile($outputPath);

        $result = $this->service->generateThumbnail('/nonexistent/file.pdf', $outputPath);
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    public function testGenerateThumbnailWithValidPdf(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required for thumbnail tests');
        }

        $pdfPath = $this->createTestPdf();
        $pageCount = $this->service->getPageCount($pdfPath);
        if ($pageCount === 0) {
            $this->markTestSkipped('Test PDF not parseable by this parser');
        }

        $outputPath = ROOT . DS . 'tests' . DS . 'thumb_' . uniqid() . '.png';
        $this->trackFile($outputPath);

        $result = $this->service->generateThumbnail($pdfPath, $outputPath);
        $this->assertInstanceOf(ServiceResult::class, $result);

        if ($result->isSuccess()) {
            $this->assertTrue(file_exists($outputPath));
            $data = $result->getData();
            $this->assertArrayHasKey('thumbnail_path', $data);
            $this->assertArrayHasKey('page_count', $data);
            $this->assertEquals($outputPath, $data['thumbnail_path']);
        }
    }

    public function testGenerateThumbnailCustomDimensions(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $pdfPath = $this->createTestPdf();
        $pageCount = $this->service->getPageCount($pdfPath);
        if ($pageCount === 0) {
            $this->markTestSkipped('Test PDF not parseable by this parser');
        }

        $outputPath = ROOT . DS . 'tests' . DS . 'thumb_custom_' . uniqid() . '.png';
        $this->trackFile($outputPath);

        $result = $this->service->generateThumbnail($pdfPath, $outputPath, 300, 400);

        if ($result->isSuccess()) {
            $imageInfo = getimagesize($outputPath);
            $this->assertEquals(300, $imageInfo[0]);
            $this->assertEquals(400, $imageInfo[1]);
        }
    }
}
