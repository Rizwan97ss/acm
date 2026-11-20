<?php

namespace App\Http\Controllers\Academics\OnlineExams;

use App\Enums\ExamResultsStatus;
use App\Enums\OnlineExamState;
use App\Enums\PublishStatus;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Http\Requests\Academics\OnlineExamRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\OnlineExam;
use App\Models\OnlineExamQuestion;
use App\Models\User;
use App\Notifications\LearningUpdate;
use App\Services\Academics\Exams\OnlineExamReports;
use App\Services\Academics\Exams\OnlineExamScope;
use App\Services\Academics\Exams\OnlineExamService;
use App\Services\Learning\LearningScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teachers create, configure, release and manage online exams for the classes and
 * subjects they teach; supervisors ("online_exams.approve") for the whole school.
 */
class ExamManageController extends OnlineExamController
{
    /**
     * Settings that may still change after students have started.
     */
    private const array EDITABLE_WHEN_LOCKED = ['title', 'instructions', 'ends_at', 'show_rank'];

    public function __construct(
        OnlineExamScope $scope,
        private OnlineExamService $exams,
        private OnlineExamReports $reports,
        private LearningScope $learning,
    ) {
        parent::__construct($scope);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->scope->isStaff($user), 403);
        $year = $this->currentYear();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'state' => ['nullable', 'in:'.implode(',', array_column(OnlineExamState::cases(), 'value'))],
        ]);

        $now = now();
        $exams = $this->scope->forStaff(OnlineExam::query(), $user, $year)
            ->with(['subject:id,name,color', 'schoolClass:id,name', 'section:id,name'])
            ->withCount(['questions', 'students', 'attempts', 'attempts as to_mark_count' => fn (Builder $query) => $query->where('status', QuizAttemptStatus::Submitted)])
            ->where('academic_year_id', $year->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('school_class_id', $classId))
            ->when($filters['state'] ?? null, fn (Builder $query, string $state) => match (OnlineExamState::from($state)) {
                OnlineExamState::Draft => $query->where('status', PublishStatus::Draft),
                OnlineExamState::ResultsPublished => $query->where('results_status', ExamResultsStatus::Published),
                OnlineExamState::Scheduled => $query->where('status', PublishStatus::Published)->where('starts_at', '>', $now),
                OnlineExamState::Active => $query->where('status', PublishStatus::Published)->where('starts_at', '<=', $now)->where('ends_at', '>', $now),
                OnlineExamState::Completed => $query->where('status', PublishStatus::Published)->where('ends_at', '<=', $now)->where('results_status', '!=', ExamResultsStatus::Published),
            })
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();

        $options = $this->scope->teachingOptions($user, $year);

        return Inertia::render('academics/online-exams/index', [
            'exams' => [
                'data' => $exams->getCollection()->map(fn (OnlineExam $exam): array => [
                    ...$this->present($exam),
                    'questions_count' => (int) $exam->getAttribute('questions_count'),
                    'attempts_count' => (int) $exam->getAttribute('attempts_count'),
                    'to_mark_count' => (int) $exam->getAttribute('to_mark_count'),
                ])->values(),
                'meta' => $this->meta($exams),
            ],
            'filters' => $filters,
            'classes' => array_map(fn (array $class): array => ['id' => $class['id'], 'name' => $class['name']], $options),
            'subjects' => collect($options)->flatMap(fn (array $class) => $class['subjects'])->unique('id')->sortBy('name')->values(),
            'states' => OnlineExamState::options(),
            'can' => [
                'create' => $user->hasPermission('online_exams.create') && $options !== [],
                'reports' => true,
                'bank' => $user->hasPermission('question_bank.view'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('online_exams.create'), 403);

        return Inertia::render('academics/online-exams/form', [...$this->formOptions($request->user()), 'exam' => null]);
    }

    public function store(OnlineExamRequest $request): RedirectResponse
    {
        $year = $this->currentYear();
        $this->authorizeAudience($request, $year);
        $studentIds = $this->checkedStudents($request, $year);

        $exam = DB::transaction(function () use ($request, $year, $studentIds): OnlineExam {
            $exam = OnlineExam::query()->create([
                ...$request->settings(),
                'academic_year_id' => $year->id,
                'status' => PublishStatus::Draft,
                'created_by' => $request->user()->id,
            ]);
            $exam->students()->sync($studentIds);

            return $exam;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exam created. Add questions, then publish it.')]);

        return to_route('academics.online-exams.show', $exam);
    }

    public function show(Request $request, OnlineExam $onlineExam): Response
    {
        $exam = $onlineExam;
        $this->authorizeView($exam);
        $user = $request->user();
        $exam->load(['questions', 'students:id']);
        $canManage = $this->scope->canManage($user, $exam);
        $locked = $exam->isLocked();
        $report = $this->reports->exam($exam, false);

        return Inertia::render('academics/online-exams/show', [
            'exam' => [
                ...$this->present($exam),
                'questions_total' => round((float) $exam->questions->sum(fn (OnlineExamQuestion $question): float => (float) $question->marks), 2),
                'locked' => $locked,
                'author' => $exam->author?->name,
                'results_approved_at' => $exam->results_approved_at?->toIso8601String(),
            ],
            'questions' => $exam->questions->map(fn (OnlineExamQuestion $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'type_label' => $question->type->label(),
                'prompt' => $question->prompt,
                'marks' => (float) $question->marks,
                'negative_marks' => (float) $question->negative_marks,
                'explanation' => $question->explanation,
                'options' => $question->options ?? [],
                'answer_key' => $question->answer_key ?? [],
                'from_bank' => $question->bank_question_id !== null,
            ])->values(),
            'summary' => $report['summary'],
            'standings' => $report['standings'],
            'questionTypes' => QuestionType::options(),
            'difficulties' => QuestionDifficulty::options(),
            'can' => [
                'edit' => $canManage,
                'edit_questions' => $canManage && ! $locked,
                'delete' => $canManage && $user->hasPermission('online_exams.delete') && ! $locked,
                'release' => $canManage,
                'mark' => $canManage && $exam->results_status === ExamResultsStatus::Pending,
                'approve' => $canManage,
                'publish_results' => $user->hasPermission('online_exams.publish'),
                'export' => $user->hasPermission('online_exams.export'),
                'bank' => $user->hasPermission('question_bank.view'),
                'save_to_bank' => $user->hasPermission('question_bank.create'),
            ],
        ]);
    }

    public function edit(Request $request, OnlineExam $onlineExam): Response
    {
        $this->authorizeManage($onlineExam);
        $onlineExam->load('students:id');

        return Inertia::render('academics/online-exams/form', [
            ...$this->formOptions($request->user()),
            'exam' => [...$this->present($onlineExam), 'student_ids' => $onlineExam->students->pluck('id')->values(), 'locked' => $onlineExam->isLocked()],
        ]);
    }

    public function update(OnlineExamRequest $request, OnlineExam $onlineExam): RedirectResponse
    {
        $exam = $onlineExam;
        $this->authorizeManage($exam);
        $year = AcademicYear::query()->findOrFail($exam->academic_year_id);
        $settings = $request->settings();

        if ($exam->isLocked()) {
            $this->guardLockedChanges($exam, $settings, $request->studentIds());

            if ($exam->ends_at->gt($request->date('ends_at'))) {
                throw ValidationException::withMessages(['ends_at' => __('Students have started, so the end time can only be extended.')]);
            }

            $exam->update(array_intersect_key($settings, array_flip(self::EDITABLE_WHEN_LOCKED)));
        } else {
            $this->authorizeAudience($request, $year);
            $studentIds = $this->checkedStudents($request, $year);

            DB::transaction(function () use ($exam, $settings, $studentIds): void {
                $exam->update($settings);
                $exam->students()->sync($studentIds);
            });
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exam saved.')]);

        return to_route('academics.online-exams.show', $exam);
    }

    public function destroy(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->authorizeManage($onlineExam);
        abort_unless($request->user()->hasPermission('online_exams.delete'), 403);

        if ($onlineExam->isLocked()) {
            throw ValidationException::withMessages(['exam' => __('Students have sat this exam, so it is kept as a record and cannot be deleted.')]);
        }

        $onlineExam->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exam deleted.')]);

        return to_route('academics.online-exams.index');
    }

    /**
     * Release the exam to its students.
     */
    public function publish(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $exam = $onlineExam;
        $this->authorizeManage($exam);
        $this->exams->publish($exam);
        $notified = $this->announce($exam);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('Exam published. :count student was told.|Exam published. :count students were told.', $notified, ['count' => $notified])]);

        return back();
    }

    public function unpublish(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->authorizeManage($onlineExam);
        $this->exams->unpublish($onlineExam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The exam is back in draft and hidden from students.')]);

        return back();
    }

    /**
     * Students of a class (optionally one section), for choosing individual examinees.
     */
    public function roster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $year = $this->currentYear();
        $classId = (int) $validated['school_class_id'];
        $sectionId = isset($validated['section_id']) ? (int) $validated['section_id'] : null;

        $class = collect($this->scope->teachingOptions($user, $year))->firstWhere('id', $classId);
        $allowed = $this->scope->isStaff($user) && $class !== null
            && ($sectionId === null || in_array($sectionId, array_column($class['sections'], 'id'), true));
        abort_unless($allowed, 403);

        return response()->json($this->learning->rosterFor($year->id, $classId, $sectionId));
    }

    /* --------------------------------------------------------------- helpers */

    private function authorizeAudience(OnlineExamRequest $request, AcademicYear $year): void
    {
        $allowed = $this->scope->canTeach(
            $request->user(),
            $request->integer('school_class_id'),
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->integer('subject_id'),
            $year,
        );

        if (! $allowed) {
            throw ValidationException::withMessages(['subject_id' => __('You can only set exams for classes and subjects you teach.')]);
        }
    }

    /**
     * Chosen students must be enrolled in the exam's class (and section) this year.
     *
     * @return list<int>
     */
    private function checkedStudents(OnlineExamRequest $request, AcademicYear $year): array
    {
        $ids = $request->studentIds();

        if ($ids === []) {
            return [];
        }

        $valid = Enrollment::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('student_id', $ids)
            ->whereHas('section', fn (Builder $query) => $query->where('school_class_id', $request->integer('school_class_id')))
            ->when($request->filled('section_id'), fn (Builder $query) => $query->where('section_id', $request->integer('section_id')))
            ->pluck('student_id')
            ->map(intval(...))
            ->all();

        if (count(array_diff($ids, $valid)) > 0) {
            throw ValidationException::withMessages(['student_ids' => __('Some chosen students are not in this class or section.')]);
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<int>  $studentIds
     */
    private function guardLockedChanges(OnlineExam $exam, array $settings, array $studentIds): void
    {
        $exam->load('students:id');
        $current = [
            'school_class_id' => $exam->school_class_id,
            'section_id' => $exam->section_id,
            'subject_id' => $exam->subject_id,
            'starts_at' => intdiv($exam->starts_at->getTimestamp(), 60),
            'duration_minutes' => $exam->duration_minutes,
            'total_marks' => (float) $exam->total_marks,
            'passing_marks' => (float) $exam->passing_marks,
            'max_attempts' => $exam->max_attempts,
            'shuffle_questions' => $exam->shuffle_questions,
            'shuffle_options' => $exam->shuffle_options,
            'negative_marking' => $exam->negative_marking,
        ];
        $wanted = [
            'school_class_id' => (int) $settings['school_class_id'],
            'section_id' => isset($settings['section_id']) ? (int) $settings['section_id'] : null,
            'subject_id' => (int) $settings['subject_id'],
            'starts_at' => intdiv(now()->parse((string) $settings['starts_at'])->getTimestamp(), 60),
            'duration_minutes' => (int) $settings['duration_minutes'],
            'total_marks' => (float) $settings['total_marks'],
            'passing_marks' => (float) $settings['passing_marks'],
            'max_attempts' => (int) $settings['max_attempts'],
            'shuffle_questions' => (bool) ($settings['shuffle_questions'] ?? false),
            'shuffle_options' => (bool) ($settings['shuffle_options'] ?? false),
            'negative_marking' => (bool) ($settings['negative_marking'] ?? false),
        ];
        $chosen = $exam->students->pluck('id')->map(intval(...))->sort()->values()->all();
        sort($studentIds);

        if ($current !== $wanted || $chosen !== $studentIds) {
            throw ValidationException::withMessages(['exam' => __('Students have started this exam. Only the title, instructions, end time (later) and rank setting can change now.')]);
        }
    }

    private function announce(OnlineExam $exam): int
    {
        if ($exam->notified_at !== null) {
            return 0;
        }

        $chosen = array_values($exam->students()->pluck('students.id')->map(intval(...))->all());
        $recipients = $this->learning->studentUsers($exam->academic_year_id, $exam->school_class_id, $exam->section_id, $chosen === [] ? null : $chosen);

        Notification::send($recipients, new LearningUpdate(
            'quiz',
            __('New exam: :title', ['title' => $exam->title]),
            __(':subject · starts :when', ['subject' => $exam->subject->name, 'when' => $exam->starts_at->toDayDateTimeString()]),
            route('exams.my.show', $exam, false),
        ));
        $exam->forceFill(['notified_at' => now()])->saveQuietly();

        return $recipients->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $year = $this->currentYear();

        return [
            'options' => $this->scope->teachingOptions($user, $year),
            'yearId' => $year->id,
        ];
    }
}
