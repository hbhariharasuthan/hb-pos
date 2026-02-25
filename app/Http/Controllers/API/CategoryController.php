<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Traits\HasDropdownPagination;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    use HasDropdownPagination;

    public function index(Request $request)
    {
        // Check if this is the 'all' route (for master page)
        if ($request->route()->getName() === 'categories.all') {
            $categories = Category::withCount('products')->orderBy('name')->get();
            return response()->json($categories);
        }
        
        // List route: paginated with optional search and status filter
        $query = Category::withCount('products')->orderBy('name');

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
                'code' => 'nullable|string|unique:categories,code',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $category = Category::create($validated);
            return response()->json($category, 201);
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
            $category = Category::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'nullable|string|unique:categories,code,' . $id,
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $category->update($validated);
            return response()->json($category);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        // Check if category has any active products
        $activeProductsCount = $category->products()
            ->where('is_active', true)
            ->count();
        
        if ($activeProductsCount > 0) {
            throw ValidationException::withMessages([
                'category_id' => 'This category has ' . $activeProductsCount . ' active product(s). Please deactivate or delete the products first.',
            ]);
        }
        
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
