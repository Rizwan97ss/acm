<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\ExamPaperRequest;
use App\Http\Requests\Academics\ExamRequest;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Academics\AcademicOptions;
use App\Services\Academics\ExamService;
use App\Services\Academics\GradeScaleService;
use App\Services\Academics\TeachingScope;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function __construct(
        private ExamService $exams,
        private AcademicOptions $options,
        private TeachingScope $scope,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Exam::class);

        $exams = Exam::query()
            ->with(['academicYear:id,name', 'term:id,name'])
            ->withCount('papers')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('academics/exams/index', [
            'exams' => $exams->map(fn (Exam $exam): array => [
                ...$this->present($exam),
                'papers_count' => (int) $exam->getAttribute('papers_count'),
            ]),
            'can' => ['create' => $request->user()->can('create', Exam::class)],
        ]);
    }

    public function create(GradeScaleService $scales): Response
    {
        Gate::authorize('create', Exam::class);

        $scales->defaultFor((int) $this->currentSchool->id());

        return Inertia::render('academics/exams/form', [...$this->formOptions(), 'exam' => null]);
    }

    public function store(ExamRequest $request): RedirectResponse
    {
        $exam = $this->exams->save(new Exam(['school_id' => $this->currentSchool->id()]), $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exam created. Now add its papers.')]);

        return to_route('academics.exams.show', $exam);
    }

    public function show(Request $request, Exam $exam): Response
    {
        Gate::authorize('view', $exam);

        $user = $request->user();
        $year = $exam->academicYear;
        $papers = $exam->papers()->with(['subject:id,name,code', 'schoolClass:id,name,level'])->withCount('marks')->get();
        $sectionsByClass = Section::query()->orderBy('name')->get()->groupBy('school_class_id');

        $classes = $papers->groupBy('school_class_id')->map(function ($classPapers) use ($sectionsByClass, $user, $year): array {
            $class = $classPapers->first()->schoolClass;
            $sections = $sectionsByClass->get($class->id, collect());

            return [
                'id' => $class->id,
                'name' => $class->name,
                'level' => $class->level,
                'sections' => $sections
                    ->filter(fn (Section $section): bool => $this->scope->canAccessSection($user, $section, $year, 'grades'))
                    ->map(fn (Section $section): array => ['id' => $section->id, 'name' => $section->name])
                    ->values(),
                'papers' => $classPapers->sortBy(fn (ExamPaper $paper): string => $paper->subject->name)->map(fn (ExamPaper $paper): array => [
                    'id' => $paper->id,
                    'subject_id' => $paper->subject_id,
                    'subject' => $paper->subject->name,
                    'code' => $paper->subject->code,
                    'held_on' => $paper->held_on?->toDateString(),
                    'max_marks' => (float) $paper->max_marks,
                    'pass_marks' => (float) $paper->pass_marks,
                    'marks_count' => (int) $paper->getAttribute('marks_count'),
                    'entry_sections' => $sections
                        ->filter(fn (Section $section): bool => $this->scope->canTeachSubject($user, $section, $paper->subject_id, $year, 'grades'))
                        ->pluck('id')
                        ->values(),
                ])->values(),
            ];
        })->sortBy('level')->values();

        return Inertia::render('academics/exams/show', [
            'exam' => $this->present($exam->load(['academicYear:id,name', 'term:id,name', 'gradeScale:id,name'])),
            'classes' => $classes,
            'classOptions' => $this->options->classes(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => $user->can('update', $exam) && ! $exam->isPublished(),
                'publish' => $user->can('publish', $exam),
                'enterMarks' => $user->can('grades.create') || $user->can('grades.edit'),
                'viewResults' => $user->can('grades.view'),
            ],
        ]);
    }

    public function edit(Exam $exam): Response
    {
        Gate::authorize('update', $exam);

        return Inertia::render('academics/exams/form', [...$this->formOptions(), 'exam' => $this->present($exam)]);
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        $this->exams->save($exam, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exam updated.')]);

        return to_route('academics.exams.show', $exam);
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        Gate::authorize('delete', $exam);

        $this->exams->delete($exam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exam deleted.')]);

        return to_route('academics.exams.index');
    }

    public function generatePapers(Exam $exam): RedirectResponse
    {
        Gate::authorize('update', $exam);

        $created = $this->exams->generatePapers($exam);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} Every class already has its papers.|{1} 1 paper added.|[2,*] :count papers added.', $created, ['count' => $created])]);

        return to_route('academics.exams.show', $exam);
    }

    public function storePaper(ExamPaperRequest $request, Exam $exam): RedirectResponse
    {
        $this->exams->savePaper($exam, new ExamPaper, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paper added.')]);

        return to_route('academics.exams.show', $exam);
    }

    public function updatePaper(ExamPaperRequest $request, Exam $exam, ExamPaper $paper): RedirectResponse
    {
        abort_unless($paper->exam_id === $exam->id, 404);

        $this->exams->savePaper($exam, $paper, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paper updated.')]);

        return to_route('academics.exams.show', $exam);
    }

    public function destroyPaper(Exam $exam, ExamPaper $paper): RedirectResponse
    {
        Gate::authorize('update', $exam);
        abort_unless($paper->exam_id === $exam->id, 404);

        $this->exams->deletePaper($exam, $paper);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paper removed.')]);

        return to_route('academics.exams.show', $exam);
    }

    public function publish(Request $request, Exam $exam): RedirectResponse
    {
        Gate::authorize('publish', $exam);

        $hasMarks = Mark::query()->whereIn('exam_paper_id', $exam->papers()->select('id'))->exists();
        if (! $hasMarks) {
            throw ValidationException::withMessages(['exam' => __('Enter marks before publishing results.')]);
        }

        $this->exams->publish($exam, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Results published to students and parents.')]);

        return to_route('academics.exams.show', $exam);
    }

    public function unpublish(Request $request, Exam $exam): RedirectResponse
    {
        Gate::authorize('publish', $exam);

        $this->exams->unpublish($exam, $request->user());

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Results hidden; marks can be edited again.')]);

        return to_route('academics.exams.show', $exam);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'name' => $exam->name,
            'academic_year_id' => $exam->academic_year_id,
            'academic_year' => $exam->relationLoaded('academicYear') ? $exam->academicYear->name : null,
            'term_id' => $exam->term_id,
            'term' => $exam->relationLoaded('term') ? $exam->term?->name : null,
            'grade_scale_id' => $exam->grade_scale_id,
            'grade_scale' => $exam->relationLoaded('gradeScale') ? $exam->gradeScale->name : null,
            'starts_on' => $exam->starts_on->toDateString(),
            'ends_on' => $exam->ends_on->toDateString(),
            'published_at' => $exam->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'years' => $this->options->years(),
            'terms' => Term::query()->orderBy('starts_on')->get(['id', 'name', 'academic_year_id']),
            'scales' => GradeScale::query()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
            'currentYearId' => AcademicYear::query()->current()->value('id'),
        ];
    }
}
