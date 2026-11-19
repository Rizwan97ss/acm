<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\AcademicOptions;
use App\Services\People\IdCardService;
use App\Services\People\PortalScope;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printable ID cards (54 × 85.6 mm, front and back) for students, staff and parents, and
 * the public verification page their QR codes open.
 */
class IdCardController extends Controller
{
    public const int MAX_BATCH = 200;

    public function __construct(
        private IdCardService $cards,
        private PortalScope $portal,
    ) {}

    /**
     * Choose whose cards to print.
     */
    public function index(Request $request, AcademicOptions $options): Response
    {
        $user = $request->user();
        abort_if($this->portal->isPortalUser($user), 403);

        $can = [
            'students' => $user->hasPermission('students.view'),
            'staff' => $user->hasPermission('employees.view') || $user->hasPermission('teachers.view'),
            'parents' => $user->hasPermission('guardians.view'),
        ];
        abort_unless($can['students'] || $can['staff'] || $can['parents'], 403);

        return Inertia::render('people/id-cards', [
            'can' => $can,
            'sections' => $options->sections(),
            'staffCount' => $can['staff'] ? Employee::query()->where('status', 'active')->count() : 0,
        ]);
    }

    public function print(Request $request, CurrentSchool $currentSchool): Response
    {
        $validated = $request->validate([
            'type' => ['required', 'in:students,staff,parents'],
            'section_id' => ['nullable', 'integer'],
            'ids' => ['nullable', 'array', 'max:'.self::MAX_BATCH],
            'ids.*' => ['integer'],
            'teaching' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        abort_if($this->portal->isPortalUser($user), 403);
        $ids = array_values(array_map('intval', (array) ($validated['ids'] ?? [])));
        $sectionId = isset($validated['section_id']) ? (int) $validated['section_id'] : null;

        $cards = match ((string) $validated['type']) {
            'students' => $this->studentCards($user, $ids, $sectionId),
            'staff' => $this->staffCards($user, $ids, $request->boolean('teaching')),
            'parents' => $this->parentCards($user, $ids, $sectionId),
            default => abort(404),
        };

        abort_if($cards->isEmpty(), 404, __('No cards to print for this selection.'));

        Log::info('ID cards printed.', ['type' => $validated['type'], 'count' => $cards->count(), 'user_id' => $user->id]);

        return Inertia::render('standalone/id-cards', [
            'cards' => $cards->values(),
            'school' => $this->cards->school($currentSchool->get()),
        ]);
    }

    /**
     * JSON lookup for printing one person's card: name or number, at most ten matches.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:students,staff,parents'],
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $user = $request->user();
        abort_if($this->portal->isPortalUser($user), 403);
        $words = array_slice(preg_split('/\s+/', trim((string) $validated['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 4);
        $byName = function (Builder $query, string $number) use ($words): void {
            foreach ($words as $word) {
                $like = '%'.addcslashes($word, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere($number, 'like', $like));
            }
        };

        $results = match ((string) $validated['type']) {
            'students' => $this->searchStudents($user, $byName),
            'staff' => $this->searchStaff($user, $byName),
            'parents' => $this->searchParents($user, $byName),
            default => abort(404),
        };

        return response()->json($results);
    }

    /**
     * @param  callable(Builder<Student>, string): mixed  $byName
     * @return list<array{id: int, name: string, number: string, detail: string|null}>
     */
    private function searchStudents(User $user, callable $byName): array
    {
        abort_unless($user->hasPermission('students.view'), 403);

        return array_values(Student::query()
            ->tap(fn (Builder $query) => $byName($query, 'admission_no'))
            ->with('currentEnrollment.section.schoolClass')
            ->orderBy('first_name')
            ->limit(10)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->full_name,
                'number' => $student->admission_no,
                'detail' => $student->currentEnrollment?->section?->fullName(),
            ])
            ->all());
    }

