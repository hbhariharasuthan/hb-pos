<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ReturnModel;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $query = ReturnModel::with(['sale', 'customer', 'user', 'items.product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date_from')) {
            $query->whereDate('return_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('return_date', '<=', $request->date_to);
        }
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->has('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        $perPage = $request->input('per_page', 15);
        $returns = $query->orderBy('return_date', 'desc')->paginate($perPage);

        return response()->json($returns);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'sale_id' => 'required|exists:sales,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.sale_item_id' => 'nullable|exists:sale_items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.reason' => 'nullable|string',
                'reason' => 'required|in:defective,wrong_item,customer_request,other',
                'notes' => 'nullable|string',
                'refund_method' => 'nullable|string|in:cash,card,credit_note,none',
                'refund_reference' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:pending,approved',
            ]);

            DB::beginTransaction();

            $sale = Sale::with('items')->findOrFail($validated['sale_id']);

            // Build max returnable per product/sale_item (sold qty - already returned)
            $returnedBySaleItem = ReturnItem::whereIn('return_id', $sale->returns()->where('status', '!=', 'cancelled')->pluck('id'))
                ->selectRaw('sale_item_id, COALESCE(SUM(quantity),0) as returned')
                ->groupBy('sale_item_id')
                ->pluck('returned', 'sale_item_id');

            $refundAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $saleItemId = $itemData['sale_item_id'] ?? null;
                $qty = (int) $itemData['quantity'];
                if ($saleItemId) {
                    $saleItem = $sale->items->firstWhere('id', $saleItemId);
                    if (!$saleItem) {
                        throw ValidationException::withMessages(['items' => ['Invalid sale_item_id for this sale.']]);
                    }
                    $alreadyReturned = (int) ($returnedBySaleItem[$saleItemId] ?? 0);
                    $maxReturn = (int) $saleItem->quantity - $alreadyReturned;
                    if ($qty > $maxReturn) {
                        throw ValidationException::withMessages([
                            'items' => ["Return quantity for product {$saleItem->product_id} cannot exceed sold quantity (max: {$maxReturn})."],
                        ]);
                    }
                } else {
                    $byProduct = [];
                    foreach ($sale->items as $si) {
                        $byProduct[$si->product_id] = ($byProduct[$si->product_id] ?? 0) + (int) $si->quantity;
                    }
                    $alreadyReturned = ReturnItem::where('product_id', $itemData['product_id'])
                        ->whereIn('return_id', $sale->returns()->where('status', '!=', 'cancelled')->pluck('id'))
                        ->sum('quantity');
                    $maxReturn = (int) ($byProduct[$itemData['product_id']] ?? 0) - (int) $alreadyReturned;
                    if ($qty > $maxReturn) {
                        throw ValidationException::withMessages([
                            'items' => ["Return quantity for product {$itemData['product_id']} cannot exceed sold quantity (max: {$maxReturn})."],
                        ]);
                    }
                }
                $refundAmount += $qty * (float) $itemData['unit_price'];
            }

            $status = $validated['status'] ?? 'approved';
            $return = ReturnModel::create([
                'sale_id' => $validated['sale_id'],
                'customer_id' => $sale->customer_id,
                'user_id' => $request->user()->id,
                'return_date' => now(),
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'refund_amount' => $refundAmount,
                'refund_method' => $validated['refund_method'] ?? null,
                'refund_reference' => $validated['refund_reference'] ?? null,
                'status' => $status,
            ]);

            foreach ($validated['items'] as $itemData) {
                ReturnItem::create([
                    'return_id' => $return->id,
                    'product_id' => $itemData['product_id'],
                    'sale_item_id' => $itemData['sale_item_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'refund_amount' => $itemData['quantity'] * $itemData['unit_price'],
                    'reason' => $itemData['reason'] ?? null,
                ]);

                if ($status === 'approved') {
                    $product = \App\Models\Product::find($itemData['product_id']);
                    $product->increment('stock_quantity', $itemData['quantity']);

                    StockMovement::create([
                        'product_id' => $itemData['product_id'],
                        'type' => 'return',
                        'quantity' => $itemData['quantity'],
                        'reference_type' => ReturnModel::class,
                        'reference_id' => $return->id,
                        'user_id' => $request->user()->id,
                    ]);
                }
            }

            // After creating return, recompute sale status (refunded if fully returned)
            $this->recomputeSaleStatus($sale);

            DB::commit();

            return response()->json([
                'message' => 'Return processed successfully',
                'return' => $return->load(['items.product', 'sale', 'customer']),
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
                'message' => 'Return processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $return = ReturnModel::with(['sale', 'customer', 'user', 'items.product'])->findOrFail($id);
        return response()->json($return);
    }

    public function update(Request $request, $id)
    {
        try {
            $return = ReturnModel::with('items')->findOrFail($id);
            if ($return->status === 'cancelled') {
                return response()->json(['message' => 'Cannot update a cancelled return.'], 422);
            }

            $validated = $request->validate([
                'notes' => 'nullable|string',
                'status' => 'nullable|string|in:pending,approved,rejected,cancelled',
                'refund_method' => 'nullable|string|in:cash,card,credit_note,none',
                'refund_reference' => 'nullable|string|max:255',
            ]);

            $oldStatus = $return->status;
            $return->update($validated);

            // When transitioning pending -> approved: apply stock and movements (observers will post journal/daybook)
            $newStatus = $return->fresh()->status;
            if ($oldStatus === 'pending' && $newStatus === 'approved') {
                foreach ($return->items as $item) {
                    $product = \App\Models\Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock_quantity', $item->quantity);
                    }
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'return',
                        'quantity' => $item->quantity,
                        'reference_type' => ReturnModel::class,
                        'reference_id' => $return->id,
                        'user_id' => $return->user_id,
                    ]);
                }
            }

            return response()->json($return->fresh()->load(['items.product', 'sale', 'customer']));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Soft cancel: set status to cancelled, reverse stock and accounting.
     */
    public function destroy($id)
    {
        $return = ReturnModel::with(['items', 'sale.items'])->findOrFail($id);
        if ($return->status === 'cancelled') {
            return response()->json(['message' => 'Return is already cancelled.'], 200);
        }

        DB::beginTransaction();
        try {
            $return->update(['status' => 'cancelled']);

            // Reverse stock: decrement product and remove stock movements for this return
            foreach ($return->items as $item) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product) {
                    $product->decrement('stock_quantity', $item->quantity);
                }
            }
            StockMovement::where('reference_type', ReturnModel::class)->where('reference_id', $return->id)->delete();

            // Sale may no longer be fully returned after cancellation
            if ($return->sale) {
                $this->recomputeSaleStatus($return->sale);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to cancel return',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Return cancelled successfully.', 'return' => $return->fresh(['items.product', 'sale', 'customer'])]);
    }

    /**
     * Mark sale as refunded when all items are fully returned; otherwise keep as completed.
     */
    protected function recomputeSaleStatus(Sale $sale): void
    {
        $sale->loadMissing('items', 'returns.items');

        foreach ($sale->items as $item) {
            $returnedQty = ReturnItem::query()
                ->where('sale_item_id', $item->id)
                ->whereHas('returnModel', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->sum('quantity');

            if ((int) $returnedQty < (int) $item->quantity) {
                $sale->update(['status' => 'completed']);
                return;
            }
        }

        $sale->update(['status' => 'refunded']);
    }
}
