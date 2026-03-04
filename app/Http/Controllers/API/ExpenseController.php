<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DayBookEntry;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['user', 'expenseCategory']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        if ($request->has('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page', 10);
        $expenses = $query->orderBy('expense_date', 'desc')->paginate($perPage);

        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'expense_category_id' => 'required|exists:expense_categories,id',
                'voucher_number' => 'nullable|string|max:255',
                'amount' => 'required|numeric|min:0',
                'expense_date' => 'required|date',
                'payment_method' => 'nullable|string|in:cash,card,credit,mixed',
                'reference' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);

            $validated['user_id'] = $request->user()->id;
            if (empty($validated['status'])) {
                $validated['status'] = 'approved';
            }

            $expense = Expense::create($validated);
            return response()->json($expense->load(['user', 'expenseCategory']), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show($id)
    {
        $expense = Expense::with(['user', 'expenseCategory'])->findOrFail($id);
        return response()->json($expense);
    }

    public function update(Request $request, $id)
    {
        try {
            $expense = Expense::findOrFail($id);
            $validated = $request->validate([
                'expense_category_id' => 'nullable|exists:expense_categories,id',
                'voucher_number' => 'nullable|string|max:255',
                'amount' => 'sometimes|numeric|min:0',
                'expense_date' => 'sometimes|date',
                'payment_method' => 'nullable|string|in:cash,card,credit,mixed',
                'reference' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:pending,approved,cancelled',
                'notes' => 'nullable|string',
            ]);

            $expense->update($validated);

            return response()->json($expense->load(['user', 'expenseCategory']));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted successfully']);
    }

    /**
     * Mark an approved expense as refunded/cancelled and create a receipt entry.
     */
    public function refund(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        if ($expense->status === 'cancelled') {
            return response()->json([
                'message' => 'Expense is already cancelled.',
                'expense' => $expense->load(['user', 'expenseCategory']),
            ], 200);
        }

        if ($expense->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved expenses can be refunded.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $expense->update(['status' => 'cancelled']);

            DayBookEntry::create([
                'user_id' => $request->user()->id,
                'entry_date' => now()->toDateString(),
                'voucher_number' => DayBookEntry::generateVoucherNumber('RCP'),
                'entry_type' => DayBookEntry::TYPE_RECEIPT,
                'amount' => (float) $expense->amount,
                'narration' => 'Expense refund for ' . $expense->voucher_number,
                'reference_type' => Expense::class,
                'reference_id' => $expense->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Expense refunded and marked as cancelled.',
                'expense' => $expense->fresh()->load(['user', 'expenseCategory']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to refund expense.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
