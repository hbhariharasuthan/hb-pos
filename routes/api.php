<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\POSController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\ReturnController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\ImportController;
use App\Http\Controllers\API\GstSlabController;
use App\Http\Controllers\API\ExpenseCategoryController;
use App\Http\Controllers\API\ExpenseController;
use App\Http\Controllers\API\DayBookEntryController;
use App\Http\Controllers\API\AccountController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/client-info', function () {
    return response()->json(config('client'));
});
// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Categories
    Route::get('/categories/all', [CategoryController::class, 'index'])->name('categories.all');
    Route::apiResource('categories', CategoryController::class);

    // Brands
    Route::get('/brands/all', [BrandController::class, 'index'])->name('brands.all');
    Route::apiResource('brands', BrandController::class);

    // GST Slabs (master + dropdown)
    Route::get('/gst-slabs/all', [GstSlabController::class, 'index'])->name('gst-slabs.all');
    Route::apiResource('gst-slabs', GstSlabController::class);
    //import brands
    Route::post('/import/{type}', [ImportController::class, 'import']);


    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('/products/low-stock/list', [ProductController::class, 'index'])->name('products.low-stock');

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // POS
    Route::get('/pos/products', [POSController::class, 'getProducts']);
    Route::post('/pos/sale', [POSController::class, 'processSale']);
    Route::get('/pos/config', [POSController::class, 'getConfig']);
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

    // Expense categories
    Route::get('/expense-categories/all', [ExpenseCategoryController::class, 'index'])->name('expense-categories.all');
    Route::apiResource('expense-categories', ExpenseCategoryController::class);

    // Expenses
    Route::apiResource('expenses', ExpenseController::class);

    // Day book entries (Phase 2: journal layer; list all, manual journal/opening_balance/payment/receipt)
    Route::post('/day-book-entries/{id}/reconcile', [DayBookEntryController::class, 'reconcile'])->name('day-book-entries.reconcile');
    Route::apiResource('day-book-entries', DayBookEntryController::class);

    // Chart of accounts (Phase 3)
    Route::apiResource('accounts', AccountController::class);

    // Reports
    Route::get('/reports/dashboard-stats', [ReportController::class, 'dashboardStats']);
    Route::get('/reports/products', [ReportController::class, 'productReport']);
    Route::get('/reports/inventory', [ReportController::class, 'inventoryReport']);
    Route::get('/reports/sales', [ReportController::class, 'salesReport']);
    Route::get('/reports/purchases', [ReportController::class, 'purchaseReport']);
    Route::get('/reports/expenses', [ReportController::class, 'expenseReport']);
    Route::get('/reports/day-book', [ReportController::class, 'dayBookReport']);
    Route::get('/reports/day-book/export', [ReportController::class, 'dayBookExport']);
    Route::get('/reports/ledger', [ReportController::class, 'ledgerReport']);
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalanceReport']);
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLossReport']);
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheetReport']);
    Route::get('/reports/gst-outward', [ReportController::class, 'gstOutwardReport']);
    Route::get('/reports/gst-purchase-register', [ReportController::class, 'gstPurchaseRegisterReport']);

    // Import
    Route::get('/import/sample/{type}', [ImportController::class, 'downloadSample']);
});
