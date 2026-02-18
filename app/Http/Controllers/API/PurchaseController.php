<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'user', 'items.product']);

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->has('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->orderBy('purchase_date', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'nullable|exists:customers,id',
                'purchase_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.001',
                'items.*.unit_cost' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
                'tax_rate' => 'nullable|numeric|min:0|max:100',
                'discount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $subtotal = 0;
            $itemRows = [];

            foreach ($validated['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $qty = (float) $row['quantity'];
                $unitCost = (float) $row['unit_cost'];
                $itemDiscount = (float) ($row['discount'] ?? 0);
                $taxRate = (float) ($validated['tax_rate'] ?? 0);
                $itemSubtotal = $qty * $unitCost - $itemDiscount;
                $itemTaxAmount = $itemSubtotal * ($taxRate / 100);
                $itemTotal = $itemSubtotal + $itemTaxAmount;

                $subtotal += $qty * $unitCost;
                $itemRows[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'discount' => $itemDiscount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $itemTaxAmount,
                    'subtotal' => $qty * $unitCost,
                    'total' => $itemTotal,
                ];
            }

            $discount = (float) ($validated['discount'] ?? 0);
            $taxRate = (float) ($validated['tax_rate'] ?? 0);
            $taxAmount = ($subtotal - $discount) * ($taxRate / 100);
            $total = $subtotal - $discount + $taxAmount;

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'] ?? null,
                'user_id' => $request->user()->id,
                'purchase_date' => $validated['purchase_date'],
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount' => $discount,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($itemRows as $row) {
                $itemSubtotal = $row['quantity'] * $row['unit_cost'];
                $itemTotal = $itemSubtotal - $row['discount'] + $row['tax_amount'];

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $row['product']->id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'tax_rate' => $row['tax_rate'],
                    'tax_amount' => $row['tax_amount'],
                    'discount' => $row['discount'],
                    'subtotal' => $itemSubtotal,
                    'total' => $itemTotal,
                ]);

                $row['product']->increment('stock_quantity', $row['quantity']);

                StockMovement::create([
                    'product_id' => $row['product']->id,
                    'type' => 'purchase',
                    'quantity' => (int) round($row['quantity']),
                    'unit_cost' => $row['unit_cost'],
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'user_id' => $request->user()->id,
                    'notes' => 'Purchase bill ' . $purchase->bill_number,
                ]);
            }

            DB::commit();
            return response()->json($purchase->load(['supplier', 'user', 'items.product']), 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Purchase failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $purchase = Purchase::with(['supplier', 'user', 'items.product'])->findOrFail($id);
        return response()->json($purchase);
    }

    public function getBill($id)
    {
        $purchase = Purchase::with(['supplier', 'user', 'items.product'])->findOrFail($id);
        return response()->json($purchase);
    }
}
