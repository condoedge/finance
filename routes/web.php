<?php

use Condoedge\Finance\Http\Controllers\Payments\MonerisReturnController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance Web Routes
|--------------------------------------------------------------------------
*/

// Moneris hosted-checkout redirect-back. Moneris POSTs the user's browser
// here after they complete (or cancel) payment on the hosted page. Cross-domain
// POST so CSRF must be skipped on this route specifically.
Route::match(['get', 'post'], '/finance/payments/moneris/return', [MonerisReturnController::class, 'handle'])
    ->name('finance.payments.moneris.return')
    ->withoutMiddleware([VerifyCsrfToken::class]);
