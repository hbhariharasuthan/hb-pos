<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'user', 'items.product']);

        if ($request->has('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sales = $query->orderBy('sale_date', 'desc')->paginate($request->per_page ?? 15);
        return response()->json($sales);
    }

    public function show($id)
    {
        $sale = Sale::with(['customer', 'user', 'items.product'])->findOrFail($id);
        return response()->json($sale);
    }

    public function getInvoice($id)
    {
        $sale = Sale::with(['customer', 'user', 'items.product'])->findOrFail($id);
        return response()->json($sale);
    }
}
