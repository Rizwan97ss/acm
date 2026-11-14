<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
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
