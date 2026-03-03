<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $query = Account::query()->orderBy('code');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $query->where('is_active', (bool) $request->is_active);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 50);
        $accounts = $query->paginate($perPage);

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50|unique:accounts,code',
                'name' => 'required|string|max:255',
                'type' => 'required|in:asset,liability,equity,income,expense',
                'parent_id' => 'nullable|exists:accounts,id',
                'opening_balance' => 'nullable|numeric',
                'is_active' => 'boolean',
            ]);

            $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
            $account = Account::create($validated);

            return response()->json($account, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show($id)
    {
        $account = Account::findOrFail($id);

        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        try {
            $account = Account::findOrFail($id);

            $validated = $request->validate([
                'code' => 'sometimes|string|max:50|unique:accounts,code,' . $id,
                'name' => 'sometimes|string|max:255',
                'type' => 'sometimes|in:asset,liability,equity,income,expense',
                'parent_id' => 'nullable|exists:accounts,id',
                'opening_balance' => 'nullable|numeric',
                'is_active' => 'boolean',
            ]);

            $account->update($validated);

            return response()->json($account);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $account = Account::findOrFail($id);

        if ($account->journalEntryLines()->exists()) {
            throw ValidationException::withMessages([
                'account_id' => 'This account has journal entry lines. Reassign or remove them first.',
            ]);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
