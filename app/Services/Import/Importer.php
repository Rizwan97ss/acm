<?php

namespace App\Services\Import;

use App\Models\User;

/**
 * A kind of record that can be bulk-loaded from a spreadsheet. Each row is validated with
 * rules() and check(), and saved with import() inside one transaction for the whole file.
 */
interface Importer
{
    public function key(): string;

    public function title(): string;

    public function description(): string;

    public function authorize(User $user): bool;

    /**
     * @return list<ImportColumn>
     */
    public function columns(): array;

    /**
     * Laravel validation rules for one row, keyed by column key.
     *
     * @return array<string, mixed>
     */
    public function rules(ImportBatch $batch): array;

    /**
     * Checks that need lookups or knowledge of earlier rows (duplicates within the file).
     *
     * @param  array<string, string|null>  $values
     * @return list<string>
     */
    public function check(array $values, ImportBatch $batch): array;

    /**
     * Save one validated row.
     *
     * @param  array<string, string|null>  $values
     */
    public function import(array $values, ImportBatch $batch): void;
}
