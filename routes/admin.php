<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SchoolSwitchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserTwoFactorController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except('show');
    Route::delete('users/{user}/two-factor', [UserTwoFactorController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('users.two-factor.destroy');
    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('schools', SchoolController::class)->except('show');

    Route::post('schools/{school}/switch', [SchoolSwitchController::class, 'store'])->name('schools.switch');
    Route::delete('school-context', [SchoolSwitchController::class, 'destroy'])->name('schools.switch.clear');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('settings/{group}', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:audit_logs.view')
        ->name('audit-logs.index');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('can:audit_logs.view')
        ->name('activity-logs.index');

    Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');
    Route::delete('inquiries/{inquiry}', [InquiryController::class, 'destroy'])->name('inquiries.destroy');
});

Route::middleware(['auth', 'verified', RequireSchoolContext::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::put('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::post('webhooks/{webhook}/rotate', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
    Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->middleware('throttle:10,1')->name('webhooks.test');
});
