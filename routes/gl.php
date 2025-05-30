<?php

use Illuminate\Support\Facades\Route;
use Condoedge\Finance\Http\Controllers\GL\FiscalPeriodController;
use Condoedge\Finance\Http\Controllers\GL\ChartOfAccountsController;
use Condoedge\Finance\Http\Controllers\GL\GlTransactionController;

/*
|--------------------------------------------------------------------------
| GL Module API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/gl')->middleware(['api'])->group(function () {
    
    // Fiscal Period Management
    Route::prefix('fiscal')->group(function () {
        Route::get('setup', [FiscalPeriodController::class, 'getFiscalYearSetup']);
        Route::post('setup', [FiscalPeriodController::class, 'setFiscalYearSetup']);
        Route::post('periods', [FiscalPeriodController::class, 'createFiscalPeriods']);
        Route::get('periods', [FiscalPeriodController::class, 'getFiscalPeriods']);
        Route::get('periods/{periodId}', [FiscalPeriodController::class, 'getPeriodStatus']);
        Route::put('periods/{periodId}', [FiscalPeriodController::class, 'updateFiscalPeriod']);
        Route::post('periods/{periodId}/close', [FiscalPeriodController::class, 'closePeriod']);
        Route::post('periods/{periodId}/open', [FiscalPeriodController::class, 'openPeriod']);
        Route::get('open-periods', [FiscalPeriodController::class, 'getOpenPeriods']);
    });

    // Chart of Accounts Management
    Route::prefix('accounts')->group(function () {
        // Account Structure
        Route::post('structure', [ChartOfAccountsController::class, 'setupAccountStructure']);
        Route::get('structure', [ChartOfAccountsController::class, 'getAccountStructure']);
        
        // Segment Values
        Route::post('segments/values', [ChartOfAccountsController::class, 'createSegmentValue']);
        Route::get('segments/{segmentNumber}/values', [ChartOfAccountsController::class, 'getSegmentValues']);
        
        // GL Accounts
        Route::get('/', [ChartOfAccountsController::class, 'getChartOfAccounts']);
        Route::post('/', [ChartOfAccountsController::class, 'createGlAccount']);
        Route::put('/{accountId}', [ChartOfAccountsController::class, 'updateGlAccount']);
        Route::post('/{accountId}/disable', [ChartOfAccountsController::class, 'disableAccount']);
        Route::post('/{accountId}/enable', [ChartOfAccountsController::class, 'enableAccount']);
        Route::get('/{accountId}/balance', [ChartOfAccountsController::class, 'getAccountBalance']);
        
        // Utility endpoints
        Route::get('for-selection', [ChartOfAccountsController::class, 'getAccountsForSelection']);
        Route::get('trial-balance', [ChartOfAccountsController::class, 'getTrialBalance']);
        Route::post('import', [ChartOfAccountsController::class, 'importChartOfAccounts']);
        
        // Default Accounts
        Route::get('defaults', [ChartOfAccountsController::class, 'getDefaultAccounts']);
        Route::post('defaults', [ChartOfAccountsController::class, 'setDefaultAccount']);
    });

    // GL Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [GlTransactionController::class, 'index']);
        Route::get('/{transactionId}', [GlTransactionController::class, 'show']);
        Route::post('/manual', [GlTransactionController::class, 'createManualTransaction']);
        Route::post('/system', [GlTransactionController::class, 'createSystemTransaction']);
        Route::put('/{transactionId}', [GlTransactionController::class, 'update']);
        Route::delete('/{transactionId}', [GlTransactionController::class, 'destroy']);
        Route::post('/{transactionId}/reverse', [GlTransactionController::class, 'reverse']);
        Route::get('/{transactionId}/validate-balance', [GlTransactionController::class, 'validateBalance']);
        Route::get('/next-number', [GlTransactionController::class, 'getNextTransactionNumber']);
    });
});

/*
|--------------------------------------------------------------------------
| GL Module Web Routes
|--------------------------------------------------------------------------
*/

Route::prefix('gl')->middleware(['web'])->group(function () {
    
    // Fiscal Setup Routes
    Route::get('fiscal-setup', function () {
        return view('finance::gl.fiscal-setup');
    })->name('gl.fiscal-setup');
    
    // Chart of Accounts Routes
    Route::get('chart-of-accounts', function () {
        return view('finance::gl.chart-of-accounts');
    })->name('gl.chart-of-accounts');
    
    Route::get('account-structure', function () {
        return view('finance::gl.account-structure');
    })->name('gl.account-structure');
    
    // GL Transactions Routes
    Route::get('transactions', function () {
        return view('finance::gl.transactions');
    })->name('gl.transactions');
    
    Route::get('transactions/create', function () {
        return view('finance::gl.transaction-form');
    })->name('gl.transactions.create');
    
    Route::get('transactions/{id}/edit', function ($id) {
        return view('finance::gl.transaction-form', compact('id'));
    })->name('gl.transactions.edit');
    
    // Reports Routes
    Route::get('reports/trial-balance', function () {
        return view('finance::gl.reports.trial-balance');
    })->name('gl.reports.trial-balance');
    
    Route::get('reports/general-ledger', function () {
        return view('finance::gl.reports.general-ledger');
    })->name('gl.reports.general-ledger');
});
