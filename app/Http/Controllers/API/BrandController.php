<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Traits\HasDropdownPagination;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    use HasDropdownPagination;

    public function index(Request $request)
    {
        // 'all' route for master page dropdowns
        if ($request->route()->getName() === 'brands.all') {
            $brands = Brand::withCount('products')
                ->orderBy('name')
                ->get();

            return response()->json($brands);
        }

        $query = Brand::withCount('products')->orderBy('name');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $query->where('is_active', (bool) $request->is_active);
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|unique:brands,code',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $brand = Brand::create($validated);
            return response()->json($brand, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $brand = Brand::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'nullable|string|unique:brands,code,' . $id,
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $brand->update($validated);
            return response()->json($brand);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        
        // Check if brand has any active products
        $activeProductsCount = $brand->products()
            ->where('is_active', true)
            ->count();
        
        if ($activeProductsCount > 0) {
            throw ValidationException::withMessages([
                'brand_id' => 'This brand has ' . $activeProductsCount . ' active product(s). Please deactivate or delete the products first.',
            ]);
        }
        
        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }
}

