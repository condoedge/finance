<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Kompo\FinantialCustomerPage;
use Condoedge\Finance\Kompo\FinantialCustomersTable;
use Condoedge\Finance\Kompo\InvoiceForm;
use Condoedge\Finance\Kompo\InvoicePage;
use Condoedge\Finance\Kompo\InvoicesTable;
use Illuminate\Support\Facades\Route;

class FinanceRouteService 
{
    public static function invoicesRoutes()
    {
        Route::get('invoices', InvoicesTable::class)->name('invoices.list');

        Route::get('invoices/{id}', InvoicePage::class)->name('invoices.show');

        Route::get('invoice-form/{id?}', InvoiceForm::class)->name('invoices.form');
    }

    public static function finantialCustomerRoutes()
    {
        Route::get('finantial-customers', FinantialCustomersTable::class)->name('finantial-customers.list');
        Route::get('finantial-customer/{id}', FinantialCustomerPage::class)->name('finantial-customers.page');
    }
}