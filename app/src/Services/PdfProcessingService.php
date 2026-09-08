<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Log\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Handles PDF file operations for waiver uploads.
 *
 * Provides PDF validation, page counting, merging, and thumbnail generation.
 * Uses pure PHP libraries (smalot/pdfparser, setasign/fpdi) with no external dependencies.
 */
class PdfProcessingService
{
    /**
     * Maximum file size for PDF uploads (in bytes)
     * Default: 50MB
     */
    private const MAX_PDF_SIZE = 52428800;

    /**
     * Validate that a file is a valid PDF
     *
     * @param string $filePath Path to the PDF file
     * @return \App\Services\ServiceResult Success with page count in data, or failure with error message
     */
    public function validatePdf(string $filePath): ServiceResult
    {
        return $this->runWorker('validate', [$filePath]);
    }

    /**
     * Get page count from a PDF file
     *
     * @param string $filePath Path to the PDF file
     * @return int Page count, or 0 on error
     */
    public function getPageCount(string $filePath): int
    {
        $result = $this->validatePdf($filePath);
        if (!$result->success) {
            return 0;
        }

        return $result->data['page_count'] ?? 0;
    }

    /**
     * Merge multiple PDF files into one
     *
     * @param array $pdfPaths Array of paths to PDF files
     * @param string $outputPath Path for the merged output PDF
     * @return \App\Services\ServiceResult Success with total page count, or failure with error
     */
    public function mergePdfs(array $pdfInfos, string $outputPath): ServiceResult
    {
        $paths = array_map(
            static fn($info): string => is_string($info) ? $info : (string)($info['path'] ?? ''),
            $pdfInfos,
        );
        $result = $this->runWorker('merge', $paths, $outputPath);
        if (!$result->success && is_file($outputPath)) {
            unlink($outputPath);
        }

        return $result;
    }

