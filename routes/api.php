<?php

use Illuminate\Support\Facades\Route;

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

