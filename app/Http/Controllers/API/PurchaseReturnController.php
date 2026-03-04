<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnController extends Controller
{
    /**
     * Recompute purchase status based on total returned quantity.
     * If all items are fully returned (non-cancelled returns), mark as 'returned', else 'completed'.
     */
    protected function recomputePurchaseStatus(Purchase $purchase): void
    {
        $purchase->loadMissing('items', 'purchaseReturns.items');

        foreach ($purchase->items as $item) {
            $returnedQty = PurchaseReturnItem::query()
                ->where('purchase_item_id', $item->id)
                ->whereHas('purchaseReturn', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->sum('quantity');

            if ((float) $returnedQty < (float) $item->quantity) {
                $purchase->update(['status' => 'completed']);
                return;
            }
        }

        // All lines fully returned
        $purchase->update(['status' => 'returned']);
    }

    public function index(Request $request)
    {
        $query = PurchaseReturn::with(['purchase', 'supplier', 'user', 'items.product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date_from')) {
            $query->whereDate('return_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('return_date', '<=', $request->date_to);
        }
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->has('purchase_id')) {
            $query->where('purchase_id', $request->purchase_id);
        }

        $perPage = $request->input('per_page', 15);
        $returns = $query->orderBy('return_date', 'desc')->paginate($perPage);

        return response()->json($returns);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'purchase_id' => 'required|exists:purchases,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.purchase_item_id' => 'nullable|exists:purchase_items,id',
                'items.*.quantity' => 'required|numeric|min:0.001',
                'items.*.unit_cost' => 'required|numeric|min:0',
                'items.*.reason' => 'nullable|string',
                'reason' => 'nullable|string|max:50',
                'notes' => 'nullable|string',
                'status' => 'nullable|string|in:pending,approved',
            ]);

            DB::beginTransaction();

            $purchase = Purchase::with('items')->findOrFail($validated['purchase_id']);

            $returnedByPurchaseItem = PurchaseReturnItem::whereIn(
                'purchase_return_id',
                $purchase->purchaseReturns()->where('status', '!=', 'cancelled')->pluck('id')
            )->selectRaw('purchase_item_id, COALESCE(SUM(quantity),0) as returned')
                ->groupBy('purchase_item_id')
                ->pluck('returned', 'purchase_item_id');

            $returnAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $purchaseItemId = $itemData['purchase_item_id'] ?? null;
                $qty = (float) $itemData['quantity'];
                if ($purchaseItemId) {
                    $purchaseItem = $purchase->items->firstWhere('id', $purchaseItemId);
                    if (!$purchaseItem) {
                        throw ValidationException::withMessages(['items' => ['Invalid purchase_item_id for this purchase.']]);
                    }
                    $alreadyReturned = (float) ($returnedByPurchaseItem[$purchaseItemId] ?? 0);
                    $maxReturn = (float) $purchaseItem->quantity - $alreadyReturned;
                    if ($qty > $maxReturn) {
                        throw ValidationException::withMessages([
                            'items' => ["Return quantity for product {$purchaseItem->product_id} cannot exceed purchased quantity (max: {$maxReturn})."],
                        ]);
                    }
                } else {
                    $byProduct = [];
                    foreach ($purchase->items as $pi) {
                        $byProduct[$pi->product_id] = ($byProduct[$pi->product_id] ?? 0) + (float) $pi->quantity;
                    }
                    $alreadyReturned = PurchaseReturnItem::where('product_id', $itemData['product_id'])
                        ->whereIn('purchase_return_id', $purchase->purchaseReturns()->where('status', '!=', 'cancelled')->pluck('id'))
                        ->sum('quantity');
                    $maxReturn = (float) ($byProduct[$itemData['product_id']] ?? 0) - $alreadyReturned;
                    if ($qty > $maxReturn) {
                        throw ValidationException::withMessages([
                            'items' => ["Return quantity for product {$itemData['product_id']} cannot exceed purchased quantity (max: {$maxReturn})."],
                        ]);
                    }
                }
                $returnAmount += $qty * (float) $itemData['unit_cost'];
            }

            $status = $validated['status'] ?? 'approved';
            $purchaseReturn = PurchaseReturn::create([
                'purchase_id' => $validated['purchase_id'],
                'supplier_id' => $purchase->supplier_id,
                'user_id' => $request->user()->id,
                'return_date' => now(),
                'reason' => $validated['reason'] ?? 'other',
                'notes' => $validated['notes'] ?? null,
                'return_amount' => $returnAmount,
                'status' => $status,
            ]);

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $unitCost = (float) $itemData['unit_cost'];
                $lineAmount = $qty * $unitCost;
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $itemData['product_id'],
                    'purchase_item_id' => $itemData['purchase_item_id'] ?? null,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'line_amount' => $lineAmount,
                    'reason' => $itemData['reason'] ?? null,
                ]);

                if ($status === 'approved') {
                    $product = \App\Models\Product::find($itemData['product_id']);
                    if ($product) {
                        $product->decrement('stock_quantity', $qty);
                    }
                    StockMovement::create([
                        'product_id' => $itemData['product_id'],
                        'type' => 'purchase_return',
                        'quantity' => -(int) round($qty),
                        'unit_cost' => $unitCost,
                        'reference_type' => PurchaseReturn::class,
                        'reference_id' => $purchaseReturn->id,
                        'user_id' => $request->user()->id,
                    ]);
                }
            }

            $this->recomputePurchaseStatus($purchase);

            DB::commit();

            return response()->json([
                'message' => 'Purchase return processed successfully',
                'purchase_return' => $purchaseReturn->load(['items.product', 'purchase', 'supplier']),
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Purchase return processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $purchaseReturn = PurchaseReturn::with(['purchase', 'supplier', 'user', 'items.product'])->findOrFail($id);
        return response()->json($purchaseReturn);
    }

    public function update(Request $request, $id)
    {
        try {
            $purchaseReturn = PurchaseReturn::with('items')->findOrFail($id);
            if ($purchaseReturn->status === 'cancelled') {
                return response()->json(['message' => 'Cannot update a cancelled purchase return.'], 422);
            }

            $validated = $request->validate([
                'notes' => 'nullable|string',
                'status' => 'nullable|string|in:pending,approved,rejected,cancelled',
            ]);

            $oldStatus = $purchaseReturn->status;
            $purchaseReturn->update($validated);
            $newStatus = $purchaseReturn->fresh()->status;

            if ($oldStatus === 'pending' && $newStatus === 'approved') {
                foreach ($purchaseReturn->items as $item) {
                    $product = \App\Models\Product::find($item->product_id);
                    if ($product) {
                        $product->decrement('stock_quantity', $item->quantity);
                    }
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'purchase_return',
                        'quantity' => -(int) round($item->quantity),
                        'unit_cost' => $item->unit_cost,
                        'reference_type' => PurchaseReturn::class,
                        'reference_id' => $purchaseReturn->id,
                        'user_id' => $purchaseReturn->user_id,
                    ]);
                }
            }

            return response()->json($purchaseReturn->fresh()->load(['items.product', 'purchase', 'supplier']));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Soft cancel: set status to cancelled, reverse stock.
     */
    public function destroy($id)
    {
        $purchaseReturn = PurchaseReturn::with('items')->findOrFail($id);
        if ($purchaseReturn->status === 'cancelled') {
            return response()->json(['message' => 'Purchase return is already cancelled.'], 200);
        }

        DB::beginTransaction();
        try {
            $purchaseReturn->update(['status' => 'cancelled']);

            foreach ($purchaseReturn->items as $item) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }
            StockMovement::where('reference_type', PurchaseReturn::class)->where('reference_id', $purchaseReturn->id)->delete();

            // Purchase may no longer be fully returned after cancellation
            $this->recomputePurchaseStatus($purchaseReturn->purchase);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to cancel purchase return',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Purchase return cancelled successfully.',
            'purchase_return' => $purchaseReturn->fresh(['items.product', 'purchase', 'supplier']),
        ]);
    }
}
