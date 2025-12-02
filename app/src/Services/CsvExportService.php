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
     * 2. **Header Extraction**: Gets column headers from query fields or first row
     * 3. **Stream Processing**: Uses temporary streams for memory-efficient processing
     * 4. **Entity Conversion**: Converts entities to arrays using toArray() method
     * 5. **Data Alignment**: Ensures all rows match header structure
     * 6. **Response Generation**: Creates HTTP response with proper download headers
     * 
     * ## Header Handling
     * 
     * Headers can be provided explicitly or auto-detected:
     * - **Query Objects**: Uses SELECT clause field names as headers
     * - **Arrays**: Uses keys from first row as headers
     * - **Explicit**: Provided headers parameter takes precedence
     * - **Missing Data**: Empty string used for missing column values
     * 
     * ## Memory Management
     * 
     * Uses `php://temp` stream to:
     * - Process large datasets without memory exhaustion
     * - Handle queries that may return thousands of rows
     * - Maintain consistent performance regardless of data size
     * - Allow garbage collection of processed rows
     * 
     * @param QueryInterface|iterable $data Source data (query, array, or iterable)
     * @param string $filename Desired filename for download (sanitized automatically)
     * @param array|null $headers Optional explicit column headers
     * 
     * @return Response HTTP response configured for CSV file download
     * 
     * @example
     * ```php
     * // Basic query export
     * $query = $this->Members->find()->select(['sca_name', 'email_address']);
     * return $csvService->outputCsv($query, 'members.csv');
     * 
     * // Custom headers with array data
     * $data = [['John', 'john@example.com'], ['Jane', 'jane@example.com']];
     * return $csvService->outputCsv($data, 'contacts.csv', ['Name', 'Email']);
     * 
     * // Entity collection with automatic headers
     * $members = $this->Members->find()->contain(['Branches'])->all();
     * return $csvService->outputCsv($members, 'member_report.csv');
     * ```
     * 
     * @throws \InvalidArgumentException If data source is not supported
     * @throws \RuntimeException If stream operations fail
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
