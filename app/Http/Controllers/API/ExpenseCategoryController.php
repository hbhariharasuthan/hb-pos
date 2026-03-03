<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Traits\HasDropdownPagination;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    use HasDropdownPagination;

    public function index(Request $request)
    {
        // 'all' route for master page dropdowns
        if ($request->route()->getName() === 'expense-categories.all') {
            $categories = ExpenseCategory::withCount('expenses')
                ->orderBy('name')
                ->get();

            return response()->json($categories);
        }

        $query = ExpenseCategory::withCount('expenses')->orderBy('name');

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
                'code' => 'nullable|string|unique:expense_categories,code',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $category = ExpenseCategory::create($validated);
            return response()->json($category, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show($id)
    {
        $category = ExpenseCategory::withCount('expenses')->findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        try {
            $category = ExpenseCategory::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'nullable|string|unique:expense_categories,code,' . $id,
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
        $category = ExpenseCategory::findOrFail($id);

        if ($category->expenses()->exists()) {
            throw ValidationException::withMessages([
                'expense_category_id' => 'This category has expense(s). Reassign or delete them first.',
            ]);
        }

        $category->delete();

        return response()->json(['message' => 'Expense category deleted successfully']);
    }
}
