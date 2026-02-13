<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function productReport(Request $request)
    {
        $query = Product::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('low_stock_only')) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        if ($request->has('date_from') || $request->has('date_to')) {
            $query->whereHas('stockMovements', function($q) use ($request) {
                if ($request->has('date_from')) {
                    $q->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->has('date_to')) {
                    $q->whereDate('created_at', '<=', $request->date_to);
                }
            });
        }

        $products = $query->get();

        $stats = [
            'total_products' => $products->count(),
            'total_value' => $products->sum(function($p) {
                return $p->stock_quantity * $p->cost_price;
            }),
            'low_stock_count' => $products->filter(function($p) {
                return $p->stock_quantity <= $p->min_stock_level;
            })->count(),
            'out_of_stock_count' => $products->filter(function($p) {
                return $p->stock_quantity === 0;
            })->count(),
            'total_stock_quantity' => $products->sum('stock_quantity'),
        ];

        return response()->json([
            'stats' => $stats,
            'products' => $products,
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

        $movements = $query->orderBy('created_at', 'desc')->get();

        $stats = [
            'total_movements' => $movements->count(),
            'purchases' => $movements->where('type', 'purchase')->sum('quantity'),
            'sales' => abs($movements->where('type', 'sale')->sum('quantity')),
            'returns' => $movements->where('type', 'return')->sum('quantity'),
            'adjustments' => $movements->where('type', 'adjustment')->sum('quantity'),
            'total_value' => $movements->where('type', 'purchase')->sum(function($m) {
                return $m->quantity * ($m->unit_cost ?? 0);
            }),
        ];

        // Group by product
        $productMovements = $movements->groupBy('product_id')->map(function($group) {
            $product = $group->first()->product;
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'movements' => $group->count(),
                'total_in' => $group->whereIn('type', ['purchase', 'return'])->sum('quantity'),
                'total_out' => abs($group->whereIn('type', ['sale'])->sum('quantity')),
                'adjustments' => $group->where('type', 'adjustment')->sum('quantity'),
            ];
        })->values();

        return response()->json([
            'stats' => $stats,
            'movements' => $movements,
            'product_summary' => $productMovements,
        ]);
    }

    public function salesReport(Request $request)
    {
        $query = Sale::with(['customer', 'user', 'items.product']);

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

        $sales = $query->orderBy('sale_date', 'desc')->get();

        $stats = [
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total'),
            'total_items_sold' => $sales->sum(function($sale) {
                return $sale->items->sum('quantity');
            }),
            'average_sale' => $sales->count() > 0 ? $sales->sum('total') / $sales->count() : 0,
            'cash_sales' => $sales->where('payment_method', 'cash')->sum('total'),
            'card_sales' => $sales->where('payment_method', 'card')->sum('total'),
            'credit_sales' => $sales->where('payment_method', 'credit')->sum('total'),
            'total_tax' => $sales->sum('tax_amount'),
            'total_discount' => $sales->sum('discount'),
        ];

        // Daily sales breakdown
        $dailySales = $sales->groupBy(function($sale) {
            return $sale->sale_date->format('Y-m-d');
        })->map(function($daySales, $date) {
            return [
                'date' => $date,
                'count' => $daySales->count(),
                'revenue' => $daySales->sum('total'),
                'items' => $daySales->sum(function($sale) {
                    return $sale->items->sum('quantity');
                }),
            ];
        })->values();

        // Top selling products
        $productSales = [];
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $productId = $item->product_id;
                if (!isset($productSales[$productId])) {
                    $productSales[$productId] = [
                        'product_id' => $productId,
                        'product_name' => $item->product->name,
                        'sku' => $item->product->sku,
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
