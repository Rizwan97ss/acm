<?php

use App\Http\Controllers\Operations\HostelController;
use App\Http\Controllers\Operations\InventoryController;
use App\Http\Controllers\Operations\TransportController;
use App\Http\Controllers\People\IdCardController;
use App\Http\Controllers\Records\CertificateController;
use App\Http\Controllers\Records\DocumentController;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::get('verify/{code}', [CertificateController::class, 'verify'])
    ->where('code', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1')
    ->name('certificates.verify');

Route::get('verify/id/{token}', [IdCardController::class, 'verify'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:30,1')
    ->name('id-cards.verify');

Route::middleware(['auth', 'verified', RequireSchoolContext::class])->group(function () {
    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('transport', [TransportController::class, 'index'])->name('transport.index');
        Route::post('transport', [TransportController::class, 'store'])->name('transport.store');
        Route::get('transport/{route}', [TransportController::class, 'show'])->name('transport.show');
        Route::put('transport/{route}', [TransportController::class, 'update'])->name('transport.update');
        Route::delete('transport/{route}', [TransportController::class, 'destroy'])->name('transport.destroy');
        Route::post('transport/{route}/riders', [TransportController::class, 'assign'])->name('transport.assign');
        Route::delete('transport-riders/{assignment}', [TransportController::class, 'unassign'])->name('transport.unassign');

        Route::get('hostels', [HostelController::class, 'index'])->name('hostels.index');
        Route::post('hostels', [HostelController::class, 'store'])->name('hostels.store');
        Route::get('hostels/{hostel}', [HostelController::class, 'show'])->name('hostels.show');
        Route::put('hostels/{hostel}', [HostelController::class, 'update'])->name('hostels.update');
        Route::delete('hostels/{hostel}', [HostelController::class, 'destroy'])->name('hostels.destroy');
        Route::post('hostels/{hostel}/rooms', [HostelController::class, 'storeRoom'])->name('hostels.rooms.store');
        Route::post('hostels/{hostel}/bill', [HostelController::class, 'bill'])->middleware('throttle:10,1')->name('hostels.bill');
        Route::put('hostel-rooms/{room}', [HostelController::class, 'updateRoom'])->name('hostel-rooms.update');
        Route::delete('hostel-rooms/{room}', [HostelController::class, 'destroyRoom'])->name('hostel-rooms.destroy');
        Route::post('hostel-rooms/{room}/residents', [HostelController::class, 'allocate'])->name('hostel-rooms.allocate');
        Route::put('hostel-residents/{allocation}', [HostelController::class, 'move'])->name('hostel-residents.move');
        Route::delete('hostel-residents/{allocation}', [HostelController::class, 'vacate'])->name('hostel-residents.vacate');

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::put('inventory/{item}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::delete('inventory/{item}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        Route::post('inventory/{item}/movements', [InventoryController::class, 'move'])->name('inventory.move');
    });

    Route::prefix('records')->name('records.')->group(function () {
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:30,1')->name('documents.store');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->whereNumber('document')->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
        Route::post('certificates', [CertificateController::class, 'store'])->name('certificates.store');
        Route::put('certificates/{certificate}', [CertificateController::class, 'update'])->name('certificates.update');
        Route::delete('certificates/{certificate}', [CertificateController::class, 'destroy'])->name('certificates.destroy');
        Route::post('certificates/{certificate}/issue', [CertificateController::class, 'issue'])->name('certificates.issue');
        Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->name('certificates.revoke');
        Route::get('certificates/{certificate}/print', [CertificateController::class, 'print'])->whereNumber('certificate')->name('certificates.print');
    });
});
