<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\Quiz;
use App\Services\Learning\LearningScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Helpers shared by the learning controllers.
 */
abstract class LearningController extends Controller
{
    public function __construct(protected LearningScope $scope) {}

    protected function currentYear(): AcademicYear
    {
        $year = AcademicYear::query()->current()->first();

        abort_if($year === null, 409, __('Set a current academic year first.'));

        return $year;
    }

    /**
     * Who the content is for, e.g. "Class 5 – A" or "Class 5 (all sections)".
     */
    protected function audienceLabel(Course|LiveClass|Quiz $record): string
    {
        $record->loadMissing(['schoolClass:id,name', 'section:id,name']);

        return $record->section !== null
            ? "{$record->schoolClass->name} – {$record->section->name}"
            : __(':class (all sections)', ['class' => $record->schoolClass->name]);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, from: int|null, last_page: int, per_page: int, to: int|null, total: int, links: array<int, mixed>}
     */
    protected function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'links' => $paginator->linkCollection()->all(),
        ];
    }

    /**
     * Swap an item's position with its neighbour among its siblings.
     *
     * @template TModel of Model
     *
     * @param  TModel  $item
     * @param  Builder<TModel>  $siblings
     */
    protected function move(Model $item, Builder $siblings, string $direction): void
    {
        $position = (int) $item->getAttribute('position');
        $neighbour = $siblings
            ->whereKeyNot($item->getKey())
            ->where('position', $direction === 'up' ? '<=' : '>=', $position)
            ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
            ->orderBy('id', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if ($neighbour === null) {
            return;
        }

        DB::transaction(function () use ($item, $neighbour, $position, $direction): void {
            $other = (int) $neighbour->getAttribute('position');
            $item->forceFill(['position' => $other === $position ? max(0, $position + ($direction === 'up' ? -1 : 1)) : $other])->save();
            $neighbour->forceFill(['position' => $position])->save();
        });
    }
}
