<?php

namespace Condoedge\Finance\Services;

use Illuminate\Support\Facades\Route;

class FinanceRouteService
{
    /**
     * Invoice management routes
     */
    public static function invoicesRoutes(): void
    {
        Route::prefix('finance/invoices')->name('invoices.')->group(function () {
            Route::get('/', \Condoedge\Finance\Kompo\InvoicesTable::class)->name('list');
            Route::get('/page/{id}', \Condoedge\Finance\Kompo\InvoicePage::class)->name('show');
            Route::get('/form/{id?}', \Condoedge\Finance\Kompo\InvoiceForm::class)->name('form');
            
            // Invoice details
            Route::prefix('{invoice_id}/details')->name('details.')->group(function () {
                Route::get('/', \Condoedge\Finance\Kompo\InvoiceDetailsTable::class)->name('index');
                Route::get('/form/{id?}', \Condoedge\Finance\Kompo\InvoiceDetailForm::class)->name('form');
            });
        });
    }

    /**
     * Financial customer routes - alias for backwards compatibility
     */
    public static function finantialCustomerRoutes(): void
    {
        static::financialCustomerRoutes();
    }
    
    /**
     * Financial customer management routes
     */
    public static function financialCustomerRoutes(): void
    {
        Route::prefix('finance/customers')->name('finance.customers.')->group(function () {
            Route::get('/', \Condoedge\Finance\Kompo\FinantialCustomersTable::class)->name('list');
            Route::get('/page/{id}', \Condoedge\Finance\Kompo\FinantialCustomerPage::class)->name('page');
            Route::get('/form/{id?}', \Condoedge\Finance\Kompo\CustomerForm::class)->name('form');
            Route::get('/{id}/payments', \Condoedge\Finance\Kompo\FinantialCustomerPayments::class)->name('payments');
        });
    }

    /**
     * Chart of accounts and segment management routes
     */
    public static function accountingRoutes(): void
    {
        Route::prefix('finance/accounting')->name('finance.')->group(function () {
            // Chart of Accounts
            Route::get('/chart-of-accounts', \Condoedge\Finance\Kompo\ChartOfAccounts\ChartOfAccounts::class)->name('chart-of-accounts');
            
            // Segment Management
            Route::get('/segments', \Condoedge\Finance\Kompo\SegmentManagement\SegmentManager::class)->name('segment-manager');
        });
    }

    /**
     * Payment management routes
     */
    public static function paymentsRoutes(): void
    {
        Route::prefix('finance/payments')->name('finance.payments.')->group(function () {
            Route::get('/form/{id?}', \Condoedge\Finance\Kompo\PaymentForm::class)->name('form');
            Route::get('/entry-form', \Condoedge\Finance\Kompo\PaymentEntryForm::class)->name('entry-form');
            Route::get('/apply-modal', \Condoedge\Finance\Kompo\ApplyPaymentToInvoiceModal::class)->name('apply-modal');
        });
    }

    /**
     * GL Transaction routes
     */
    public static function glTransactionRoutes(): void
    {
        Route::prefix('finance/gl')->name('finance.gl.')->group(function () {
            // Transactions (legacy)
            Route::get('/transactions', \Condoedge\Finance\Kompo\TransactionsTable::class)->name('transactions');
            Route::get('/transactions/mini', \Condoedge\Finance\Kompo\TransactionsMiniTable::class)->name('transactions-mini');
            Route::get('/transaction/form/{id?}', \Condoedge\Finance\Kompo\TransactionForm::class)->name('transaction-form');
            Route::get('/transaction/preview/{id}', \Condoedge\Finance\Kompo\TransactionPreviewForm::class)->name('transaction-preview');
            
            // Transaction entries
            Route::get('/entries/{transaction_id}', \Condoedge\Finance\Kompo\TransactionEntriesTable::class)->name('entries');
            Route::get('/entry/form/{id?}', \Condoedge\Finance\Kompo\TransactionEntryForm::class)->name('entry-form');
            
            // New GL system
            Route::get('/gl-transactions', \Condoedge\Finance\Kompo\GlTransactions\GlTransactionsTable::class)->name('gl-transactions');
            Route::get('/gl-transaction/form/{id?}', \Condoedge\Finance\Kompo\GlTransactions\GlTransactionForm::class)->name('gl-transaction-form');
        });
    }

    /**
     * API routes
     */
    public static function apiRoutes(): void
    {
        Route::prefix('api/finance')->name('api.finance.')->group(function () {
            // Add API controllers when they exist
            if (class_exists(\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class)) {
                Route::get('/segments/{id}/values', [\Condoedge\Finance\Http\Controllers\Api\AccountSegmentController::class, 'getSegmentValues']);
            }
        });
    }

    /**
     * Register all routes at once
     */
    public static function registerAllWebRoutes(): void
    {
        static::invoicesRoutes();
        static::financialCustomerRoutes();
        static::accountingRoutes();
        static::paymentsRoutes();
        static::glTransactionRoutes();
    }
}
