<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GstSlab;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GstSlabController extends Controller
{
    public function index(Request $request)
    {
        if ($request->route()->getName() === 'gst-slabs.all') {
            $slabs = GstSlab::withCount('products')->orderBy('hsn_code')->get();
            return response()->json($slabs);
        }

        $query = GstSlab::withCount('products')->orderBy('hsn_code');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('hsn_code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'hsn_code'     => 'required|string|max:10',
                'gst_percent'  => 'required|numeric|min:0|max:100',
                'description'  => 'nullable|string|max:255',
            ]);

            $slab = GstSlab::create($validated);
            return response()->json($slab, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'   => $e->errors(),
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $slab = GstSlab::findOrFail($id);

            $validated = $request->validate([
                'hsn_code'     => 'sometimes|required|string|max:10',
                'gst_percent'  => 'sometimes|required|numeric|min:0|max:100',
                'description'  => 'nullable|string|max:255',
            ]);

            $slab->update($validated);
            return response()->json($slab);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'   => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $slab = GstSlab::findOrFail($id);

        $productsCount = $slab->products()->count();
        if ($productsCount > 0) {
            throw ValidationException::withMessages([
                'gst_slab_id' => "This GST slab is used by {$productsCount} product(s). Remove or change the slab from those products first.",
            ]);
        }

        $slab->delete();
        return response()->json(['message' => 'GST slab deleted successfully']);
    }
}
