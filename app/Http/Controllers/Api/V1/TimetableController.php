<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\TimetableEntry;
use App\Services\People\PortalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Weekly timetable for the current academic year. Students and guardians get their own
 * sections; staff pass a section_id, or get their own teaching schedule without one.
 */
class TimetableController extends Controller
{
    public function __invoke(Request $request, PortalScope $portalScope): JsonResponse
    {
        Gate::authorize('timetables.view');

        $filters = $request->validate(['section_id' => ['nullable', 'integer']]);
        $user = $request->user();
        $year = AcademicYear::query()->current()->first();

        if ($year === null) {
            return response()->json(['data' => [], 'academic_year' => null]);
        }

        $allowedSectionIds = $portalScope->sectionIds($user, $year);
        $sectionId = $filters['section_id'] ?? null;

        abort_if($allowedSectionIds !== null && $sectionId !== null && ! in_array($sectionId, $allowedSectionIds, true), 404);

        $entries = TimetableEntry::query()
            ->with(['period', 'subject:id,name,code,color', 'teacher:id,name', 'section.schoolClass:id,name'])
            ->where('academic_year_id', $year->id)
            ->when(
                $sectionId !== null,
                fn (Builder $query) => $query->where('section_id', $sectionId),
                fn (Builder $query) => $allowedSectionIds !== null
                    ? $query->whereIn('section_id', $allowedSectionIds)
                    : $query->where('teacher_id', $user->id),
            )
            ->get()
            ->sortBy(fn (TimetableEntry $entry): string => sprintf('%d %05d', $entry->day_of_week->value, $entry->period->position))
            ->values();

        return response()->json([
            'academic_year' => $year->only(['id', 'name']),
            'data' => $entries->map(fn (TimetableEntry $entry): array => [
                'id' => $entry->id,
                'day_of_week' => $entry->day_of_week->value,
                'day' => $entry->day_of_week->label(),
                'period' => [
                    'id' => $entry->period->id,
                    'name' => $entry->period->name,
                    'starts_at' => substr($entry->period->starts_at, 0, 5),
                    'ends_at' => substr($entry->period->ends_at, 0, 5),
                ],
                'subject' => $entry->subject->only(['id', 'name', 'code', 'color']),
                'teacher' => $entry->teacher?->only(['id', 'name']),
                'section' => ['id' => $entry->section_id, 'name' => $entry->section->fullName()],
                'room' => $entry->room,
            ]),
        ]);
    }
}
