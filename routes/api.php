<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Account Segments
    Route::prefix('segments')->group(function () {
        Route::get('structure', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'getStructure']);
        Route::post('structure', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'createSegment']);
        Route::put('structure/{segment}', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'updateSegment']);
        Route::delete('structure/{segment}', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'deleteSegment']);
        
        Route::get('values/{position}', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'getValues']);
        Route::post('values', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'createValue']);
        Route::put('values/{value}', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'updateValue']);
        Route::delete('values/{value}', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'deleteValue']);
        Route::post('values/bulk-import', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'bulkImportValues']);
        
        Route::get('validate', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'validateStructure']);
    });
    
    // Accounts (Chart of Accounts)
    Route::prefix('accounts')->group(function () {
        Route::get('/', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'index']);
        Route::get('/{account_id}', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'show']);
        Route::post('/', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'store']);
        Route::put('/{id}', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'update']);
        Route::get('/{account_id}/balance', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'balance']);
        Route::get('/reports/trial-balance', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'trialBalance']);
        Route::post('/bulk-create', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'bulkCreate']);
        Route::post('/search-pattern', [\Condoedge\Finance\Http\Controllers\Api\AccountController::class, 'searchByPattern']);
    });
    
    // GL Transactions
    Route::prefix('gl-transactions')->group(function () {
        Route::get('/', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'index']);
        Route::get('/{transaction_id}', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'show']);
        Route::post('/', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'store']);
        Route::put('/{transaction_id}', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'update']);
        Route::post('/{transaction_id}/post', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'post']);
        Route::get('/account/{account_id}', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'byAccount']);
        Route::get('/reports/unbalanced', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'unbalanced']);
        Route::post('/validate', [\Condoedge\Finance\Http\Controllers\Api\GlTransactionController::class, 'validate']);
    });
    
    // Company Default Accounts
    Route::prefix('default-accounts')->group(function () {
        Route::get('/', [\Condoedge\Finance\Http\Controllers\Api\CompanyDefaultAccountController::class, 'index']);
        Route::get('/{setting_name}', [\Condoedge\Finance\Http\Controllers\Api\CompanyDefaultAccountController::class, 'show']);
        Route::post('/', [\Condoedge\Finance\Http\Controllers\Api\CompanyDefaultAccountController::class, 'store']);
        Route::put('/{setting_name}', [\Condoedge\Finance\Http\Controllers\Api\CompanyDefaultAccountController::class, 'update']);
        Route::delete('/{setting_name}', [\Condoedge\Finance\Http\Controllers\Api\CompanyDefaultAccountController::class, 'destroy']);
        Route::post('/bulk-set', [\Condoedge\Finance\Http\Controllers\Api\CompanyDefaultAccountController::class, 'bulkSet']);
    });
    
    // Legacy endpoints (for backward compatibility)
    Route::prefix('invoices')->group(function () {
        Route::get('/', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'index']);
        Route::get('/{id}', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'show']);
        Route::post('/', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'store']);
        Route::put('/{id}', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'update']);
        Route::delete('/{id}', [\Condoedge\Finance\Http\Controllers\Api\InvoicesController::class, 'destroy']);
    });
    
    Route::prefix('customers')->group(function () {
        Route::get('/', [\Condoedge\Finance\Http\Controllers\Api\CustomersController::class, 'index']);
        Route::get('/{id}', [\Condoedge\Finance\Http\Controllers\Api\CustomersController::class, 'show']);
        Route::post('/', [\Condoedge\Finance\Http\Controllers\Api\CustomersController::class, 'store']);
        Route::put('/{id}', [\Condoedge\Finance\Http\Controllers\Api\CustomersController::class, 'update']);
        Route::delete('/{id}', [\Condoedge\Finance\Http\Controllers\Api\CustomersController::class, 'destroy']);
    });
    
    Route::prefix('payments')->group(function () {
        Route::get('/', [\Condoedge\Finance\Http\Controllers\Api\PaymentsController::class, 'index']);
        Route::get('/{id}', [\Condoedge\Finance\Http\Controllers\Api\PaymentsController::class, 'show']);
        Route::post('/', [\Condoedge\Finance\Http\Controllers\Api\PaymentsController::class, 'store']);
        Route::post('/apply', [\Condoedge\Finance\Http\Controllers\Api\PaymentsController::class, 'applyToInvoice']);
    });
});
