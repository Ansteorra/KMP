<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Http\Response;
use Cake\Datasource\ResultSetInterface;
use Cake\Datasource\QueryInterface;

/**
 * CsvExportService
 *
 * Provides a globally reusable service to output CSV from arrays or iterators (e.g., SelectQuery).
 */
class CsvExportService
{
    /**
     * Outputs a CSV response from an array, iterator, or Query.
     *
     * @param iterable|QueryInterface $data Array, iterator, or Query of rows (associative arrays or entities)
     * @param string $filename Name of the CSV file for download
     * @param array|null $headers Optional: List of column headers (if not provided, uses query fields or first row)
     * @return Response
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
