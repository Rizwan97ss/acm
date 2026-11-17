<?php

use App\Http\Controllers\ImportController;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RequireSchoolContext::class])
    ->prefix('imports')
    ->name('imports.')
    ->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::get('{type}', [ImportController::class, 'show'])->name('show');
        Route::get('{type}/template', [ImportController::class, 'template'])->name('template');
        Route::post('{type}/preview', [ImportController::class, 'preview'])->middleware('throttle:20,1')->name('preview');
        Route::delete('{type}/preview', [ImportController::class, 'destroy'])->name('discard');
        Route::post('{type}', [ImportController::class, 'store'])->middleware('throttle:10,1')->name('store');
    });
