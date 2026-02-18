<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\POSController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\ReturnController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\PurchaseController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Categories
    Route::get('/categories/all', [CategoryController::class, 'index'])->name('categories.all');
    Route::apiResource('categories', CategoryController::class);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('/products/low-stock/list', [ProductController::class, 'index'])->name('products.low-stock');

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // POS
    Route::get('/pos/products', [POSController::class, 'getProducts']);
    Route::post('/pos/sale', [POSController::class, 'processSale']);

    // Sales
    Route::apiResource('sales', SaleController::class);
    Route::get('/sales/{id}/invoice', [SaleController::class, 'getInvoice']);

    // Purchases (inventory purchase bills)
    Route::get('/purchases/{id}/bill', [PurchaseController::class, 'getBill']);
    Route::apiResource('purchases', PurchaseController::class)->only(['index', 'store', 'show']);

    // Stock / Inventory
    Route::get('/stock/movements', [StockController::class, 'index']);
    Route::post('/stock/adjust', [StockController::class, 'adjustStock']);
    Route::get('/stock/low-stock', [StockController::class, 'getLowStock']);
    Route::get('/stock/stats', [StockController::class, 'getInventoryStats']);
    Route::get('/stock/report', [StockController::class, 'getInventoryReport']);

    // Returns
    Route::apiResource('returns', ReturnController::class);

    // Reports
    Route::get('/reports/dashboard-stats', [ReportController::class, 'dashboardStats']);
    Route::get('/reports/products', [ReportController::class, 'productReport']);
    Route::get('/reports/inventory', [ReportController::class, 'inventoryReport']);
    Route::get('/reports/sales', [ReportController::class, 'salesReport']);
});
