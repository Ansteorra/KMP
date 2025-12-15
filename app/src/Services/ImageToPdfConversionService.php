<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Converts uploaded image files to PDF format for consistent document storage.
 * 
 * Uses GD extension for image processing. Supports JPEG, PNG, GIF, BMP, WEBP formats.
 * Maintains aspect ratio and fits images to standard page sizes (Letter, A4).
 * 
 * @see \App\Services\ServiceResult Standard service result pattern
 */
class ImageToPdfConversionService
{
    /**
     * Supported image formats
     * 
     * Note: WEBP support depends on GD library compilation options
     */
    private const SUPPORTED_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'wbmp'];

    /**
     * Convert an image file to PDF format
     *
     * @param string $imagePath Full path to the source image file
     * @param string $outputPath Full path where the PDF should be saved
     * @param string $pageSize Page size: 'letter' or 'a4' (default: letter)
     * @param string|null $previewPath Reference that will receive the path to a generated JPEG preview
     * @return \App\Services\ServiceResult Success/failure with optional error message
     */
    public function convertImageToPdf(string $imagePath, string $outputPath, string $pageSize = 'letter', ?string &$previewPath = null): ServiceResult
    {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            return new ServiceResult(false, 'GD extension is not available for image processing');
        }

        $previewPath = null;

        // Validate and get image info
        $imageInfo = $this->validateAndGetImageInfo($imagePath, false);
        if (!$imageInfo['success']) {
            return new ServiceResult(false, $imageInfo['error']);
        }

        $width = $imageInfo['width'];
        $height = $imageInfo['height'];
        $type = $imageInfo['type'];

        // Load the image based on type
        $image = $this->loadImage($imagePath, $type);
        if ($image === false) {
            return new ServiceResult(false, 'Failed to load image file');
        }

        $orientation = $this->determinePageOrientation($width, $height);
        [$pageWidth, $pageHeight] = $this->getPageDimensions($pageSize, $orientation);

        try {
            // Create PDF using simple format (since FPDF may not be installed)
            $result = $this->createSimplePdf($image, $width, $height, $outputPath, $pageWidth, $pageHeight, $previewPath);
            unset($image);

            return $result;
        } catch (\Exception $e) {
            unset($image);
            Log::error('PDF conversion error: ' . $e->getMessage());
            return new ServiceResult(false, 'Error during PDF conversion: ' . $e->getMessage());
        }
    }

    /**
     * Convert multiple image files into a single multi-page PDF
     *
     * @param array $imagePaths Array of full paths to source image files
     * @param string $outputPath Full path where the PDF should be saved
     * @param string $pageSize Page size: 'letter' or 'a4' (default: letter)
     * @param string|null $previewPath Reference that will receive the path to a generated JPEG preview for the first page
     * @return \App\Services\ServiceResult Success/failure with optional error message
     */
    public function convertMultipleImagesToPdf(array $imagePaths, string $outputPath, string $pageSize = 'letter', ?string &$previewPath = null): ServiceResult
    {
        if (empty($imagePaths)) {
            return new ServiceResult(false, 'No images provided');
        }

        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            return new ServiceResult(false, 'GD extension is not available for image processing');
        }

        $processedImages = [];
        $jpegDataArray = [];
        $firstPageJpegData = null;

        try {
            // Process each image
            foreach ($imagePaths as $imagePath) {
                // Validate and get image info (throws exception on failure)
                $imageInfo = $this->validateAndGetImageInfo($imagePath, true);

                $width = $imageInfo['width'];
                $height = $imageInfo['height'];
                $type = $imageInfo['type'];

                // Load the image
                $image = $this->loadImage($imagePath, $type);
                if ($image === false) {
                    throw new \Exception("Failed to load image: $imagePath");
                }

                $processedImages[] = $image;

                $orientation = $this->determinePageOrientation($width, $height);
                [$pageWidth, $pageHeight] = $this->getPageDimensions($pageSize, $orientation);

                // Process image (resize and convert to grayscale)
                $result = $this->processImageForPdf($image, $width, $height, $pageWidth, $pageHeight);
                if (!$result['success']) {
                    throw new \Exception($result['error']);
                }

                $result['page_width'] = $pageWidth;
                $result['page_height'] = $pageHeight;
                $jpegDataArray[] = [
                    'data' => $result['jpeg_data'],
                    'size' => $result['jpeg_size'],
                    'jpeg_width' => $result['jpeg_width'],       // Actual JPEG pixel dimensions
                    'jpeg_height' => $result['jpeg_height'],
                    'display_width' => $result['display_width'],   // Display size in points
                    'display_height' => $result['display_height'],
                    'page_width' => $result['page_width'],
                    'page_height' => $result['page_height'],
                ];

                if ($firstPageJpegData === null) {
                    $firstPageJpegData = $result['jpeg_data'];
                }
            }

            // Create multi-page PDF with per-page dimensions
            $pdf = $this->buildMultiPagePdfStructure($jpegDataArray);

            // Write PDF file
            if (file_put_contents($outputPath, $pdf) === false) {
                throw new \Exception('Failed to write PDF file');
            }

            // Clean up image resources
            foreach ($processedImages as $image) {
                unset($image);
            }

            $previewPath = $this->createPreviewFromJpegData($firstPageJpegData);

            return new ServiceResult(true, 'Images successfully converted to multi-page PDF', $outputPath);
        } catch (\Exception $e) {
            // Clean up any loaded images on error
            foreach ($processedImages as $image) {
                unset($image);
            }

            $previewPath = $this->createPreviewFromJpegData($firstPageJpegData);

            Log::error('Multi-image PDF conversion error: ' . $e->getMessage());
            return new ServiceResult(false, 'Error during PDF conversion: ' . $e->getMessage());
        }
    }

    /**
     * Persist first-page JPEG data into a temporary preview file.
     *
     * @param string|null $jpegData Binary JPEG data or null when unavailable
     * @return string|null Path to temporary preview file (caller responsible for cleanup)
     */
    private function createPreviewFromJpegData(?string $jpegData): ?string
    {
        if ($jpegData === null) {
            return null;
        }

        $previewTemp = tempnam(sys_get_temp_dir(), 'waiver_preview_multi_');
        if ($previewTemp === false) {
            return null;
        }

        $previewTarget = $previewTemp . '.jpg';
        @rename($previewTemp, $previewTarget);

        if (file_put_contents($previewTarget, $jpegData) !== false) {
            return $previewTarget;
        }

        @unlink($previewTarget);

        return null;
    }

    /**
     * Validate and get information about an image file
     * 
     * Performs comprehensive validation including:
     * - File existence check
     * - Image format detection
     * - Format support verification
     * - Debug file saving on failure
     * 
     * @param string $imagePath Path to image file
     * @param bool $throwException If true, throws exception on failure; if false, returns error in array
     * @return array{success: bool, width?: int, height?: int, type?: int, error?: string}
     * @throws \Exception if validation fails and $throwException is true
     */
    private function validateAndGetImageInfo(string $imagePath, bool $throwException = false): array
    {
        // Validate input file exists
        if (!file_exists($imagePath)) {
            $error = "Image file not found: $imagePath";
            if ($throwException) {
                throw new \Exception($error);
            }
            return ['success' => false, 'error' => $error];
        }

        // Get image info
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            // Save the failing file for debugging
            //$debugPath = TMP . 'failed_image_' . date('Y-m-d_His') . '_' . basename($imagePath);
            //copy($imagePath, $debugPath);

            // Try to determine if it's an SVG or other unsupported format
            $fileContent = file_get_contents($imagePath, false, null, 0, 1024);
            $fileHeader = bin2hex(substr($fileContent, 0, 16));

            // Log detailed debug info
            Log::error('Failed to read image file', [
                'path' => $imagePath,
                //'saved_to' => $debugPath,
                'file_size' => filesize($imagePath),
                'file_header_hex' => $fileHeader,
                'file_header_text' => substr($fileContent, 0, 64),
                'mime_type' => mime_content_type($imagePath),
            ]);

            if (strpos($fileContent, '<svg') !== false || strpos($fileContent, '<?xml') !== false) {
                $error = 'SVG files are not supported. Please upload raster images only (JPEG, PNG, GIF, BMP, WEBP, WBMP)';
            } else {
                $error = 'Unable to read image file. Supported formats: JPEG, PNG, GIF, BMP, WEBP, WBMP (raster images only)';
            }

            if ($throwException) {
                throw new \Exception($error);
            }
            return ['success' => false, 'error' => $error];
        }

        [$width, $height, $type] = $imageInfo;

        // Build list of supported IMAGETYPE constants
        $supportedTypes = [
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG,
            IMAGETYPE_GIF,
            IMAGETYPE_BMP,
            IMAGETYPE_WBMP,
        ];

        // Add optional formats if available in this PHP build
        if (defined('IMAGETYPE_WEBP')) {
            $supportedTypes[] = IMAGETYPE_WEBP;
        }

        // Validate image type
        if (!in_array($type, $supportedTypes)) {
            $error = 'Unsupported image format. Supported formats: JPEG, PNG, GIF, BMP, WEBP, WBMP';
            if ($throwException) {
                throw new \Exception($error);
            }
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => true,
            'width' => $width,
            'height' => $height,
            'type' => $type,
        ];
    }

    /**
     * Load an image from file based on its type
     *
     * @param string $path Path to image
     * @param int $type Image type constant
     * @return \GdImage|false Image resource or false on failure
     */
    private function loadImage(string $path, int $type): \GdImage|false
    {
        // Handle basic formats with match
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_BMP => @imagecreatefrombmp($path),
            IMAGETYPE_WBMP => @imagecreatefromwbmp($path),
            default => false,
        };

        // Return if we got an image from the match
        if ($image !== false) {
            return $image;
        }

        // Handle optional formats that may not be defined in all PHP builds
        if (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) {
            return @imagecreatefromwebp($path);
        }

        return false;
    }

    /**
     * Create a simple PDF with embedded JPEG
     *
     * Creates a basic PDF structure with the image embedded as JPEG.
     * This is a minimal implementation that works without external libraries.
     *
     * @param \GdImage $image GD image resource
     * @param int $width Image width
     * @param int $height Image height
     * @param string $outputPath Output PDF path
     * @param int $pageWidth Page width in points
     * @param int $pageHeight Page height in points
     * @param string|null $previewPath Output parameter that receives a temporary JPEG path
     * @return \App\Services\ServiceResult
     */
    private function createSimplePdf(\GdImage $image, int $width, int $height, string $outputPath, int $pageWidth, int $pageHeight, ?string &$previewPath = null): ServiceResult
    {
        $previewPath = null;
        [$imgWidth, $imgHeight] = $this->calculateFitDimensions($width, $height, $pageWidth, $pageHeight);

        // Create a new image with the fitted dimensions
        $resizedImage = imagecreatetruecolor($imgWidth, $imgHeight);
        if ($resizedImage === false) {
            return new ServiceResult(false, 'Failed to create resized image');
        }

        // Fill with white background
        $white = imagecolorallocate($resizedImage, 255, 255, 255);
        imagefill($resizedImage, 0, 0, $white);

        // Resize the original image to fit
        if (!imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $imgWidth, $imgHeight, $width, $height)) {
            imagedestroy($resizedImage);
            return new ServiceResult(false, 'Failed to resize image');
        }

        // Convert to grayscale (black and white)
        if (!imagefilter($resizedImage, IMG_FILTER_GRAYSCALE)) {
            imagedestroy($resizedImage);
            return new ServiceResult(false, 'Failed to convert to grayscale');
        }

        // Increase contrast for better black and white effect
        imagefilter($resizedImage, IMG_FILTER_CONTRAST, -30);

        // Create a temporary JPEG file with the processed image
        $tempJpeg = tempnam(sys_get_temp_dir(), 'img2pdf_') . '.jpg';

        // Save with lower quality since it's black and white
        if (!imagejpeg($resizedImage, $tempJpeg, 70)) {
            imagedestroy($resizedImage);
            return new ServiceResult(false, 'Failed to create temporary JPEG file');
        }

        imagedestroy($resizedImage);

        $previewCopy = tempnam(sys_get_temp_dir(), 'waiver_preview_');
        if ($previewCopy !== false) {
            $previewCopyJpg = $previewCopy . '.jpg';
            @rename($previewCopy, $previewCopyJpg);
            if (@copy($tempJpeg, $previewCopyJpg)) {
                $previewPath = $previewCopyJpg;
            } else {
                @unlink($previewCopyJpg);
            }
        }

        // Read JPEG data
        $jpegData = file_get_contents($tempJpeg);
        $jpegSize = filesize($tempJpeg);

        // Get actual JPEG dimensions for the XObject declaration
        $jpegInfo = @getimagesize($tempJpeg);
        $jpegWidth = $imgWidth;  // Default to fitted dimensions
        $jpegHeight = $imgHeight;
        if ($jpegInfo !== false) {
            $jpegWidth = $jpegInfo[0];
            $jpegHeight = $jpegInfo[1];
        }
        unlink($tempJpeg);

        // Create minimal PDF structure
        // Pass JPEG pixel dimensions for XObject, and fitted dimensions for display size
        $pdf = $this->buildPdfStructure($jpegData, $jpegSize, $jpegWidth, $jpegHeight, $imgWidth, $imgHeight, $pageWidth, $pageHeight);

        // Write PDF file
        if (file_put_contents($outputPath, $pdf) === false) {
            return new ServiceResult(false, 'Failed to write PDF file');
        }

        return new ServiceResult(true, 'Image successfully converted to PDF', $outputPath);
    }

    /**
     * Get page dimensions based on size name
     *
     * @param string $pageSize Page size name
     * @return string [width, height] in points
     */
    private function determinePageOrientation(int $width, int $height): string
    {
        if ($height <= 0) {
            return 'portrait';
        }

        $ratio = $width / $height;

        if ($ratio >= 1.15) {
            return 'landscape';
        }

        return 'portrait';
    }

    private function getPageDimensions(string $pageSize, string $orientation = 'portrait'): array
    {
        [$width, $height] = match (strtolower($pageSize)) {
            'a4' => [595, 842],      // A4 in points (210 x 297 mm)
            'letter' => [612, 792],  // US Letter in points (8.5 x 11 in)
            default => [612, 792],
        };

        if ($orientation === 'landscape') {
            return [$height, $width];
        }

        return [$width, $height];
    }

    /**
     * Calculate dimensions to fit image on page while maintaining aspect ratio
     *
     * @param int $imgWidth Image width
     * @param int $imgHeight Image height
     * @param int $pageWidth Page width
     * @param int $pageHeight Page height
     * @return array [fitted width, fitted height]
     */
    private function calculateFitDimensions(int $imgWidth, int $imgHeight, int $pageWidth, int $pageHeight): array
    {
        $margin = 36; // 0.5 inch margin in points
        $maxWidth = $pageWidth - ($margin * 2);
        $maxHeight = $pageHeight - ($margin * 2);

        $ratio = min($maxWidth / $imgWidth, $maxHeight / $imgHeight);

        $fittedWidth = (int)($imgWidth * $ratio);
        $fittedHeight = (int)($imgHeight * $ratio);

        return [$fittedWidth, $fittedHeight];
    }

    /**
     * Build minimal PDF structure with embedded JPEG
     *
     * @param string $jpegData JPEG binary data
     * @param int $jpegSize JPEG file size
     * @param int $jpegWidth Actual JPEG pixel width (for XObject)
     * @param int $jpegHeight Actual JPEG pixel height (for XObject)
     * @param int $displayWidth Display width in points (for transformation matrix)
     * @param int $displayHeight Display height in points (for transformation matrix)
     * @param int $pageWidth Page width
     * @param int $pageHeight Page height
     * @return string PDF content
     */
    private function buildPdfStructure(string $jpegData, int $jpegSize, int $jpegWidth, int $jpegHeight, int $displayWidth, int $displayHeight, int $pageWidth, int $pageHeight): string
    {
        // Calculate position to center image based on display dimensions
        $x = ($pageWidth - $displayWidth) / 2;
        $y = ($pageHeight - $displayHeight) / 2;

        // Build PDF objects
        $objects = [];

        // Object 1: Catalog
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Object 2: Pages
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // Object 3: Page
        $objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /XObject << /Im1 4 0 R >> >> /MediaBox [0 0 $pageWidth $pageHeight] /Contents 5 0 R >>\nendobj\n";

        // Object 4: Image (using DeviceRGB since GD saves grayscale images as RGB JPEGs)
        // Width/Height should match actual JPEG pixel dimensions
        $objects[4] = "4 0 obj\n<< /Type /XObject /Subtype /Image /Width $jpegWidth /Height $jpegHeight /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length $jpegSize >>\nstream\n$jpegData\nendstream\nendobj\n";

        // Object 5: Content stream
        // In PDF, images are 1x1 unit squares that need to be scaled and positioned
        // Transformation matrix [a b c d e f] where:
        // a = horizontal scaling (display width), d = vertical scaling (display height)
        // e = horizontal position, f = vertical position
        $stream = "q\n$displayWidth 0 0 $displayHeight $x $y cm\n/Im1 Do\nQ\n";
        $streamLength = strlen($stream);
        $objects[5] = "5 0 obj\n<< /Length $streamLength >>\nstream\n$stream\nendstream\nendobj\n";

        // Sort objects by key to ensure they're written in numerical order
        ksort($objects);

        // Build cross-reference table
        $xref = "xref\n0 6\n0000000000 65535 f \n";
        $offset = strlen("%PDF-1.4\n");

        for ($i = 1; $i <= 5; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
            $offset += strlen($objects[$i]);
        }

        // Build trailer
        $trailer = "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$offset\n%%EOF\n";

        // Assemble PDF
        return "%PDF-1.4\n" . implode('', $objects) . $xref . $trailer;
    }

    /**
     * Process image for PDF (resize and convert to grayscale)
     *
     * @param \GdImage $image GD image resource
     * @param int $width Original width
     * @param int $height Original height
     * @param int $pageWidth Page width in points
     * @param int $pageHeight Page height in points
     * @return array Result with jpeg_data, jpeg_size, width, height
     */
    private function processImageForPdf(\GdImage $image, int $width, int $height, int $pageWidth, int $pageHeight): array
    {
        // Get actual dimensions from the image resource
        $actualWidth = imagesx($image);
        $actualHeight = imagesy($image);

        [$displayWidth, $displayHeight] = $this->calculateFitDimensions($width, $height, $pageWidth, $pageHeight);

        // Create a new image with the fitted dimensions
        $resizedImage = imagecreatetruecolor($displayWidth, $displayHeight);
        if ($resizedImage === false) {
            return ['success' => false, 'error' => 'Failed to create resized image'];
        }

        // Fill with white background
        $white = imagecolorallocate($resizedImage, 255, 255, 255);
        imagefill($resizedImage, 0, 0, $white);

        // Resize the original image to fit - use ACTUAL resource dimensions
        if (!imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $displayWidth, $displayHeight, $actualWidth, $actualHeight)) {
            imagedestroy($resizedImage);
            return ['success' => false, 'error' => 'Failed to resize image'];
        }

        // Convert to grayscale (black and white)
        if (!imagefilter($resizedImage, IMG_FILTER_GRAYSCALE)) {
            imagedestroy($resizedImage);
            return ['success' => false, 'error' => 'Failed to convert to grayscale'];
        }

        // Increase contrast for better black and white effect
        imagefilter($resizedImage, IMG_FILTER_CONTRAST, -30);

        // Create temporary JPEG
        $tempJpeg = tempnam(sys_get_temp_dir(), 'waiver_') . '.jpg';
        if (!imagejpeg($resizedImage, $tempJpeg, 70)) {
            imagedestroy($resizedImage);
            return ['success' => false, 'error' => 'Failed to create JPEG'];
        }

        $jpegData = file_get_contents($tempJpeg);
        $jpegSize = filesize($tempJpeg);

        // Get actual JPEG pixel dimensions (may differ slightly from display dimensions due to GD processing)
        $jpegInfo = @getimagesize($tempJpeg);
        $jpegWidth = $displayWidth;
        $jpegHeight = $displayHeight;
        if ($jpegInfo !== false) {
            $jpegWidth = $jpegInfo[0];
            $jpegHeight = $jpegInfo[1];
        }

        unlink($tempJpeg);
        imagedestroy($resizedImage);

        return [
            'success' => true,
            'jpeg_data' => $jpegData,
            'jpeg_size' => $jpegSize,
            'jpeg_width' => $jpegWidth,       // Actual JPEG pixel dimensions
            'jpeg_height' => $jpegHeight,
            'display_width' => $displayWidth,   // Intended display size in points
            'display_height' => $displayHeight,
        ];
    }

    /**
     * Build multi-page PDF structure
     *
     * @param array $jpegDataArray Array of page data (with per-page width/height)
     * @return string PDF content
     */
    private function buildMultiPagePdfStructure(array $jpegDataArray): string
    {
        $numPages = count($jpegDataArray);
        $objects = [];
        $objNum = 1;

        // Object 1: Catalog
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objNum++;

        // Object 2: Pages (will be built after we know all page object numbers)
        $pageObjectNumbers = [];
        $objNum++; // Reserve for pages object

        // Create objects for each page
        $imageObjects = [];
        $contentObjects = [];

        foreach ($jpegDataArray as $index => $pageData) {
            $pageNum = $objNum++;
            $imageNum = $objNum++;
            $contentNum = $objNum++;

            $pageObjectNumbers[] = "$pageNum 0 R";
            $imageObjects[$pageNum] = $imageNum;
            $contentObjects[$pageNum] = $contentNum;

            $pageWidth = $pageData['page_width'];
            $pageHeight = $pageData['page_height'];
            // Calculate position to center image using display dimensions
            $x = ($pageWidth - $pageData['display_width']) / 2;
            $y = ($pageHeight - $pageData['display_height']) / 2;

            // Page object
            $objects[$pageNum] = "$pageNum 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /XObject << /Im$pageNum $imageNum 0 R >> >> /MediaBox [0 0 $pageWidth $pageHeight] /Contents $contentNum 0 R >>\nendobj\n";

            // Image object (using DeviceRGB since GD saves grayscale images as RGB JPEGs)
            // Width/Height should match actual JPEG pixel dimensions, not display dimensions
            $objects[$imageNum] = "$imageNum 0 obj\n<< /Type /XObject /Subtype /Image /Width {$pageData['jpeg_width']} /Height {$pageData['jpeg_height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$pageData['size']} >>\nstream\n{$pageData['data']}\nendstream\nendobj\n";

            // Content stream
            // In PDF, images are 1x1 unit squares that need to be scaled and positioned
            // Transformation matrix: a = horizontal scaling (display width), d = vertical scaling (display height)
            $stream = "q\n{$pageData['display_width']} 0 0 {$pageData['display_height']} $x $y cm\n/Im$pageNum Do\nQ\n";
            $streamLength = strlen($stream);

            $objects[$contentNum] = "$contentNum 0 obj\n<< /Length $streamLength >>\nstream\n$stream\nendstream\nendobj\n";
        }

        // Now create the Pages object with all page references
        $pagesKids = implode(' ', $pageObjectNumbers);
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [$pagesKids] /Count $numPages >>\nendobj\n";

        // Sort objects by key to ensure they're written in numerical order
        ksort($objects);

        // Build cross-reference table
        $xref = "xref\n0 " . ($objNum) . "\n0000000000 65535 f \n";
        $offset = strlen("%PDF-1.4\n");

        for ($i = 1; $i < $objNum; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
            $offset += strlen($objects[$i]);
        }

        // Build trailer
        $trailer = "trailer\n<< /Size $objNum /Root 1 0 R >>\nstartxref\n$offset\n%%EOF\n";

        // Assemble PDF
        return "%PDF-1.4\n" . implode('', $objects) . $xref . $trailer;
    }
}
