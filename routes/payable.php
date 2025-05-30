<?php

use Illuminate\Support\Facades\Route;
use Condoedge\Finance\Http\Controllers\Payable\VendorsController;
use Condoedge\Finance\Http\Controllers\Payable\BillsController;
use Condoedge\Finance\Http\Controllers\Payable\VendorPaymentsController;

/*
|--------------------------------------------------------------------------
| Payable Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/payable')->middleware(['api'])->group(function () {
    
    // Vendor management
    Route::post('vendors', [VendorsController::class, 'saveVendor']);
    Route::post('vendors/from-customable', [VendorsController::class, 'createFromCustomableModel']);
    
    // Bill management
    Route::post('bills', [BillsController::class, 'createBill']);
    Route::put('bills', [BillsController::class, 'updateBill']);
    Route::post('bills/details', [BillsController::class, 'saveBillDetail']);
    
    // Vendor payments
    Route::post('payments/vendors', [VendorPaymentsController::class, 'createVendorPayment']);
    Route::post('payments/vendors/for-bill', [VendorPaymentsController::class, 'createVendorPaymentForBill']);
});

Route::prefix('payable')->middleware(['web'])->group(function () {
    
    // Vendor management pages
    Route::get('vendors', function () {
        return view('finance::payable.vendors.index');
    })->name('payable.vendors.index');
    
    Route::get('vendors/create', function () {
        return view('finance::payable.vendors.create');
    })->name('payable.vendors.create');
    
    Route::get('vendors/{id}/edit', function ($id) {
        return view('finance::payable.vendors.edit', compact('id'));
    })->name('payable.vendors.edit');
    
    // Bill management pages
    Route::get('bills', function () {
        return view('finance::payable.bills.index');
    })->name('payable.bills.index');
    
    Route::get('bills/create', function () {
        return view('finance::payable.bills.create');
    })->name('payable.bills.create');
    
    Route::get('bills/{id}/edit', function ($id) {
        return view('finance::payable.bills.edit', compact('id'));
    })->name('payable.bills.edit');
    
    Route::get('bills/{id}', function ($id) {
        return view('finance::payable.bills.show', compact('id'));
    })->name('payable.bills.show');
    
    // Payment management pages
    Route::get('payments', function () {
        return view('finance::payable.payments.index');
    })->name('payable.payments.index');
    
    Route::get('payments/create', function () {
        return view('finance::payable.payments.create');
    })->name('payable.payments.create');
    
    // Reports
    Route::get('reports/vendor-aging', function () {
        return view('finance::payable.reports.vendor-aging');
    })->name('payable.reports.vendor-aging');
    
    Route::get('reports/bills-payable', function () {
        return view('finance::payable.reports.bills-payable');
    })->name('payable.reports.bills-payable');
});