    /** Isolate untrusted parsers from the request process and bound memory and wall time. */
    private function runWorker(string $operation, array $paths, ?string $outputPath = null): ServiceResult
    {
        if ($paths === [] || count($paths) > 100) {
            return new ServiceResult(false, $paths === [] ? 'No PDF files provided' : 'Select at most 100 PDF files.');
        }
        $totalBytes = 0;
        foreach ($paths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                return new ServiceResult(false, 'File does not exist or cannot be read.');
            }
            $size = filesize($path);
            if ($size === false || $size < 5 || $size > self::MAX_PDF_SIZE) {
                return new ServiceResult(false, 'A PDF file is empty or exceeds the 50 MB limit.');
            }
            if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
                return new ServiceResult(false, 'File is not a valid PDF');
            }
            $totalBytes += $size;
        }
        if ($totalBytes > 104857600) {
            return new ServiceResult(false, 'The combined PDFs exceed the 100 MB limit.');
        }
        try {
            $process = new Process([
                PHP_BINDIR . '/php', '-d', 'memory_limit=256M', '-d', 'display_errors=0',
                '-d', 'log_errors=0', dirname(__DIR__, 2) . '/bin/pdf-worker.php',
            ]);
            $process->setTimeout(30);
            $process->setInput(json_encode([
                'operation' => $operation, 'paths' => $paths, 'output' => $outputPath,
            ], JSON_THROW_ON_ERROR));
            $process->run();
            $data = json_decode($process->getOutput(), true);
            if (!$process->isSuccessful() || !is_array($data) || ($data['page_count'] ?? 0) < 1) {
                return new ServiceResult(false, 'Unable to process the PDF. Use a supported PDF or upload images.');
            }

            return new ServiceResult(true, null, $data);
        } catch (Throwable) {
            Log::warning('pdf.processing_failed', ['event' => 'pdf.processing_failed']);

            return new ServiceResult(false, 'PDF processing exceeded its limits or could not complete.');
        }
    }

    /**
     * Generate a placeholder thumbnail for a PDF
     *
     * Since we don't have ImageMagick/Ghostscript, we create a simple
     * branded placeholder image showing the page count.
     *
     * @param string $pdfPath Path to the PDF file
     * @param string $outputPath Path for the thumbnail image (PNG)
     * @param int $width Thumbnail width in pixels (default: 200)
     * @param int $height Thumbnail height in pixels (default: 260)
     * @return \App\Services\ServiceResult Success or failure
     */
    public function generateThumbnail(
        string $pdfPath,
        string $outputPath,
        int $width = 200,
        int $height = 260,
    ): ServiceResult {
        $pageCount = $this->getPageCount($pdfPath);
        if ($pageCount === 0) {
            return new ServiceResult(false, 'Unable to read PDF page count');
        }

        // Create a simple placeholder thumbnail using GD
        if (!extension_loaded('gd')) {
            return new ServiceResult(false, 'GD extension required for thumbnail generation');
        }

        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            return new ServiceResult(false, 'Failed to create thumbnail image');
        }

        // Colors
        $bgColor = imagecolorallocate($image, 248, 250, 252); // Light gray
        $borderColor = imagecolorallocate($image, 226, 232, 240); // Slate 200
        $iconColor = imagecolorallocate($image, 100, 116, 139); // Slate 500
        $textColor = imagecolorallocate($image, 51, 65, 85); // Slate 700
        $accentColor = imagecolorallocate($image, 236, 72, 153); // Pink (waiver section color)

        // Fill background
        imagefill($image, 0, 0, $bgColor);

        // Draw border
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);

        // Draw accent stripe at top
        imagefilledrectangle($image, 0, 0, $width - 1, 4, $accentColor);

        // Draw PDF icon (simple rectangle representing document)
        $iconX = ($width - 60) / 2;
        $iconY = 50;
        imagefilledrectangle($image, (int)$iconX, (int)$iconY, (int)($iconX + 60), (int)($iconY + 80), $borderColor);
        imagerectangle($image, (int)$iconX, (int)$iconY, (int)($iconX + 60), (int)($iconY + 80), $iconColor);

        // Draw lines to represent text
        for ($i = 0; $i < 4; $i++) {
            $lineY = $iconY + 20 + ($i * 12);
            $lineWidth = $i === 3 ? 30 : 40;
            imagefilledrectangle(
                $image,
                (int)($iconX + 10),
                (int)$lineY,
                (int)($iconX + 10 + $lineWidth),
                (int)($lineY + 6),
                $iconColor,
            );
        }

        // Draw page count text
        $pageText = $pageCount . ' page' . ($pageCount > 1 ? 's' : '');
        $textWidth = strlen($pageText) * 7; // Approximate width
        $textX = ($width - $textWidth) / 2;
        imagestring($image, 4, (int)$textX, $height - 50, $pageText, $textColor);

        // Draw "PDF" label
        $pdfLabel = 'PDF Document';
        $labelWidth = strlen($pdfLabel) * 6;
        $labelX = ($width - $labelWidth) / 2;
        imagestring($image, 3, (int)$labelX, $height - 30, $pdfLabel, $iconColor);

        // Save as PNG
        $result = imagepng($image, $outputPath);
        imagedestroy($image);

        if (!$result) {
            return new ServiceResult(false, 'Failed to save thumbnail');
        }

        return new ServiceResult(true, null, [
            'thumbnail_path' => $outputPath,
            'page_count' => $pageCount,
        ]);
    }

    /**
     * Check if a file is a PDF based on extension and magic bytes
     *
     * @param string $filePath Path to file
     * @return bool True if file appears to be a PDF
     */
    public function isPdf(string $filePath): bool
    {
        // Check extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            return false;
        }

        // Check magic bytes
        if (!file_exists($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    /**
     * Check if a MIME type indicates a PDF
     *
     * @param string $mimeType MIME type to check
     * @return bool True if MIME type is PDF
     */
    public function isPdfMimeType(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/pdf',
            'application/x-pdf',
        ], true);
    }
}
