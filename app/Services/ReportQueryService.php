<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;

class ReportQueryService
{
    public function buildProductQuery(array $filters = []): Builder
    {
        $query = Product::with(['category', 'brand']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['low_stock_only'])) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $dateFrom = $filters['date_from'] ?? null;
            $dateTo = $filters['date_to'] ?? null;
            $query->whereHas('stockMovements', function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                }
            });
        }

        return $query;
    }

    public function buildInventoryQuery(array $filters = []): Builder
    {
        $query = StockMovement::with(['product', 'user']);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function buildSalesQuery(array $filters = []): Builder
    {
        $query = Sale::with(['customer', 'user', 'items.product.brand']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('sale_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('sale_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        return $query;
    }

    public function buildPurchaseQuery(array $filters = []): Builder
    {
        $query = Purchase::with(['supplier', 'items.product']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('purchase_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('purchase_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function buildExpenseQuery(array $filters = []): Builder
    {
        $query = Expense::with(['expenseCategory', 'user']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }

        return $query;
    }
}

