<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::with(['product', 'user']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);
        return response()->json($movements);
    }

    public function adjustStock(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|numeric',
                'type' => 'required|in:purchase,adjustment',
                'unit_cost' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $product = Product::findOrFail($validated['product_id']);

            if ($validated['type'] === 'purchase') {
                $product->increment('stock_quantity', $validated['quantity']);
            } else {
                $newQuantity = $product->stock_quantity + $validated['quantity'];
                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Insufficient stock for adjustment']
                    ]);
                }
                $product->stock_quantity = $newQuantity;
                $product->save();
            }

            StockMovement::create([
                'product_id' => $validated['product_id'],
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'] ?? $product->cost_price,
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock adjusted successfully',
                'product' => $product->fresh(),
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function getLowStock()
    {
        $products = Product::whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->with('category')
            ->get();

        return response()->json($products);
    }

    public function getInventoryStats()
    {
        $totalProducts = Product::where('is_active', true)->count();
        $inStock = Product::where('is_active', true)
            ->whereRaw('stock_quantity > min_stock_level')
            ->count();
        $lowStock = Product::where('is_active', true)
            ->whereRaw('stock_quantity > 0 AND stock_quantity <= min_stock_level')
            ->count();
        $outOfStock = Product::where('is_active', true)
            ->where('stock_quantity', 0)
            ->count();

        $totalValue = Product::where('is_active', true)
            ->selectRaw('SUM(stock_quantity * cost_price) as total')
            ->first()
            ->total ?? 0;

        return response()->json([
            'total_products' => $totalProducts,
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_value' => $totalValue,
        ]);
    }

    public function getInventoryReport(Request $request)
    {
        $query = Product::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('low_stock_only')) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        if ($request->has('out_of_stock_only')) {
            $query->where('stock_quantity', 0);
        }

        $products = $query->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category->name ?? 'N/A',
                'stock_quantity' => $product->stock_quantity,
                'min_stock_level' => $product->min_stock_level,
                'unit' => $product->unit,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'total_value' => $product->stock_quantity * $product->cost_price,
                'status' => $product->stock_quantity === 0 
                    ? 'out_of_stock' 
                    : ($product->stock_quantity <= $product->min_stock_level ? 'low_stock' : 'in_stock'),
            ];
        });

        return response()->json($products);
    }
}
