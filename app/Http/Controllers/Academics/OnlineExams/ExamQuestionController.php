<?php

namespace App\Http\Controllers\Academics\OnlineExams;

use App\Http\Requests\Academics\ExamQuestionRequest;
use App\Models\BankQuestion;
use App\Models\OnlineExam;
use App\Models\OnlineExamQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The exam's question paper: add, edit, reorder and remove questions, pull questions
 * from the question bank, or save a question to it. Questions are frozen once a student
 * has started, so every student is marked against the same paper.
 */
class ExamQuestionController extends OnlineExamController
{
    public const int MAX_QUESTIONS = 200;

    public function store(ExamQuestionRequest $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->guardEditable($onlineExam);
        $this->guardCount($onlineExam, 1);

        $onlineExam->questions()->create([
            ...$request->payload(),
            'school_id' => $onlineExam->school_id,
            'position' => (int) $onlineExam->questions()->max('position') + 1,
        ]);

        return back();
    }

    public function update(ExamQuestionRequest $request, OnlineExamQuestion $question): RedirectResponse
    {
        $this->guardEditable($question->exam);
        $question->update($request->payload());

        return back();
    }

    public function destroy(OnlineExamQuestion $question): RedirectResponse
    {
        $this->guardEditable($question->exam);
        $question->delete();

        return back();
    }

    public function move(Request $request, OnlineExamQuestion $question): RedirectResponse
    {
        $this->guardEditable($question->exam);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $neighbour = OnlineExamQuestion::query()
            ->where('online_exam_id', $question->online_exam_id)
            ->whereKeyNot($question->id)
            ->where('position', $direction === 'up' ? '<=' : '>=', $question->position)
            ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
            ->orderBy('id', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if ($neighbour !== null) {
            DB::transaction(function () use ($question, $neighbour, $direction): void {
                $position = $question->position;
                $other = $neighbour->position === $position ? max(0, $position + ($direction === 'up' ? -1 : 1)) : $neighbour->position;
                $question->forceFill(['position' => $other])->save();
                $neighbour->forceFill(['position' => $position])->save();
            });
        }

        return back();
    }

    /**
     * Copy chosen bank questions (of the exam's subject) onto the paper.
     */
    public function import(Request $request, OnlineExam $onlineExam): RedirectResponse
    {
        $this->guardEditable($onlineExam);
        abort_unless($request->user()->hasPermission('question_bank.view'), 403);

        $ids = $request->validate([
            'bank_question_ids' => ['required', 'array', 'min:1', 'max:100'],
            'bank_question_ids.*' => ['integer', 'distinct'],
        ])['bank_question_ids'];

        $items = BankQuestion::query()
            ->whereIn('id', $ids)
            ->where('subject_id', $onlineExam->subject_id)
            ->where(fn (Builder $query) => $query->whereNull('school_class_id')->orWhere('school_class_id', $onlineExam->school_class_id))
            ->orderBy('id')
            ->get();

        if ($items->count() !== count($ids)) {
            throw ValidationException::withMessages(['bank_question_ids' => __('Some questions are not in the bank for this subject and class.')]);
        }

        $this->guardCount($onlineExam, $items->count());

        DB::transaction(function () use ($onlineExam, $items): void {
            $position = (int) $onlineExam->questions()->max('position');

            foreach ($items as $item) {
                $onlineExam->questions()->create([
                    'school_id' => $onlineExam->school_id,
                    'bank_question_id' => $item->id,
                    'type' => $item->type,
                    'prompt' => $item->prompt,
                    'options' => $item->options,
                    'answer_key' => $item->answer_key,
                    'marks' => $item->marks,
                    'negative_marks' => $item->negative_marks,
                    'explanation' => $item->explanation,
                    'position' => ++$position,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count question added from the bank.|:count questions added from the bank.', $items->count(), ['count' => $items->count()])]);

        return back();
    }

    /**
     * Keep a copy of this question in the bank for future exams.
     */
    public function saveToBank(Request $request, OnlineExamQuestion $question): RedirectResponse
    {
        $exam = $question->exam;
        $this->authorizeView($exam);
        abort_unless($request->user()->hasPermission('question_bank.create'), 403);

        BankQuestion::query()->create([
            'school_id' => $exam->school_id,
            'subject_id' => $exam->subject_id,
            'school_class_id' => $exam->school_class_id,
            'type' => $question->type,
            'difficulty' => 'medium',
            'prompt' => $question->prompt,
            'options' => $question->options,
            'answer_key' => $question->answer_key,
            'marks' => $question->marks,
            'negative_marks' => $question->negative_marks,
            'explanation' => $question->explanation,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved to the question bank.')]);

        return back();
    }

    private function guardEditable(OnlineExam $exam): void
    {
        $this->authorizeManage($exam);

        if ($exam->isLocked()) {
            throw ValidationException::withMessages(['question' => __('Students have already started this exam, so its questions can no longer change.')]);
        }
    }

    private function guardCount(OnlineExam $exam, int $adding): void
    {
        if ($exam->questions()->count() + $adding > self::MAX_QUESTIONS) {
            throw ValidationException::withMessages(['question' => __('An exam can have at most :max questions.', ['max' => self::MAX_QUESTIONS])]);
        }
    }
}
