<?php

use App\Http\Controllers\Academics\AcademicYearController;
use App\Http\Controllers\Academics\AssignmentController;
use App\Http\Controllers\Academics\AttendanceController;
use App\Http\Controllers\Academics\ExamController;
use App\Http\Controllers\Academics\GradeScaleController;
use App\Http\Controllers\Academics\MarkController;
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
    });
