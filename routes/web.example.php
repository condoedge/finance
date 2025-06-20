<?php

use Illuminate\Support\Facades\Route;
use Condoedge\Finance\Services\FinanceRouteService;

// All routes using the main layout (no middleware, only a Navbar)
Route::layout('layouts.main')->group(function() {
    
    // Route::get('/', App\Kompo\Home\HomeView::class)->name('home');
    
    Route::get('/dashboard', function () {
        return redirect()->route('home', status: 301);
    });
    
    // Public finance routes
    FinanceRouteService::publicRoutes();
});

// All routes using the dashboard layout ('auth' middleware, Navbar + Sidebar)
Route::layout('layouts.dashboard')->middleware(['auth'])->group(function() {
    
    // Finance module routes
    FinanceRouteService::invoicesRoutes();
    FinanceRouteService::financialCustomerRoutes();
    FinanceRouteService::accountingRoutes();
    FinanceRouteService::paymentsRoutes();
    FinanceRouteService::glTransactionRoutes();
    FinanceRouteService::reportsRoutes();
    
    // Admin routes (require additional permission)
    FinanceRouteService::adminRoutes();
    
    // Command interface routes
    FinanceRouteService::commandRoutes();
});

// API routes (stateless)
Route::prefix('api')->middleware(['api', 'auth:sanctum'])->group(function() {
    FinanceRouteService::apiRoutes();
});

// Kompo Modules Routes
include __DIR__.'/kompo/basic-auth.php';
