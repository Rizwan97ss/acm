<?php

namespace App\Services\Import;

use App\Models\User;
use App\Services\Import\Importers\AdmissionImporter;
use App\Services\Import\Importers\LibraryBookImporter;
use App\Services\Import\Importers\StaffImporter;
use App\Services\Import\Importers\StudentImporter;

/**
 * Every spreadsheet import the application offers. Add a new importer class here.
 */
class ImportRegistry
{
    /**
     * @var list<class-string<Importer>>
     */
    public const array IMPORTERS = [
        StudentImporter::class,
        StaffImporter::class,
        AdmissionImporter::class,
        LibraryBookImporter::class,
    ];

    /**
     * @return list<Importer>
     */
    public function all(): array
    {
        return array_map(fn (string $class): Importer => app($class), self::IMPORTERS);
    }

    /**
     * @return list<Importer>
     */
    public function availableTo(User $user): array
    {
        return array_values(array_filter($this->all(), fn (Importer $importer): bool => $importer->authorize($user)));
    }

    public function find(string $key): ?Importer
    {
        foreach ($this->all() as $importer) {
            if ($importer->key() === $key) {
                return $importer;
            }
        }

        return null;
    }
}
