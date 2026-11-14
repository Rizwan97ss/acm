<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\LibraryBookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A title in the catalogue with a number of physical copies. `copies_available` is kept
 * in step with open loans by LibraryService.
 *
 * @property int $id
 * @property int $school_id
 * @property string $title
 * @property string|null $author
 * @property string|null $isbn
 * @property string|null $publisher
 * @property string|null $category
 * @property string|null $shelf
 * @property int $copies_total
 * @property int $copies_available
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'title', 'author', 'isbn', 'publisher', 'category', 'shelf', 'copies_total', 'copies_available'])]
class LibraryBook extends Model
{
    /** @use HasFactory<LibraryBookFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['copies_total' => 'integer', 'copies_available' => 'integer'];
    }

    /**
     * @return HasMany<LibraryLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(LibraryLoan::class);
    }
}
