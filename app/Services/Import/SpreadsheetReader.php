<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Reads the first sheet of an .xlsx or .csv file into rows keyed by column key. The first
 * non-empty row is the header. Cell values are normalised per column type: dates become
 * Y-m-d, Yes/No become 1/0, choice labels become their stored value, numbers lose ".0".
 */
class SpreadsheetReader
{
    public const int MAX_ROWS = 2000;

    /**
     * @param  list<ImportColumn>  $columns
     * @return list<array{line: int, values: array<string, string|null>}>
     *
     * @throws ValidationException when the file cannot be read or its header is wrong
     */
    public function read(string $path, string $extension, array $columns): array
    {
        $reader = $this->readerFor($extension);

        try {
            $reader->open($path);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('The file could not be read. Save it as .xlsx or .csv and try again.')]);
        }

        try {
            return $this->rows($reader, $columns);
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  list<ImportColumn>  $columns
     * @return list<array{line: int, values: array<string, string|null>}>
     */
    private function rows(XlsxReader|CsvReader $reader, array $columns): array
    {
        $byKey = [];

        foreach ($columns as $column) {
            $byKey[$column->key] = $column;
        }

        $positions = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $line = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $cells = $row->toArray();

                if ($this->isBlank($cells)) {
                    continue;
                }

                if ($positions === null) {
                    $positions = $this->matchHeader($cells, $columns);

                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => __('The file has more than :max rows. Split it into smaller files.', ['max' => self::MAX_ROWS])]);
                }

                $values = [];

                foreach ($byKey as $key => $column) {
                    $position = $positions[$key] ?? null;
                    $values[$key] = $position === null ? null : $this->normalize($cells[$position] ?? null, $column);
                }

                $rows[] = ['line' => $line, 'values' => $values];
            }

            break;
        }

        if ($positions === null) {
            throw ValidationException::withMessages(['file' => __('The file is empty. Use the template and fill one row per record under the header row.')]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('The file has a header row but no records under it.')]);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  list<ImportColumn>  $columns
     * @return array<string, int>
     */
    private function matchHeader(array $cells, array $columns): array
    {
        $positions = [];

        foreach ($cells as $position => $cell) {
            $heading = self::headingKey($this->scalar($cell));
            $column = $heading === '' ? null : collect($columns)->first(fn (ImportColumn $column): bool => $heading === $column->key || $heading === self::headingKey($column->label));

            if ($column !== null) {
                $positions[$column->key] ??= $position;
            }
        }

        if ($positions === []) {
            throw ValidationException::withMessages(['file' => __('The first row must be the header row from the template.')]);
        }

        $missing = collect($columns)
            ->filter(fn (ImportColumn $column): bool => $column->required && ! isset($positions[$column->key]))
            ->map(fn (ImportColumn $column): string => $column->label)
            ->values()
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => __('Required columns are missing: :columns. Download the template and keep its header row.', ['columns' => implode(', ', $missing)])]);
        }

        return $positions;
    }

    /**
     * "First name *", "first-name" and "FIRST_NAME" all become "first_name".
     */
    public static function headingKey(string $heading): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(str_replace('*', '', $heading))), '_');
    }

    private function normalize(mixed $cell, ImportColumn $column): ?string
    {
        if ($cell instanceof DateTimeInterface) {
            return CarbonImmutable::instance($cell)->toDateString();
        }

        $value = $this->scalar($cell);

        if ($value === '') {
            return null;
        }

        return match ($column->type) {
            ImportColumn::DATE => $this->date($value),
            ImportColumn::BOOLEAN => $this->boolean($value),
            ImportColumn::CODE => Str::upper(str_replace(' ', '', $value)),
            ImportColumn::DIGITS => Str::upper(str_replace([' ', '-'], '', $value)),
            ImportColumn::CHOICE => $this->choice($value, $column->choices),
            default => $value,
        };
    }

    private function scalar(mixed $cell): string
    {
        return match (true) {
            $cell === null, $cell instanceof ErrorCell, $cell instanceof DateInterval => '',
            is_bool($cell) => $cell ? '1' : '0',
            is_float($cell) && floor($cell) === $cell && abs($cell) < 1e15 => (string) (int) $cell,
            is_scalar($cell) => trim((string) $cell),
            $cell instanceof DateTimeInterface => $cell->format('Y-m-d'),
            default => '',
        };
    }

    /**
     * Accepts ISO dates and day-first dates (21/06/2015, 21-06-2015, 21.06.2015). Anything
     * else is passed through so the "date" rule reports it.
     */
    private function date(string $value): string
    {
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value) === 1) {
            return $value;
        }

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value);

            if ($date !== null && $date->format($format) === $this->padDate($value, $format)) {
                return $date->toDateString();
            }
        }

        return $value;
    }

    private function padDate(string $value, string $format): string
    {
        $separator = $format[1];
        $parts = explode($separator, $value);

        if (count($parts) !== 3) {
            return $value;
        }

        return sprintf('%02d%s%02d%s%s', (int) $parts[0], $separator, (int) $parts[1], $separator, $parts[2]);
    }

    private function boolean(string $value): string
    {
        return match (Str::lower($value)) {
            'yes', 'y', 'true', '1', 'teaching' => '1',
            'no', 'n', 'false', '0', 'non-teaching', 'non teaching' => '0',
            default => $value,
        };
    }

    /**
     * @param  array<string, string>  $choices
     */
    private function choice(string $value, array $choices): string
    {
        $wanted = self::headingKey($value);

        foreach ($choices as $stored => $label) {
            if ($wanted === self::headingKey($stored) || $wanted === self::headingKey($label)) {
                return $stored;
            }
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->scalar($cell) !== '' || $cell instanceof DateTimeInterface) {
                return false;
            }
        }

        return true;
    }

    private function readerFor(string $extension): XlsxReader|CsvReader
    {
        return match (Str::lower($extension)) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw ValidationException::withMessages(['file' => __('Upload an .xlsx or .csv file.')]),
        };
    }
}
