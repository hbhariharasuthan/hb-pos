<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function productReport(Request $request)
    {
        $query = Product::with(['category', 'brand']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('low_stock_only')) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        if ($request->has('date_from') || $request->has('date_to')) {
            $query->whereHas('stockMovements', function ($q) use ($request) {
                if ($request->has('date_from')) {
                    $q->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->has('date_to')) {
                    $q->whereDate('created_at', '<=', $request->date_to);
                }
            });
        }

        $statsQuery = clone $query;
        $stats = [
            'total_products' => $statsQuery->count(),
            'total_value' => (float) $statsQuery->get()->sum(fn ($p) => $p->stock_quantity * $p->cost_price),
            'low_stock_count' => (clone $statsQuery)->whereRaw('stock_quantity <= min_stock_level')->count(),
            'out_of_stock_count' => (clone $statsQuery)->where('stock_quantity', 0)->count(),
            'total_stock_quantity' => $statsQuery->sum('stock_quantity'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'stats' => $stats,
            'products' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function inventoryReport(Request $request)
    {
        $query = StockMovement::with(['product', 'user']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $baseQuery = clone $query;
        $stats = [
            'total_movements' => $baseQuery->count(),
            'purchases' => (clone $baseQuery)->where('type', 'purchase')->sum('quantity'),
            'sales' => abs((clone $baseQuery)->where('type', 'sale')->sum('quantity')),
            'returns' => (clone $baseQuery)->where('type', 'return')->sum('quantity'),
            'adjustments' => (clone $baseQuery)->where('type', 'adjustment')->sum('quantity'),
            'total_value' => (float) (clone $baseQuery)->where('type', 'purchase')->get()->sum(fn ($m) => $m->quantity * ($m->unit_cost ?? 0)),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'stats' => $stats,
            'movements' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function salesReport(Request $request)
    {
        $query = Sale::with(['customer', 'user', 'items.product.brand']);

        if ($request->has('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $baseQuery = clone $query;
        $totalSales = $baseQuery->count();
        $totalRevenue = (float) (clone $baseQuery)->sum('total');
        $totalItemsSold = (int) (clone $baseQuery)->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')->sum('sale_items.quantity');
        $stats = [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'total_items_sold' => $totalItemsSold,
            'average_sale' => $totalSales > 0 ? $totalRevenue / $totalSales : 0,
            'cash_sales' => (float) (clone $baseQuery)->where('payment_method', 'cash')->sum('total'),
            'card_sales' => (float) (clone $baseQuery)->where('payment_method', 'card')->sum('total'),
            'credit_sales' => (float) (clone $baseQuery)->where('payment_method', 'credit')->sum('total'),
            'total_tax' => (float) (clone $baseQuery)->sum('tax_amount'),
            'total_cgst' => (float) (clone $baseQuery)->sum('tax_amount') / 2,
            'total_sgst' => (float) (clone $baseQuery)->sum('tax_amount') / 2,
            'total_discount' => (float) (clone $baseQuery)->sum('discount'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('sale_date', 'desc')->paginate($perPage);
        $sales = $paginated->items();

        $dailySales = collect([]);
        $productSales = [];
        foreach ($sales as $sale) {
            foreach ($sale->items ?? [] as $item) {
                $productId = $item->product_id;
                if (! isset($productSales[$productId])) {
                    $productSales[$productId] = [
                        'product_id' => $productId,
                        'product_name' => $item->product->name ?? 'N/A',
                        'sku' => $item->product->sku ?? '',
                        'brand_name' => optional($item->product->brand)->name,
                        'quantity' => 0,
                        'revenue' => 0,
                    ];
                }
                $productSales[$productId]['quantity'] += $item->quantity;
                $productSales[$productId]['revenue'] += $item->total;
            }
        }
        $topProducts = collect($productSales)->sortByDesc('revenue')->take(10)->values();

        return response()->json([
            'stats' => $stats,
            'sales' => $sales,
            'daily_sales' => $dailySales,
            'top_products' => $topProducts,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function purchaseReport(Request $request)
    {
        $query = Purchase::with(['supplier', 'items.product']);

        if ($request->has('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }

        $baseQuery = clone $query;
        $stats = [
            'total_purchases' => $baseQuery->count(),
            'total_amount' => (float) $baseQuery->sum('total'),
            'total_items' => (int) (clone $baseQuery)->join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')->sum('purchase_items.quantity'),
            'total_tax' => (float) $baseQuery->sum('tax_amount'),
            'total_subtotal' => (float) $baseQuery->sum('subtotal'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('purchase_date', 'desc')->paginate($perPage);

        return response()->json([
            'stats' => $stats,
            'purchases' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function dashboardStats()
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        // Sales stats
        $todaySales = Sale::whereDate('sale_date', today())->get();
        $monthSales = Sale::where('sale_date', '>=', $thisMonth)->get();

        // Product stats
        $totalProducts = Product::where('is_active', true)->count();
        $lowStockProducts = Product::whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->count();

        // Inventory value
        $inventoryValue = Product::where('is_active', true)
            ->get()
            ->sum(function($product) {
                return $product->stock_quantity * $product->cost_price;
            });

        return response()->json([
            'sales' => [
                'today_revenue' => (float) $todaySales->sum('total'),
                'today_count' => $todaySales->count(),
                'month_revenue' => (float) $monthSales->sum('total'),
                'month_count' => $monthSales->count(),
            ],
            'products' => [
                'total' => $totalProducts,
                'low_stock' => $lowStockProducts,
            ],
            'inventory' => [
                'total_value' => (float) $inventoryValue,
            ],
        ]);
    }
}
