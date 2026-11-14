<?php

use App\Http\Controllers\Analytics\InsightsController;
use App\Http\Controllers\Analytics\ReportController;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RequireSchoolContext::class])->group(function () {
    Route::get('insights', [InsightsController::class, 'index'])->name('analytics.insights');
    Route::get('insights/report', [InsightsController::class, 'report'])->middleware('throttle:10,1')->name('analytics.report');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}.csv', [ReportController::class, 'export'])->middleware('throttle:30,1')->where('report', '[a-z_]+')->name('reports.export');
});
