<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DayBookEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DayBookEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = DayBookEntry::with(['user', 'reference']);

        if ($request->has('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        if ($request->has('entry_type')) {
            $query->where('entry_type', $request->entry_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('narration', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        $entries = $query->orderBy('entry_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($entries);
    }

    /**
     * Store a manual day book entry (journal, opening_balance, payment, receipt).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'entry_type' => 'required|in:payment,receipt,journal,opening_balance',
                'entry_date' => 'required|date',
                'voucher_number' => 'nullable|string|max:255',
                'amount' => 'required|numeric',
                'narration' => 'nullable|string',
            ]);

            $validated['user_id'] = $request->user()->id;
            $validated['reference_type'] = null;
            $validated['reference_id'] = null;

            if (empty($validated['voucher_number'])) {
                $prefix = match ($validated['entry_type']) {
                    'opening_balance' => 'OB',
                    'payment' => 'PAY',
                    'receipt' => 'RCP',
                    default => 'JV',
                };
                $validated['voucher_number'] = DayBookEntry::generateVoucherNumber($prefix);
            }

            $entry = DayBookEntry::create($validated);

            return response()->json($entry->load('user'), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show($id)
    {
        $entry = DayBookEntry::with(['user', 'reference'])->findOrFail($id);

        return response()->json($entry);
    }

    /**
     * Update only manual entries (no reference_type).
     */
    public function update(Request $request, $id)
    {
        try {
            $entry = DayBookEntry::findOrFail($id);

            if (! $entry->isManual()) {
                throw ValidationException::withMessages([
                    'entry' => 'Only manual entries (journal, opening_balance, payment, receipt) can be updated.',
                ]);
            }

            $validated = $request->validate([
                'entry_date' => 'sometimes|date',
                'voucher_number' => 'nullable|string|max:255',
                'amount' => 'sometimes|numeric',
                'narration' => 'nullable|string',
            ]);

            $entry->update($validated);

            return response()->json($entry->load('user'));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Delete only manual entries.
     */
    public function destroy($id)
    {
        $entry = DayBookEntry::findOrFail($id);

        if (! $entry->isManual()) {
            throw ValidationException::withMessages([
                'entry' => 'Only manual entries can be deleted. Delete the source transaction (sale, purchase, return, expense) instead.',
            ]);
        }

        $entry->delete();

        return response()->json(['message' => 'Day book entry deleted successfully']);
    }

    /**
     * Mark entry as reconciled (bank reconciliation).
     */
    public function reconcile($id)
    {
        $entry = DayBookEntry::findOrFail($id);
        $entry->update(['reconciled_at' => $entry->reconciled_at ? null : now()]);

        return response()->json($entry->load('user'));
    }
}
