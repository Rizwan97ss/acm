<?php

use App\Http\Controllers\ContactInquiryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\SessionHeartbeatController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');
Route::post('contact', [ContactInquiryController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');

Route::get('session/heartbeat', SessionHeartbeatController::class)
    ->middleware(['auth', 'throttle:12,1'])
    ->name('session.heartbeat');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('help', HelpController::class)->name('help');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/academics.php';
require __DIR__.'/people.php';
require __DIR__.'/finance.php';
require __DIR__.'/analytics.php';
require __DIR__.'/communication.php';
require __DIR__.'/library.php';
require __DIR__.'/operations.php';
require __DIR__.'/imports.php';
