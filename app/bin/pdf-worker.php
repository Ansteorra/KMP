<?php
declare(strict_types=1);

// Dedicated bounded CLI process. No application bootstrap, sessions, or customer configuration.
require dirname(__DIR__) . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

try {
    $input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
    $config = new Config();
    $config->setRetainImageContent(false);
    $config->setDecodeMemoryLimit(33554432);
    $totalPages = 0;
    $totalBytes = 0;
    foreach ($input['paths'] as $path) {
        if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('Invalid PDF');
        }
        $pdf = (new Parser([], $config))->parseFile($path);
        $pages = count($pdf->getPages());
        unset($pdf);
        if ($pages < 1 || ($totalPages += $pages) > 500) {
            throw new RuntimeException('Invalid page count');
        }
        $totalBytes += filesize($path);
    }
    if ($input['operation'] === 'merge') {
        if (count($input['paths']) === 1) {
            if (!copy($input['paths'][0], $input['output'])) {
                throw new RuntimeException('Copy failed');
            }
        } else {
            $fpdi = new Fpdi();
            $imported = 0;
            foreach ($input['paths'] as $path) {
                $pages = $fpdi->setSourceFile($path);
                if ($pages < 1 || ($imported + $pages) > 500) {
                    throw new RuntimeException('Invalid page count');
                }
                for ($page = 1; $page <= $pages; $page++) {
                    $template = $fpdi->importPage($page);
                    $size = $fpdi->getTemplateSize($template);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($template);
                    $imported++;
                }
            }
            if ($imported !== $totalPages) {
                throw new RuntimeException('Inconsistent PDF');
            }
            $fpdi->Output($input['output'], 'F');
        }
    }
    echo json_encode(['page_count' => $totalPages, 'file_size' => $totalBytes], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    // Parser messages can contain user document content. Return only an exit status.
    exit(1);
}
