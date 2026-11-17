<?php

namespace App\Services\Import;

use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates every row of an uploaded file and, when nothing is wrong, saves all rows in one
 * transaction: either the whole file is imported or nothing is.
 */
class ImportService
{
    public const int MAX_REPORTED_ERRORS = 200;

    public const int SAMPLE_ROWS = 10;

    public function __construct(
        private SpreadsheetReader $reader,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{total: int, valid: int, invalid: int, errors: list<array{line: int, messages: list<string>}>, errors_truncated: bool, sample: list<array{line: int, values: array<string, string|null>}>}
     */
    public function preview(Importer $importer, string $path, string $extension, ImportBatch $batch): array
    {
        $rows = $this->reader->read($path, $extension, $importer->columns());
        $errors = $this->validate($importer, $rows, $batch);
        $previewKeys = collect($importer->columns())->filter->preview->pluck('key')->all();

        return [
            'total' => count($rows),
            'valid' => count($rows) - count($errors),
            'invalid' => count($errors),
            'errors' => array_slice($errors, 0, self::MAX_REPORTED_ERRORS),
            'errors_truncated' => count($errors) > self::MAX_REPORTED_ERRORS,
            'sample' => array_map(
                fn (array $row): array => ['line' => $row['line'], 'values' => array_intersect_key($row['values'], array_flip($previewKeys))],
                array_slice($rows, 0, self::SAMPLE_ROWS),
            ),
        ];
    }

    /**
     * Re-reads and re-validates the file (records may have changed since the preview), then
     * saves every row or none.
     *
     * @throws ValidationException when any row is invalid or fails while saving
     */
    public function import(Importer $importer, string $path, string $extension, ImportBatch $batch, string $fileName): int
    {
        $rows = $this->reader->read($path, $extension, $importer->columns());
        $errors = $this->validate($importer, $rows, $batch);

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => __(':count rows have problems. Fix them and upload the file again.', ['count' => count($errors)])]);
        }

        DB::transaction(function () use ($importer, $rows, $batch): void {
            foreach ($rows as $row) {
                $batch->line = $row['line'];

                try {
                    $importer->import($row['values'], $batch);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['file' => __('Row :line: :message Nothing was imported.', [
                        'line' => $row['line'],
                        'message' => collect($exception->errors())->flatten()->first(),
                    ])]);
                }
            }
        });

        $this->audit->log("import.{$importer->key()}", null, [], ['rows' => count($rows), 'file' => $fileName], $batch->actor);

        Log::info('Spreadsheet import completed.', [
            'importer' => $importer->key(),
            'rows' => count($rows),
            'user_id' => $batch->actor->id,
            'school_id' => $batch->schoolId,
        ]);

        return count($rows);
    }

    /**
     * @param  list<array{line: int, values: array<string, string|null>}>  $rows
     * @return list<array{line: int, messages: list<string>}>
     */
    private function validate(Importer $importer, array $rows, ImportBatch $batch): array
    {
        $batch->resetSeen();
        $rules = $importer->rules($batch);
        $attributes = collect($importer->columns())->mapWithKeys(fn (ImportColumn $column): array => [$column->key => $column->label])->all();
        $errors = [];

        foreach ($rows as $row) {
            $batch->line = $row['line'];
            $messages = Validator::make($row['values'], $rules, [], $attributes)->errors()->all();

            if ($messages === []) {
                $messages = $importer->check($row['values'], $batch);
            }

            if ($messages !== []) {
                $errors[] = ['line' => $row['line'], 'messages' => array_values($messages)];
            }
        }

        return $errors;
    }
}
