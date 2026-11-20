<?php

namespace App\Http\Controllers\Academics\OnlineExams;

use App\Enums\ExamResultsStatus;
use App\Models\OnlineExam;
use App\Models\OnlineExamAnswer;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamQuestion;
use App\Services\Academics\Exams\AnswerMarker;
use App\Services\Academics\Exams\OnlineExamResults;
use App\Services\Academics\Exams\OnlineExamScope;
use App\Services\Academics\Exams\OnlineExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reviewing attempts, marking written answers, and the approve → publish results workflow.
 */
class ExamMarkingController extends OnlineExamController
{
    public function __construct(
        OnlineExamScope $scope,
        private OnlineExamService $exams,
        private OnlineExamResults $results,
        private AnswerMarker $marker,
    ) {
        parent::__construct($scope);
    }

    public function attempt(Request $request, OnlineExam $onlineExam, OnlineExamAttempt $attempt): Response
    {
        $exam = $onlineExam;
        $this->authorizeView($exam);
        abort_unless($attempt->online_exam_id === $exam->id, 404);

        $attempt = $this->exams->expireIfOverdue($attempt->setRelation('exam', $exam));
        $attempt->load(['student:id,first_name,last_name,admission_no', 'answers']);
        $answers = $attempt->answers->keyBy('online_exam_question_id');
        $questions = $exam->questions()->get()->keyBy('id');
        $order = array_column($attempt->layout, 'question');

        return Inertia::render('academics/online-exams/attempt', [
            'exam' => [
                ...$this->present($exam),
            ],
            'attempt' => [
                'id' => $attempt->id,
                'student' => $attempt->student->full_name,
                'admission_no' => $attempt->student->admission_no,
                'attempt_no' => $attempt->attempt_no,
                'status' => $attempt->status->value,
                'status_label' => $attempt->status->label(),
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'max_score' => (float) $attempt->max_score,
                'percentage' => $attempt->percentage !== null ? (float) $attempt->percentage : null,
                'grade' => $attempt->grade,
                'passed' => $attempt->passed,
                'feedback' => $attempt->feedback,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'submitted_by' => $attempt->submitted_by,
            ],
            'items' => array_values(array_filter(array_map(function (int $questionId, int $index) use ($questions, $answers, $exam): ?array {
                /** @var OnlineExamQuestion|null $question */
                $question = $questions->get($questionId);

                if ($question === null) {
                    return null;
                }

                /** @var OnlineExamAnswer|null $answer */
                $answer = $answers->get($questionId);
                [$floor, $ceiling] = $this->marker->range($question, $exam->negative_marking);

                return [
                    'number' => $index + 1,
                    'question' => [
                        'id' => $question->id,
                        'type' => $question->type->value,
                        'type_label' => $question->type->label(),
                        'prompt' => $question->prompt,
                        'marks' => (float) $question->marks,
                        'min_marks' => $floor,
                        'max_marks' => $ceiling,
                        'options' => $question->options ?? [],
                        'answer_key' => $question->answer_key ?? [],
                        'hand_marked' => OnlineExamService::needsHandMarking($question),
                    ],
                    'answer' => $answer === null ? null : [
                        'id' => $answer->id,
                        'value' => $answer->answer,
                        'is_correct' => $answer->is_correct,
                        'marks_awarded' => $answer->marks_awarded !== null ? (float) $answer->marks_awarded : null,
                        'feedback' => $answer->feedback,
                    ],
                ];
            }, $order, array_keys($order)))),
            'can' => [
                'grade' => $this->scope->canManage($request->user(), $exam) && $exam->results_status === ExamResultsStatus::Pending && ! $attempt->isInProgress(),
            ],
        ]);
    }

    public function grade(Request $request, OnlineExam $onlineExam, OnlineExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeManage($onlineExam);
        abort_unless($attempt->online_exam_id === $onlineExam->id, 404);

        $validated = $request->validate([
            'marks' => ['array', 'max:200'],
            'marks.*.marks' => ['nullable', 'numeric', 'min:-1000', 'max:1000'],
            'marks.*.feedback' => ['nullable', 'string', 'max:2000'],
            'feedback' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->exams->grade($request->user(), $attempt->setRelation('exam', $onlineExam), $validated['marks'] ?? [], $validated['feedback'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marks saved.')]);

        return to_route('academics.online-exams.show', $onlineExam);
    }

    public function approve(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->authorizeManage($onlineExam);
        $this->results->approve($onlineExam, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Results approved and marks locked. Publish them when ready.')]);

        return back();
    }

    public function reopen(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->authorizeManage($onlineExam);
        $this->results->reopen($onlineExam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marking reopened.')]);

        return back();
    }

    public function publish(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->authorizeView($onlineExam);
        abort_unless($request->user()->hasPermission('online_exams.publish'), 403);

        $notified = $this->results->publish($onlineExam, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('Results published. :count student or parent was told.|Results published. :count students and parents were told.', $notified, ['count' => $notified])]);

        return back();
    }

    public function withdraw(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->authorizeView($onlineExam);
        abort_unless($request->user()->hasPermission('online_exams.publish'), 403);

        $this->results->withdraw($onlineExam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Results withdrawn. Students can no longer see them.')]);

        return back();
    }
}
