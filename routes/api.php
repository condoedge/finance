<?php

use Illuminate\Support\Facades\Route;
use Condoedge\Finance\Http\Controllers\GlTransactionController;

Route::post('/payments/create', [\Condoedge\Finance\Http\PaymentsController::class, 'createCustomerPayment'])
    ->name('payments.create');

Route::post('/payments/create-for-invoice', [\Condoedge\Finance\Http\PaymentsController::class, 'createCustomerPaymentForInvoice'])
    ->name('payments.create-for-invoice');

Route::post('/customers', [\Condoedge\Finance\Http\CustomersController::class, 'saveCustomer'])
    ->name('customers.save');

Route::post('/customers/create-from-another-model', [\Condoedge\Finance\Http\CustomersController::class, 'createFromCustomableModel'])
    ->name('customers.create-from-another-model');

Route::post('/invoices/create', [\Condoedge\Finance\Http\InvoicesController::class, 'createInvoice'])
    ->name('invoices.create');

Route::post('/invoices/update', [\Condoedge\Finance\Http\InvoicesController::class, 'updateInvoice'])
    ->name('invoices.update');

Route::post('/invoice-details/save', [\Condoedge\Finance\Http\InvoicesController::class, 'saveInvoiceDetail'])
    ->name('invoice-details.save');

Route::post('/invoice-taxes/sync-many',  [\Condoedge\Finance\Http\TaxesController::class, 'syncTaxes'])
    ->name('invoice-taxes.sync-many');

Route::post('/invoice-taxes/add',  [\Condoedge\Finance\Http\TaxesController::class, 'addTax'])
    ->name('invoice-taxes.add');

// GL Transaction Routes
Route::prefix('gl')->name('gl.')->group(function () {
    Route::get('transactions', [GlTransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [GlTransactionController::class, 'store'])->name('transactions.store');
    Route::get('transactions/{transactionId}', [GlTransactionController::class, 'show'])->name('transactions.show');
    Route::put('transactions/{transactionId}', [GlTransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transactionId}', [GlTransactionController::class, 'destroy'])->name('transactions.destroy');
    
    // Transaction operations
    Route::post('transactions/{transactionId}/post', [GlTransactionController::class, 'post'])->name('transactions.post');
    Route::post('transactions/{transactionId}/reverse', [GlTransactionController::class, 'reverse'])->name('transactions.reverse');
    
    // Reports
    Route::get('trial-balance', [GlTransactionController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('accounts/{accountId}/balance', [GlTransactionController::class, 'accountBalance'])->name('accounts.balance');
});