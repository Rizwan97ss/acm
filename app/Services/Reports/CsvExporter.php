<?php

namespace App\Services\Reports;

use BackedEnum;
use DateTimeInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV downloads row by row (constant memory) with a UTF-8 BOM so spreadsheets
 * detect the encoding.
 *
 * Cells beginning with =, +, -, @, tab or carriage return are prefixed with an apostrophe
 * so spreadsheet apps treat them as text, not formulas (CSV / formula injection).
 */
class CsvExporter
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the output stream.');
            }

            fwrite($handle, "\u{FEFF}");
            fputcsv($handle, array_map($this->cell(...), $headings), escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->cell(...), $row), escape: '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function cell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default => (string) $value,
        };

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text)) {
            return "'".$text;
        }

        return $text;
    }
}
