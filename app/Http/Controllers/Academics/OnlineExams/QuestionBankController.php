<?php

namespace App\Http\Controllers\Academics\OnlineExams;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Http\Requests\Academics\BankQuestionRequest;
use App\Models\BankQuestion;
use App\Models\OnlineExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reusable questions by subject (and optionally class). Teachers see and manage the bank
 * for the subjects they teach; supervisors ("online_exams.approve") for every subject.
 * Answer keys stay encrypted at rest and are only sent to staff who may edit the item.
 */
class QuestionBankController extends OnlineExamController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermission('question_bank.view') && $this->scope->isStaff($user), 403);
        $subjectIds = $this->subjectIds($user);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string'],
            'difficulty' => ['nullable', 'string'],
        ]);

        $questions = $this->visible(BankQuestion::query(), $subjectIds)
            ->with(['subject:id,name', 'schoolClass:id,name', 'author:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('prompt', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('topic', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('school_class_id', $classId))
            ->when(QuestionType::tryFrom((string) ($filters['type'] ?? '')), fn (Builder $query, QuestionType $type) => $query->where('type', $type))
            ->when(QuestionDifficulty::tryFrom((string) ($filters['difficulty'] ?? '')), fn (Builder $query, QuestionDifficulty $difficulty) => $query->where('difficulty', $difficulty))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $options = $this->scope->teachingOptions($user, $this->currentYear());

        return Inertia::render('academics/question-bank/index', [
            'questions' => [
                'data' => $questions->getCollection()->map(fn (BankQuestion $question): array => [
                    ...$this->presentQuestion($question),
                    'answer_key' => $this->canEdit($user, $question) ? ($question->answer_key ?? []) : [],
                    'can_edit' => $this->canEdit($user, $question),
                ])->values(),
                'meta' => $this->meta($questions),
            ],
            'filters' => $filters,
            'subjects' => collect($options)->flatMap(fn (array $class) => $class['subjects'])->unique('id')->sortBy('name')->values(),
            'classes' => array_map(fn (array $class): array => ['id' => $class['id'], 'name' => $class['name']], $options),
            'questionTypes' => QuestionType::options(),
            'difficulties' => QuestionDifficulty::options(),
            'can' => ['create' => $user->hasPermission('question_bank.create')],
        ]);
    }

    /**
     * JSON: bank questions an exam can use (its subject; its class or any class).
     */
    public function pick(Request $request, OnlineExam $onlineExam): JsonResponse
    {
        $this->authorizeManage($onlineExam);
        abort_unless($request->user()->hasPermission('question_bank.view'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'difficulty' => ['nullable', 'string'],
        ]);

        $questions = BankQuestion::query()
            ->where('subject_id', $onlineExam->subject_id)
            ->where(fn (Builder $query) => $query->whereNull('school_class_id')->orWhere('school_class_id', $onlineExam->school_class_id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('prompt', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('topic', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->when(QuestionDifficulty::tryFrom((string) ($filters['difficulty'] ?? '')), fn (Builder $query, QuestionDifficulty $difficulty) => $query->where('difficulty', $difficulty))
            ->whereNotIn('id', $onlineExam->questions()->whereNotNull('bank_question_id')->select('bank_question_id'))
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($questions->map(fn (BankQuestion $question): array => [
            'id' => $question->id,
            'type_label' => $question->type->label(),
            'difficulty' => $question->difficulty->value,
            'difficulty_label' => $question->difficulty->label(),
            'prompt' => Str::limit($question->prompt, 200),
            'marks' => (float) $question->marks,
            'topic' => $question->topic,
        ])->values());
    }

    public function store(BankQuestionRequest $request): RedirectResponse
    {
        $this->guardSubject($request->user(), $request->integer('subject_id'));

        BankQuestion::query()->create([...$request->bankPayload(), 'created_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question added to the bank.')]);

        return back();
    }

    public function update(BankQuestionRequest $request, BankQuestion $bankQuestion): RedirectResponse
    {
        abort_unless($this->canEdit($request->user(), $bankQuestion), 403);
        $this->guardSubject($request->user(), $request->integer('subject_id'));

        $bankQuestion->update($request->bankPayload());

        return back();
    }

    public function destroy(Request $request, BankQuestion $bankQuestion): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('question_bank.delete') && $this->canEdit($request->user(), $bankQuestion), 403);

        $bankQuestion->delete();

        return back();
    }

    /* --------------------------------------------------------------- helpers */

    /**
     * Subjects the user may use the bank for; null when they supervise all subjects.
     *
     * @return list<int>|null
     */
    private function subjectIds(User $user): ?array
    {
        if ($this->scope->supervises($user)) {
            return null;
        }

        return array_values(collect($this->scope->teachingOptions($user, $this->currentYear()))
            ->flatMap(fn (array $class) => array_column($class['subjects'], 'id'))
            ->unique()
            ->map(intval(...))
            ->all());
    }

    /**
     * @param  Builder<BankQuestion>  $query
     * @param  list<int>|null  $subjectIds
     * @return Builder<BankQuestion>
     */
    private function visible(Builder $query, ?array $subjectIds): Builder
    {
        return $subjectIds === null ? $query : $query->whereIn('subject_id', $subjectIds);
    }

    private function canEdit(User $user, BankQuestion $question): bool
    {
        if (! $user->hasPermission('question_bank.edit') || ! $this->scope->isStaff($user)) {
            return false;
        }

        $subjectIds = $this->subjectIds($user);

        return $question->created_by === $user->id || $subjectIds === null || in_array($question->subject_id, $subjectIds, true);
    }

    private function guardSubject(User $user, int $subjectId): void
    {
        abort_unless($this->scope->isStaff($user), 403);
        $subjectIds = $this->subjectIds($user);

        if ($subjectIds !== null && ! in_array($subjectId, $subjectIds, true)) {
            throw ValidationException::withMessages(['subject_id' => __('You can only add questions for subjects you teach.')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentQuestion(BankQuestion $question): array
    {
        return [
            'id' => $question->id,
            'subject_id' => $question->subject_id,
            'subject' => $question->subject->name,
            'school_class_id' => $question->school_class_id,
            'class' => $question->schoolClass?->name,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'difficulty' => $question->difficulty->value,
            'difficulty_label' => $question->difficulty->label(),
            'prompt' => $question->prompt,
            'options' => $question->options ?? [],
            'marks' => (float) $question->marks,
            'negative_marks' => (float) $question->negative_marks,
            'explanation' => $question->explanation,
            'topic' => $question->topic,
            'author' => $question->author?->name,
        ];
    }
}
