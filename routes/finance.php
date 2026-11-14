<?php

use App\Http\Controllers\Finance\AccountingController;
use App\Http\Controllers\Finance\ConcessionController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FeeItemController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RequireSchoolContext::class])->group(function () {
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('fee-items', [FeeItemController::class, 'index'])->name('fee-items.index');
        Route::post('fee-items', [FeeItemController::class, 'store'])->name('fee-items.store');
        Route::put('fee-items/{fee_item}', [FeeItemController::class, 'update'])->name('fee-items.update');
        Route::delete('fee-items/{fee_item}', [FeeItemController::class, 'destroy'])->name('fee-items.destroy');

        Route::put('students/{student}/concession', [ConcessionController::class, 'update'])->name('concessions.update');
        Route::delete('students/{student}/concession', [ConcessionController::class, 'destroy'])->name('concessions.destroy');

        Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::post('expenses/{expense}/review', [ExpenseController::class, 'review'])->name('expenses.review');
        Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');

        Route::get('accounting', [AccountingController::class, 'overview'])->name('accounting.overview');
        Route::get('accounting/journal', [AccountingController::class, 'journal'])->name('accounting.journal');
        Route::post('accounting/journal', [AccountingController::class, 'storeJournal'])->name('accounting.journal.store');
    });

    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('leave', [LeaveController::class, 'mine'])->name('leave.mine');
        Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
        Route::post('leave/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
        Route::get('leave/requests', [LeaveController::class, 'requests'])->name('leave.requests');
        Route::post('leave/requests/{leaveRequest}/review', [LeaveController::class, 'review'])->name('leave.review');
        Route::post('leave/types', [LeaveController::class, 'saveType'])->name('leave.types.store');
        Route::put('leave/types/{leaveType}', [LeaveController::class, 'saveType'])->name('leave.types.update');

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('payroll/salaries', [PayrollController::class, 'salaries'])->name('payroll.salaries');
        Route::put('payroll/salaries/{employee}', [PayrollController::class, 'saveSalary'])->name('payroll.salaries.update');
        Route::get('payroll/my-payslips', [PayrollController::class, 'mine'])->name('payroll.mine');
        Route::get('payroll/payslips/{payslip}', [PayrollController::class, 'payslip'])->name('payroll.payslip');
        Route::get('payroll/{run}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{run}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
        Route::post('payroll/{run}/pay', [PayrollController::class, 'pay'])->name('payroll.pay');
    });
});
