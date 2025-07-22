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

    public static function fiscalSetupRoutes(): void
    {
        Route::prefix('finance/fiscal-setup')->name('finance.fiscal-setup.')->group(function () {
            Route::get('/', \Condoedge\Finance\Kompo\FiscalSetup\FiscalSetupPage::class)->name('index');
        });
    }

    /**
     * Financial customer management routes
     */
    public static function financialCustomerRoutes(): void
    {
        Route::prefix('finance/customers')->name('finance.customers.')->group(function () {
            Route::get('/', \Condoedge\Finance\Kompo\FinantialCustomersTable::class)->name('list');
            Route::get('/page/{id}', \Condoedge\Finance\Kompo\FinancialCustomerPage::class)->name('page');
            Route::get('/form/{id?}', \Condoedge\Finance\Kompo\CustomerForm::class)->name('form');
            Route::get('/{id}/payments', \Condoedge\Finance\Kompo\FinancialCustomerPayments::class)->name('payments');
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
            // New GL system
            Route::get('/gl-transactions', \Condoedge\Finance\Kompo\GlTransactions\GlTransactionsTable::class)->name('gl-transactions');
            Route::get('/gl-transaction/form/{id?}', \Condoedge\Finance\Kompo\GlTransactions\GlTransactionForm::class)->name('gl-transaction-form');
        });
    }

    public static function expenseReportRoutes(): void
    {
        Route::prefix('finance/expense-reports')->name('finance.expense-reports.')->group(function () {
            Route::get('/', \Condoedge\Finance\Kompo\ExpenseReports\UserExpenseReportTable::class)->name('list');
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
        static::fiscalSetupRoutes();
        static::expenseReportRoutes();
    }
}
