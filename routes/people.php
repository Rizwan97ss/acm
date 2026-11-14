<?php

use App\Http\Controllers\People\AdmissionController;
use App\Http\Controllers\People\EmployeeController;
use App\Http\Controllers\People\GuardianController;
use App\Http\Controllers\People\PortalAccountController;
use App\Http\Controllers\People\StudentController;
use App\Http\Controllers\People\StudentEnrollmentController;
use App\Http\Controllers\People\StudentGuardianController;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RequireSchoolContext::class])
    ->prefix('people')
    ->name('people.')
    ->group(function () {
        Route::resource('students', StudentController::class);
        Route::post('students/{student}/enrollments', [StudentEnrollmentController::class, 'store'])->name('students.enrollments.store');
        Route::post('students/{student}/guardians', [StudentGuardianController::class, 'store'])->name('students.guardians.store');
        Route::delete('students/{student}/guardians/{guardian}', [StudentGuardianController::class, 'destroy'])->name('students.guardians.destroy');
        Route::post('students/{student}/portal-account', [PortalAccountController::class, 'student'])
            ->middleware('throttle:10,1')
            ->name('students.portal-account.store');

        Route::get('guardians/search', [GuardianController::class, 'search'])->name('guardians.search');
        Route::resource('guardians', GuardianController::class)->except('show');
        Route::post('guardians/{guardian}/portal-account', [PortalAccountController::class, 'guardian'])
            ->middleware('throttle:10,1')
            ->name('guardians.portal-account.store');

        Route::resource('staff', EmployeeController::class)->except('show');

        Route::resource('admissions', AdmissionController::class)->except('destroy');
        Route::post('admissions/{admission}/decision', [AdmissionController::class, 'decide'])->name('admissions.decision');
        Route::post('admissions/{admission}/enroll', [AdmissionController::class, 'enroll'])->name('admissions.enroll');
    });
