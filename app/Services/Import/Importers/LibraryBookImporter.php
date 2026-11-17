<?php

namespace App\Services\Import\Importers;

use App\Models\LibraryBook;
use App\Models\User;
use App\Services\Import\ImportBatch;
use App\Services\Import\ImportColumn;
use App\Services\Import\Importer;
use Illuminate\Validation\Rule;

/**
 * Loads titles into the library catalogue. ISBNs (dashes and spaces ignored) must be unique
 * in the school and within the file.
 */
class LibraryBookImporter implements Importer
{
    public function key(): string
    {
        return 'library-books';
    }

    public function title(): string
    {
        return __('Library books');
    }

    public function description(): string
    {
        return __('Adds titles to the library catalogue with the number of copies the library owns.');
    }

    public function authorize(User $user): bool
    {
        return $user->can('library.create');
    }

    public function columns(): array
    {
        return [
            new ImportColumn('title', 'Title', required: true, example: 'A Brief History of Time', preview: true),
            new ImportColumn('author', 'Author', example: 'Stephen Hawking', preview: true),
            new ImportColumn('isbn', 'ISBN', type: ImportColumn::DIGITS, hint: '10 or 13 digits; dashes and spaces are ignored.', example: '978-0553380163', preview: true),
            new ImportColumn('publisher', 'Publisher', example: 'Bantam'),
            new ImportColumn('category', 'Category', example: 'Science', preview: true),
            new ImportColumn('shelf', 'Shelf', example: 'S-12'),
            new ImportColumn('copies', 'Copies', required: true, type: ImportColumn::INTEGER, hint: '0 to 1000.', example: '3', preview: true),
        ];
    }

    public function rules(ImportBatch $batch): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:150'],
            'isbn' => ['nullable', 'string', 'regex:/^(\d{9}[\dX]|\d{13})$/', Rule::unique('library_books', 'isbn')->where('school_id', $batch->schoolId)],
            'publisher' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:60'],
            'shelf' => ['nullable', 'string', 'max:30'],
            'copies' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function check(array $values, ImportBatch $batch): array
    {
        if ($line = $batch->duplicateOf('isbn', $values['isbn'])) {
            return [__('ISBN :value is also used on row :line.', ['value' => $values['isbn'], 'line' => $line])];
        }

        return [];
    }

    public function import(array $values, ImportBatch $batch): void
    {
        LibraryBook::query()->create([
            'school_id' => $batch->schoolId,
            'title' => $values['title'],
            'author' => $values['author'],
            'isbn' => $values['isbn'],
            'publisher' => $values['publisher'],
            'category' => $values['category'],
            'shelf' => $values['shelf'],
            'copies_total' => (int) $values['copies'],
            'copies_available' => (int) $values['copies'],
        ]);
    }
}
