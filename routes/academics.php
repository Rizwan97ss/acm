<?php

use App\Http\Controllers\Academics\AcademicYearController;
use App\Http\Controllers\Academics\PeriodController;
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
    });
