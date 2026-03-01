<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Traits\HasDropdownPagination;
use App\Http\Controllers\Controller;
use App\Models\GstSlab;
use Illuminate\Http\Request;

class GstSlabController extends Controller
{
    use HasDropdownPagination;

    public function index(Request $request)
    {
        $query = GstSlab::query()->orderBy('hsn_code');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('hsn_code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        return $this->paginateForDropdown($query, $perPage);
    }
}
