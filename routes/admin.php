<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SchoolSwitchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except('show');
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
});
