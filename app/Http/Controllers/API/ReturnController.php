<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ReturnModel;
use App\Models\ReturnItem;
use App\Models\Sale;
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

        $returns = $query->orderBy('return_date', 'desc')->paginate($request->per_page ?? 15);
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
            ]);

            DB::beginTransaction();

            $sale = Sale::findOrFail($validated['sale_id']);
            $refundAmount = 0;

            foreach ($validated['items'] as $itemData) {
                $refundAmount += $itemData['quantity'] * $itemData['unit_price'];
            }

            $return = ReturnModel::create([
                'sale_id' => $validated['sale_id'],
                'customer_id' => $sale->customer_id,
                'user_id' => $request->user()->id,
                'return_date' => now(),
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'refund_amount' => $refundAmount,
                'status' => 'approved',
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
}
