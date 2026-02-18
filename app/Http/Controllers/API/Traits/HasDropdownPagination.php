<?php

namespace App\Http\Controllers\API\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasDropdownPagination
{
    /**
     * Paginate query results for dropdown/select lists
     * Defaults to 10 items per page
     *
     * @param Builder $query
     * @param int $perPage
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginateForDropdown($query, $perPage = 10)
    {
        return response()->json($query->paginate($perPage));
    }
}
