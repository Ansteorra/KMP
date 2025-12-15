<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Datasource\QueryInterface;
use Cake\Http\Response;

/**
 * Standardized CSV export for query results, entity collections, and arrays.
 *
 * Uses php://temp streams for memory efficiency. Returns Response objects
 * with proper download headers.
 */
class CsvExportService
{
    /**
     * Generate CSV response from data source.
     *
     * Uses php://temp streams for memory efficiency. Returns Response with download headers.
     *
     * @param QueryInterface|iterable $data Source data (query, array, or iterable)
     * @param string $filename Desired filename for download
     * @param array|null $headers Optional explicit column headers
     * @return Response HTTP response configured for CSV download
     */
    public function outputCsv(iterable $data, string $filename = 'export.csv', ?array $headers = null): Response
    {
        // If given a Query, get fields for headers and convert to iterator
        if ($data instanceof QueryInterface) {
            $fields = $data->clause('select');
            if ($fields && is_array($fields)) {
                $headers = $headers ?? array_values(array_map(function ($v, $k) {
                    return is_string($k) ? $k : $v;
                }, $fields, array_keys($fields)));
            }
            $data = $data->all();
        }
        $fh = fopen('php://temp', 'r+');
        $firstRow = null;
        foreach ($data as $row) {
            if (is_object($row) && method_exists($row, 'toArray')) {
                $row = $row->toArray();
            }
            if ($firstRow === null) {
                $firstRow = $row;
                if ($headers === null) {
                    $headers = array_keys($firstRow);
                }
                fputcsv($fh, $headers);
            }
            // Ensure row is in the same order as headers
            $rowData = [];
            foreach ($headers as $header) {
                $rowData[] = $row[$header] ?? '';
            }
            fputcsv($fh, $rowData);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $response = new Response([
            'body' => $csv,
            'type' => 'csv',
        ]);
        $response = $response->withType('csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
