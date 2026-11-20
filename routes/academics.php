<?php

use App\Http\Controllers\Academics\AcademicYearController;
use App\Http\Controllers\Academics\AssignmentController;
use App\Http\Controllers\Academics\AttendanceController;
use App\Http\Controllers\Academics\ExamController;
use App\Http\Controllers\Academics\GradeScaleController;
use App\Http\Controllers\Academics\MarkController;
use App\Http\Controllers\Academics\OnlineExams\ExamManageController;
use App\Http\Controllers\Academics\OnlineExams\ExamMarkingController;
use App\Http\Controllers\Academics\OnlineExams\ExamQuestionController;
use App\Http\Controllers\Academics\OnlineExams\ExamReportController;
use App\Http\Controllers\Academics\OnlineExams\MyExamController;
use App\Http\Controllers\Academics\OnlineExams\QuestionBankController;
use App\Http\Controllers\Academics\PeriodController;
use App\Http\Controllers\Academics\ProgressController;
use App\Http\Controllers\Academics\ResultController;
use App\Http\Controllers\Academics\SchoolClassController;
use App\Http\Controllers\Academics\SubjectController;
use App\Http\Controllers\Academics\TimetableController;
use App\Http\Controllers\Academics\TimetableEntryController;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RequireSchoolContext::class])
    ->prefix('academics')
    ->name('academics.')
    ->group(function () {
        Route::resource('academic-years', AcademicYearController::class)->except('show');
        Route::post('academic-years/{academic_year}/current', [AcademicYearController::class, 'makeCurrent'])->name('academic-years.current');

        Route::resource('subjects', SubjectController::class)->except('show');

        Route::resource('classes', SchoolClassController::class)
            ->except('show')
            ->parameters(['classes' => 'class']);

        Route::get('periods', [PeriodController::class, 'edit'])->name('periods.edit');
        Route::put('periods', [PeriodController::class, 'update'])->name('periods.update');

        Route::get('timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::post('timetable/entries', [TimetableEntryController::class, 'store'])->name('timetable.entries.store');
        Route::put('timetable/entries/{entry}', [TimetableEntryController::class, 'update'])->name('timetable.entries.update');
        Route::delete('timetable/entries/{entry}', [TimetableEntryController::class, 'destroy'])->name('timetable.entries.destroy');

        Route::resource('grade-scales', GradeScaleController::class)->except('show');

        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('attendance/register', [AttendanceController::class, 'register'])->name('attendance.register');

        Route::resource('exams', ExamController::class);
        Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
        Route::delete('exams/{exam}/publish', [ExamController::class, 'unpublish'])->name('exams.unpublish');
        Route::post('exams/{exam}/papers/generate', [ExamController::class, 'generatePapers'])->name('exams.papers.generate');
        Route::post('exams/{exam}/papers', [ExamController::class, 'storePaper'])->name('exams.papers.store');
        Route::put('exams/{exam}/papers/{paper}', [ExamController::class, 'updatePaper'])->name('exams.papers.update');
        Route::delete('exams/{exam}/papers/{paper}', [ExamController::class, 'destroyPaper'])->name('exams.papers.destroy');
        Route::get('exams/{exam}/papers/{paper}/sections/{section}/marks', [MarkController::class, 'edit'])->name('exams.marks.edit');
        Route::put('exams/{exam}/papers/{paper}/sections/{section}/marks', [MarkController::class, 'update'])->name('exams.marks.update');
        Route::get('exams/{exam}/sections/{section}/results', [ResultController::class, 'section'])->name('exams.results');
        Route::get('exams/{exam}/students/{student}/report-card', [ResultController::class, 'reportCard'])->name('exams.report-card');

        Route::resource('assignments', AssignmentController::class);
        Route::post('assignments/{assignment}/submission', [AssignmentController::class, 'submit'])->name('assignments.submit');
        Route::put('assignments/{assignment}/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->name('assignments.grade');
        Route::delete('assignments/{assignment}/files/{file}', [AssignmentController::class, 'destroyFile'])->name('assignments.files.destroy');

        Route::get('progress', ProgressController::class)->name('progress');

        /*
        | Online exams (staff)
        */
        Route::get('online-exams/roster', [ExamManageController::class, 'roster'])->name('online-exams.roster');
        Route::get('online-exam-reports', [ExamReportController::class, 'overview'])->name('online-exam-reports.index');
        Route::get('online-exam-reports/export', [ExamReportController::class, 'exportOverview'])->middleware('throttle:20,1')->name('online-exam-reports.export');
        Route::resource('online-exams', ExamManageController::class);
        Route::post('online-exams/{online_exam}/publish', [ExamManageController::class, 'publish'])->name('online-exams.publish');
        Route::delete('online-exams/{online_exam}/publish', [ExamManageController::class, 'unpublish'])->name('online-exams.unpublish');

        Route::post('online-exams/{online_exam}/questions', [ExamQuestionController::class, 'store'])->name('online-exams.questions.store');
        Route::post('online-exams/{online_exam}/questions/import', [ExamQuestionController::class, 'import'])->name('online-exams.questions.import');
        Route::put('exam-questions/{question}', [ExamQuestionController::class, 'update'])->name('exam-questions.update');
        Route::delete('exam-questions/{question}', [ExamQuestionController::class, 'destroy'])->name('exam-questions.destroy');
        Route::post('exam-questions/{question}/move', [ExamQuestionController::class, 'move'])->name('exam-questions.move');
        Route::post('exam-questions/{question}/bank', [ExamQuestionController::class, 'saveToBank'])->name('exam-questions.bank');

        Route::get('online-exams/{online_exam}/attempts/{attempt}', [ExamMarkingController::class, 'attempt'])->name('online-exams.attempts.show');
        Route::put('online-exams/{online_exam}/attempts/{attempt}', [ExamMarkingController::class, 'grade'])->name('online-exams.attempts.grade');
        Route::post('online-exams/{online_exam}/results/approve', [ExamMarkingController::class, 'approve'])->name('online-exams.results.approve');
        Route::delete('online-exams/{online_exam}/results/approve', [ExamMarkingController::class, 'reopen'])->name('online-exams.results.reopen');
        Route::post('online-exams/{online_exam}/results/publish', [ExamMarkingController::class, 'publish'])->name('online-exams.results.publish');
        Route::delete('online-exams/{online_exam}/results/publish', [ExamMarkingController::class, 'withdraw'])->name('online-exams.results.withdraw');
        Route::get('online-exams/{online_exam}/report', [ExamReportController::class, 'show'])->name('online-exams.report');
        Route::get('online-exams/{online_exam}/export', [ExamReportController::class, 'export'])->middleware('throttle:20,1')->name('online-exams.export');

        Route::get('question-bank', [QuestionBankController::class, 'index'])->name('question-bank.index');
        Route::post('question-bank', [QuestionBankController::class, 'store'])->name('question-bank.store');
        Route::put('question-bank/{bank_question}', [QuestionBankController::class, 'update'])->name('question-bank.update');
        Route::delete('question-bank/{bank_question}', [QuestionBankController::class, 'destroy'])->name('question-bank.destroy');
        Route::get('online-exams/{online_exam}/bank', [QuestionBankController::class, 'pick'])->name('online-exams.bank');
    });

/*
| Online exams (students sit them; students and parents see published results)
*/
Route::middleware(['auth', 'verified', RequireSchoolContext::class])
    ->prefix('my-exams')
    ->name('exams.my.')
    ->group(function () {
        Route::get('/', [MyExamController::class, 'index'])->name('index');
        Route::get('{online_exam}', [MyExamController::class, 'show'])->name('show');
        Route::post('{online_exam}/start', [MyExamController::class, 'start'])->middleware('throttle:10,1')->name('start');
        Route::get('{online_exam}/attempts/{attempt}', [MyExamController::class, 'take'])->name('take');
        Route::put('{online_exam}/attempts/{attempt}/answers', [MyExamController::class, 'save'])->middleware('throttle:60,1')->name('save');
        Route::post('{online_exam}/attempts/{attempt}', [MyExamController::class, 'submit'])->middleware('throttle:10,1')->name('submit');
    });
