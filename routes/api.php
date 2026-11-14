<?php

use App\Enums\ApiAbility;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TimetableController;
use App\Http\Middleware\EnsureApiAccountActive;
use App\Http\Middleware\ResolveCurrentSchool;
use Illuminate\Support\Facades\Route;

/*
| Public API v1. Bearer tokens come from mobile sign-in or Settings → API tokens. Every
| endpoint applies the token owner's RBAC permissions and portal restrictions, on top
| of the token's scopes.
*/
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('auth.token.store');

    Route::middleware(['auth:sanctum', ResolveCurrentSchool::class, EnsureApiAccountActive::class, 'throttle:api'])->group(function (): void {
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');
        Route::get('me', ProfileController::class)->name('me');

        Route::middleware('ability:'.ApiAbility::People->value)->group(function (): void {
            Route::get('students', [StudentController::class, 'index'])->name('students.index');
            Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
        });

        Route::middleware('ability:'.ApiAbility::Academics->value)->group(function (): void {
            Route::get('timetable', TimetableController::class)->name('timetable');
            Route::get('students/{student}/attendance', [StudentController::class, 'attendance'])->name('students.attendance');
        });

        Route::middleware('ability:'.ApiAbility::Finance->value)->group(function (): void {
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        });

        Route::middleware('ability:'.ApiAbility::Communication->value)->group(function (): void {
            Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::get('events', [EventController::class, 'index'])->name('events.index');
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        });
    });
});
