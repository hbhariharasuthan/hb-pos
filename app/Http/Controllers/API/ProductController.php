<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Traits\HasDropdownPagination;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use HasDropdownPagination;

    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            })->orWhereHas('brand', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->has('low_stock')) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        if ($request->has('out_of_stock')) {
            $query->where('stock_quantity', 0);
        }

        if ($request->has('in_stock_only')) {
            $query->whereRaw('stock_quantity > min_stock_level');
        }

        $query->orderBy('name');
        
        // Always paginate - default to 10 items per page if not specified
        $perPage = $request->input('per_page', 10);
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'sku' => 'required|string|unique:products,sku',
                'barcode' => 'nullable|string|unique:products,barcode',
                'category_id' => 'nullable|exists:categories,id',
                'brand_id' => 'nullable|exists:brands,id',
                'description' => 'nullable|string',
                'cost_price' => 'required|numeric|min:0',
                'selling_price' => 'required|numeric|min:0',
                'stock_quantity' => 'numeric|min:0',
                'min_stock_level' => 'numeric|min:0',
                'unit' => 'string|max:50',
                'is_active' => 'boolean',
            ]);

            $product = Product::create($validated);

            return response()->json($product->load(['category', 'brand']), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show($id)
    {
        $product = Product::with(['category', 'brand'])->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'sku' => 'sometimes|required|string|unique:products,sku,' . $id,
                'barcode' => 'nullable|string|unique:products,barcode,' . $id,
                'category_id' => 'nullable|exists:categories,id',
                'brand_id' => 'nullable|exists:brands,id',
                'description' => 'nullable|string',
                'cost_price' => 'sometimes|required|numeric|min:0',
                'selling_price' => 'sometimes|required|numeric|min:0',
                'stock_quantity' => 'numeric|min:0',
                'min_stock_level' => 'numeric|min:0',
                'unit' => 'string|max:50',
                'is_active' => 'boolean',
            ]);

            $product->update($validated);

            return response()->json($product->load(['category', 'brand']));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
