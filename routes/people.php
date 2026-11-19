<?php

use App\Http\Controllers\People\AdmissionController;
use App\Http\Controllers\People\EmployeeController;
use App\Http\Controllers\People\GuardianController;
use App\Http\Controllers\People\IdCardController;
use App\Http\Controllers\People\PhotoController;
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
        Route::get('id-cards', [IdCardController::class, 'index'])->name('id-cards.index');
        Route::get('id-cards/search', [IdCardController::class, 'search'])->middleware('throttle:60,1')->name('id-cards.search');
        Route::get('id-cards/print', [IdCardController::class, 'print'])->name('id-cards.print');
        Route::post('id-cards/{type}/{id}/reissue', [IdCardController::class, 'reissue'])->whereIn('type', ['students', 'staff', 'parents'])->whereNumber('id')->name('id-cards.reissue');

        Route::get('photos/{kind}/{id}', [PhotoController::class, 'show'])->whereIn('kind', PhotoController::KINDS)->whereNumber('id')->name('photos.show');
        Route::post('photos/{kind}/{id}', [PhotoController::class, 'update'])->whereIn('kind', PhotoController::KINDS)->whereNumber('id')->middleware('throttle:30,1')->name('photos.update');
        Route::delete('photos/{kind}/{id}', [PhotoController::class, 'destroy'])->whereIn('kind', PhotoController::KINDS)->whereNumber('id')->name('photos.destroy');

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
