<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Customer;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class POSController extends Controller
{
    /**
     * Products for POS screen and (optionally) paginated consumers.
     *
     * Default behaviour stays the same as before: return a plain array of
     * products for the POS grid so existing Vue code that does
     * `v-for="product in products"` continues to work.
     *
     * If `per_page` or `paginated=1` is passed, a standard Laravel paginator
     * JSON structure is returned instead (for dropdowns / infinite scroll).
     */
    public function getProducts(Request $request)
    {
        $query = Product::where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with('category');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        $query->orderBy('name');

        // Explicit paginated mode for dropdowns / infinite scroll
        if ($request->has('per_page') || $request->boolean('paginated')) {
            $perPage = (int) $request->input('per_page', 10);
            return response()->json($query->paginate($perPage));
        }

        // Default: plain array (backwards compatible with existing POS UI)
        return response()->json($query->get());
    }

    public function processSale(Request $request)
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|exists:customers,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
                'tax_rate' => 'nullable|numeric|min:0|max:100',
                'discount' => 'nullable|numeric|min:0',
                'payment_method' => 'required|in:cash,card,credit,mixed',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $subtotal = 0;
            $items = [];

            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                if ($product->stock_quantity < $itemData['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->name}. Available: {$product->stock_quantity}"]
                    ]);
                }

                $itemSubtotal = $itemData['quantity'] * $itemData['unit_price'];
                $itemDiscount = $itemData['discount'] ?? 0;
                $itemTaxRate = $validated['tax_rate'] ?? 0;
                $itemTaxAmount = ($itemSubtotal - $itemDiscount) * ($itemTaxRate / 100);
                $itemTotal = $itemSubtotal - $itemDiscount + $itemTaxAmount;

                $subtotal += $itemSubtotal;
                $items[] = [
                    'product' => $product,
                    'data' => $itemData,
                    'subtotal' => $itemSubtotal,
                    'discount' => $itemDiscount,
                    'tax_amount' => $itemTaxAmount,
                    'total' => $itemTotal,
                ];
            }

            $discount = $validated['discount'] ?? 0;
            $taxRate = $validated['tax_rate'] ?? 0;
            $taxAmount = ($subtotal - $discount) * ($taxRate / 100);
            $total = $subtotal - $discount + $taxAmount;

            // Validate credit limit before processing sale
            $saleService = new SaleService();
            $saleService->validateCreditLimit(
                $validated['customer_id'],
                $total,
                $validated['payment_method']
            );

            $sale = Sale::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Process credit sale and update customer balance
            $saleService->processCreditSale($sale);

            foreach ($items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['data']['quantity'],
                    'unit_price' => $item['data']['unit_price'],
                    'discount' => $item['discount'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $item['tax_amount'],
                    'subtotal' => $item['subtotal'],
                    'total' => $item['total'],
                ]);

                $item['product']->decrement('stock_quantity', $item['data']['quantity']);

                StockMovement::create([
                    'product_id' => $item['product']->id,
                    'type' => 'sale',
                    'quantity' => -$item['data']['quantity'],
                    'unit_cost' => $item['product']->cost_price,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'user_id' => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Sale processed successfully',
                'sale' => $sale->load(['items.product', 'customer', 'user']),
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
                'message' => 'Sale processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