    /**
     * @param  callable(Builder<Employee>, string): mixed  $byName
     * @return list<array{id: int, name: string, number: string, detail: string|null}>
     */
    private function searchStaff(User $user, callable $byName): array
    {
        $all = $user->hasPermission('employees.view');
        abort_unless($all || $user->hasPermission('teachers.view'), 403);

        return array_values(Employee::query()
            ->where('status', 'active')
            ->when(! $all, fn (Builder $query) => $query->where('is_teaching', true))
            ->tap(fn (Builder $query) => $byName($query, 'employee_no'))
            ->orderBy('first_name')
            ->limit(10)
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'number' => $employee->employee_no,
                'detail' => $employee->designation,
            ])
            ->all());
    }

    /**
     * @param  callable(Builder<Guardian>, string): mixed  $byName
     * @return list<array{id: int, name: string, number: string, detail: string|null}>
     */
    private function searchParents(User $user, callable $byName): array
    {
        abort_unless($user->hasPermission('guardians.view'), 403);

        return array_values(Guardian::query()
            ->tap(fn (Builder $query) => $byName($query, 'email'))
            ->withCount('students')
            ->orderBy('first_name')
            ->limit(10)
            ->get()
            ->map(fn (Guardian $guardian): array => [
                'id' => $guardian->id,
                'name' => $guardian->full_name,
                'number' => sprintf('P-%06d', $guardian->id),
                'detail' => trans_choice(':count child|:count children', (int) $guardian->students_count),
            ])
            ->all());
    }

    /**
     * Replace a lost card: the old QR code stops verifying.
     */
    public function reissue(Request $request, string $type, int $id): RedirectResponse
    {
        $user = $request->user();
        $person = match ($type) {
            'students' => Student::query()->findOrFail($id),
            'staff' => Employee::query()->findOrFail($id),
            'parents' => Guardian::query()->findOrFail($id),
            default => abort(404),
        };

        abort_unless($user->can('update', $person), 403);

        $this->cards->reissue($person);
        Log::warning('ID card reissued; the previous card no longer verifies.', ['type' => $type, 'id' => $id, 'user_id' => $user->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('New card issued. The old card’s QR code no longer verifies.')]);

        return back();
    }

    /**
     * Public: what the QR code on a card opens. Shows only what is printed on the card.
     */
    public function verify(string $token): Response
    {
        $person = Student::query()->withoutGlobalScopes()->where('card_token', $token)->first()
            ?? Employee::query()->withoutGlobalScopes()->where('card_token', $token)->first()
            ?? Guardian::query()->withoutGlobalScopes()->where('card_token', $token)->first();

        if ($person === null) {
            return Inertia::render('standalone/verify-id', ['result' => null]);
        }

        $school = School::query()->find($person->school_id);
        $year = AcademicYear::query()->withoutGlobalScopes()->where('school_id', $person->school_id)->where('is_current', true)->first();

        [$type, $number, $active] = match (true) {
            $person instanceof Student => [__('Student'), $person->admission_no, $person->status->value === 'active'],
            $person instanceof Employee => [__('Staff'), $person->employee_no, $person->status->value === 'active'],
            default => [__('Parent / Guardian'), sprintf('P-%06d', $person->id), $person->deleted_at === null],
        };

        return Inertia::render('standalone/verify-id', [
            'result' => [
                'valid' => $active && $school?->is_active === true,
                'type' => $type,
                'name' => $person->full_name,
                'number' => $number,
                'school' => $school->name ?? '',
                'valid_until' => $year?->ends_on?->toDateString(),
            ],
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function studentCards(User $user, array $ids, ?int $sectionId): Collection
    {
        abort_unless($user->hasPermission('students.view'), 403);

        return Student::query()
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
            ->when($sectionId !== null, fn (Builder $query) => $query->whereIn('id', Enrollment::query()->where('section_id', $sectionId)->select('student_id')))
            ->when($ids === [] && $sectionId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('first_name')
            ->take(self::MAX_BATCH)
            ->get()
            ->map(fn (Student $student): array => $this->cards->student($student));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function staffCards(User $user, array $ids, bool $teachingOnly): Collection
    {
        $all = $user->hasPermission('employees.view');
        abort_unless($all || $user->hasPermission('teachers.view'), 403);

        return Employee::query()
            ->where('status', 'active')
            ->when(! $all || $teachingOnly, fn (Builder $query) => $query->where('is_teaching', true))
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
            ->orderBy('first_name')
            ->take(self::MAX_BATCH)
            ->get()
            ->map(fn (Employee $employee): array => $this->cards->employee($employee));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function parentCards(User $user, array $ids, ?int $sectionId): Collection
    {
        abort_unless($user->hasPermission('guardians.view'), 403);

        return Guardian::query()
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
            ->when($sectionId !== null, fn (Builder $query) => $query->whereHas('students', fn (Builder $students) => $students
                ->whereIn('students.id', Enrollment::query()->where('section_id', $sectionId)->select('student_id'))))
            ->when($ids === [] && $sectionId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('first_name')
            ->take(self::MAX_BATCH)
            ->get()
            ->map(fn (Guardian $guardian): array => $this->cards->guardian($guardian));
    }
}
