<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\ReturnModel;
use App\Models\Sale;
use App\Observers\ExpenseDayBookObserver;
use App\Observers\ExpenseJournalObserver;
use App\Observers\PurchaseDayBookObserver;
use App\Observers\PurchaseJournalObserver;
use App\Observers\ReturnDayBookObserver;
use App\Observers\ReturnJournalObserver;
use App\Observers\SaleDayBookObserver;
use App\Observers\SaleJournalObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sale::observe([SaleDayBookObserver::class, SaleJournalObserver::class]);
        Purchase::observe([PurchaseDayBookObserver::class, PurchaseJournalObserver::class]);
        ReturnModel::observe([ReturnDayBookObserver::class, ReturnJournalObserver::class]);
        Expense::observe([ExpenseDayBookObserver::class, ExpenseJournalObserver::class]);
    }
}
