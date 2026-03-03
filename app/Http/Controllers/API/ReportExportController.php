<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ReportExportController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'report_type' => 'required|string|in:products,inventory,sales,purchases,expenses,day-book',
            'format' => 'sometimes|string|in:csv',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date',
            'filters.type' => 'nullable|string|in:sale,purchase,return,expense', // day-book type
        ]);

        $filters = $validated['filters'] ?? [];
        $format = $validated['format'] ?? 'csv';

        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => $validated['report_type'],
            'format' => $format,
            'filters' => $filters,
            'status' => ReportExport::STATUS_PENDING,
        ]);

        // Explicitly send export job to the database queue connection
        GenerateReportExport::dispatch($export->id)->onConnection('database');

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
        ], 202);
    }

    public function show(ReportExport $reportExport)
    {
        Gate::authorize('view', $reportExport);

        return response()->json([
            'id' => $reportExport->id,
            'status' => $reportExport->status,
            'report_type' => $reportExport->report_type,
            'format' => $reportExport->format,
            'created_at' => $reportExport->created_at,
        ]);
    }

    public function download(ReportExport $reportExport)
    {
        Gate::authorize('view', $reportExport);

        if ($reportExport->status !== ReportExport::STATUS_COMPLETED || ! $reportExport->file_path) {
            return response()->json(['message' => 'Export not ready'], 409);
        }

        $disk = config('filesystems.default', 'local');
        if (! Storage::disk($disk)->exists($reportExport->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $filename = $reportExport->report_type . '_report_' . now()->format('Y-m-d_His') . '.' . $reportExport->format;

        return Storage::disk($disk)->download($reportExport->file_path, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

