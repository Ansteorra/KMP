<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Log\Log;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;

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
     * @return ServiceResult Success with page count in data, or failure with error message
     */
    public function validatePdf(string $filePath): ServiceResult
    {
        if (!file_exists($filePath)) {
            return new ServiceResult(false, 'File does not exist');
        }

        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_PDF_SIZE) {
            $maxMb = self::MAX_PDF_SIZE / 1024 / 1024;
            return new ServiceResult(false, "PDF file exceeds maximum size of {$maxMb}MB");
        }

        // Check PDF magic bytes
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return new ServiceResult(false, 'Unable to read file');
        }
        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            return new ServiceResult(false, 'File is not a valid PDF');
        }

        // Try to parse and count pages
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            if ($pageCount === 0) {
                return new ServiceResult(false, 'PDF contains no pages');
            }

            return new ServiceResult(true, null, [
                'page_count' => $pageCount,
                'file_size' => $fileSize,
            ]);
        } catch (\Exception $e) {
            Log::error('PDF validation error: ' . $e->getMessage());
            return new ServiceResult(false, 'Unable to read PDF file: ' . $e->getMessage());
        }
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
     * @return ServiceResult Success with total page count, or failure with error
     */
    public function mergePdfs(array $pdfInfos, string $outputPath): ServiceResult
    {
        if (empty($pdfInfos)) {
            return new ServiceResult(false, 'No PDF files provided');
        }

        // Normalize input - support both simple paths and path+name arrays
        $normalizedInfos = [];
        foreach ($pdfInfos as $info) {
            if (is_string($info)) {
                $normalizedInfos[] = ['path' => $info, 'name' => basename($info)];
            } else {
                $normalizedInfos[] = [
                    'path' => $info['path'] ?? '',
                    'name' => $info['name'] ?? basename($info['path'] ?? ''),
                ];
            }
        }

        // If only one PDF, just copy it
        if (count($normalizedInfos) === 1) {
            $path = $normalizedInfos[0]['path'];
            if (!copy($path, $outputPath)) {
                return new ServiceResult(false, 'Failed to copy PDF file');
            }
            $pageCount = $this->getPageCount($outputPath);
            return new ServiceResult(true, null, ['page_count' => $pageCount]);
        }

        try {
            $fpdi = new Fpdi();
            $totalPages = 0;
            $skippedFiles = [];

            foreach ($normalizedInfos as $pdfInfo) {
                $pdfPath = $pdfInfo['path'];
                $pdfName = $pdfInfo['name'];

                if (!file_exists($pdfPath)) {
                    Log::warning("PDF file not found during merge: {$pdfPath}");
                    continue;
                }

                try {
                    $pageCount = $fpdi->setSourceFile($pdfPath);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $fpdi->importPage($pageNo);
                        $size = $fpdi->getTemplateSize($templateId);

                        // Add page with same dimensions as source
                        $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $fpdi->useTemplate($templateId);
                        $totalPages++;
                    }
                } catch (\Exception $e) {
                    // Check if this is the compression/parser error
                    if (strpos($e->getMessage(), 'compression technique') !== false ||
                        strpos($e->getMessage(), 'not supported by the free parser') !== false) {
                        Log::warning("Skipping PDF with unsupported compression: {$pdfName}");
                        $skippedFiles[] = $pdfName;
                        continue;
                    }
                    // Re-throw other errors
                    throw $e;
                }
            }

            if ($totalPages === 0) {
                if (!empty($skippedFiles)) {
                    return new ServiceResult(false, 'Could not process PDF files. Some PDFs use compression not supported by this system. Please try uploading images instead, or use a simpler PDF format.');
                }
                return new ServiceResult(false, 'No pages found in PDF files');
            }

            $fpdi->Output($outputPath, 'F');

            $result = ['page_count' => $totalPages];
            if (!empty($skippedFiles)) {
                $result['skipped_files'] = $skippedFiles;
                $result['warning'] = 'Some PDF files were skipped due to unsupported compression';
            }

            return new ServiceResult(true, null, $result);
        } catch (\Exception $e) {
            Log::error('PDF merge error: ' . $e->getMessage());
            return new ServiceResult(false, 'Failed to merge PDF files: ' . $e->getMessage());
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
     * @return ServiceResult Success or failure
     */
    public function generateThumbnail(string $pdfPath, string $outputPath, int $width = 200, int $height = 260): ServiceResult
    {
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
            $lineWidth = ($i === 3) ? 30 : 40;
            imagefilledrectangle(
                $image,
                (int)($iconX + 10),
                (int)$lineY,
                (int)($iconX + 10 + $lineWidth),
                (int)($lineY + 6),
                $iconColor
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
