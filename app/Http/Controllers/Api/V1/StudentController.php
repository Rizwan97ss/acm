<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudentStatus;
use App\Http\Controllers\Api\V1\Concerns\ScopesPortalStudents;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\StudentResource;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Services\Academics\AttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    use ScopesPortalStudents;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $studentIds = $this->portalStudentIds($user);

        if ($studentIds === null) {
            Gate::authorize('viewAny', Student::class);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(StudentStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $students = Student::query()
            ->with('currentEnrollment.section.schoolClass:id,name')
            ->when($studentIds !== null, fn (Builder $query) => $query->whereIn('id', $studentIds))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$escaped}%")
                    ->orWhere('last_name', 'like', "%{$escaped}%")
                    ->orWhere('admission_no', 'like', "%{$escaped}%"));
            })
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $sectionId) => $query->whereHas(
                'currentEnrollment',
                fn (Builder $enrollment) => $enrollment->where('section_id', $sectionId),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return StudentResource::collection($students);
    }

    public function show(Request $request, Student $student): StudentResource
    {
        $this->authorizeStudent($request->user(), $student);

        return new StudentResource($student->load('currentEnrollment.section.schoolClass:id,name'));
    }

    /**
     * Attendance records (newest first) plus a summary for the current academic year.
     */
    public function attendance(Request $request, Student $student, AttendanceService $attendance): JsonResponse
    {
        Gate::authorize('attendance.view');
        $this->authorizeStudent($request->user(), $student);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $year = AcademicYear::query()->current()->first();

        $records = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->paginate($filters['per_page'] ?? 31)
            ->withQueryString();

        return response()->json([
            'data' => $records->getCollection()->map(fn (AttendanceRecord $record): array => [
                'date' => $record->date->toDateString(),
                'status' => $record->status->value,
                'remarks' => $record->remarks,
            ])->values(),
            'summary' => $year === null ? null : [
                'academic_year' => $year->only(['id', 'name']),
                ...$attendance->summary($student, $year),
            ],
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }
}
