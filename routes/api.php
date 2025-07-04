<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Account Segments
Route::prefix('segments')->group(function () {
    Route::get('structure', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'getStructure']);
    Route::post('structure/save', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'saveSegment']);
    Route::delete('structure/{segment}', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'deleteSegment']);

    Route::get('validate', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'validateStructure']);
});

// Accounts (Chart of Accounts)
Route::prefix('accounts')->group(function () {
    Route::get('natural-accounts', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'getNaturalAccountsValues']);
    Route::post('create-natural-account', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'createNaturalAccountValue']);
    Route::delete('delete-account-value', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'deleteValue']);
});

// GL Transactions
Route::prefix('gl-transactions')->group(function () {
    Route::get('/', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'index']);
    Route::get('/{transaction_id}', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'show']);
    Route::post('/', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'store']);
    Route::post('/{transaction_id}/post', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'post']);
    Route::get('/account/{account_id}', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'byAccount']);
    Route::get('/reports/unbalanced', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'unbalanced']);
    Route::post('/validate', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'validateStatus']);
});

Route::prefix('taxes')->group(function () {
    Route::post('/sync-many', [\Condoedge\Finance\Http\Controllers\Api\TaxesController::class, 'syncTaxes'])
    ->name('invoice-taxes.sync-many');

    Route::post('/add', [\Condoedge\Finance\Http\Controllers\Api\TaxesController::class, 'addTax'])
        ->name('invoice-taxes.add');
});

Route::prefix('invoices')->group(function () {
    Route::post('/create', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'createInvoice']);
    Route::put('/update', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'updateInvoice']);

    Route::post('/add-invoice-detail', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'saveInvoiceDetail'])
        ->name('invoice-details.save');
});

Route::prefix('customers')->group(function () {
    Route::post('/upsert', [\Condoedge\Finance\Http\Controllers\Api\CustomersController::class, 'saveCustomer']);
    Route::post('/create-from-another-model', [\Condoedge\Finance\Http\Controllers\Api\CustomersController::class, 'createFromCustomableModel']);
});

Route::prefix('payments')->group(function () {
    Route::post('/create', [\Condoedge\Finance\Http\Controllers\Api\PaymentsController::class, 'createCustomerPayment'])
        ->name('payments.create');

    Route::post('/create-for-invoice', [\Condoedge\Finance\Http\Controllers\Api\PaymentsController::class, 'createCustomerPaymentForInvoice'])
        ->name('payments.create-for-invoice');
});
