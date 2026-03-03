<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\ReportQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(protected int $reportExportId)
    {
    }

    public function handle(ReportQueryService $queries): void
    {
        /** @var ReportExport $export */
        $export = ReportExport::find($this->reportExportId);
        if (! $export || $export->status !== ReportExport::STATUS_PENDING) {
            return;
        }

        $export->update(['status' => ReportExport::STATUS_PROCESSING]);

        try {
            $filters = $export->filters ?? [];
            $disk = config('filesystems.default', 'local');
            $dir = 'report-exports/' . $export->user_id;
            $filename = $export->report_type . '-' . now()->format('YmdHis') . '.csv';
            $path = $dir . '/' . $filename;

            $handle = fopen('php://temp', 'w+');

            match ($export->report_type) {
                'products' => $this->writeProductsCsv($handle, $queries, $filters),
                'inventory' => $this->writeInventoryCsv($handle, $queries, $filters),
                'sales' => $this->writeSalesCsv($handle, $queries, $filters),
                'purchases' => $this->writePurchasesCsv($handle, $queries, $filters),
                'expenses' => $this->writeExpensesCsv($handle, $queries, $filters),
                'day-book' => $this->writeDayBookCsv($handle, $filters),
                default => throw new \RuntimeException('Unsupported report type: ' . $export->report_type),
            };

            rewind($handle);
            $contents = stream_get_contents($handle);
            fclose($handle);

            Storage::disk($disk)->put($path, $contents);

            $export->update([
                'status' => ReportExport::STATUS_COMPLETED,
                'file_path' => $path,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate report export', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);
            $export->update([
                'status' => ReportExport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function writeProductsCsv($handle, ReportQueryService $queries, array $filters): void
    {
        fputcsv($handle, ['Product', 'SKU', 'Category', 'Brand', 'Stock', 'Cost Price', 'Value', 'Status']);

        $query = $queries->buildProductQuery($filters)->orderBy('name');
        $query->chunk(500, function ($products) use ($handle) {
            foreach ($products as $p) {
                $value = $p->stock_quantity * $p->cost_price;
                $status = $p->stock_quantity === 0
                    ? 'Out of Stock'
                    : ($p->stock_quantity <= $p->min_stock_level ? 'Low Stock' : 'In Stock');
                fputcsv($handle, [
                    $p->name,
                    $p->sku,
                    $p->category->name ?? 'N/A',
                    $p->brand->name ?? 'N/A',
                    $p->stock_quantity,
                    $p->cost_price,
                    number_format($value, 2, '.', ''),
                    $status,
                ]);
            }
        });
    }

    protected function writeInventoryCsv($handle, ReportQueryService $queries, array $filters): void
    {
        fputcsv($handle, ['Date', 'Product', 'Type', 'Quantity', 'Unit Cost', 'User', 'Notes']);

        $query = $queries->buildInventoryQuery($filters)->orderBy('created_at', 'desc');
        $query->chunk(500, function ($movements) use ($handle) {
            foreach ($movements as $m) {
                fputcsv($handle, [
                    optional($m->created_at)->format('Y-m-d'),
                    $m->product->name ?? 'N/A',
                    strtoupper($m->type),
                    $m->quantity,
                    $m->unit_cost ?? 'N/A',
                    $m->user->name ?? 'System',
                    $m->notes ?? '',
                ]);
            }
        });
    }

    protected function writeSalesCsv($handle, ReportQueryService $queries, array $filters): void
    {
        fputcsv($handle, ['Invoice #', 'Date', 'Customer', 'Items', 'Subtotal', 'CGST', 'SGST', 'Discount', 'Total', 'Payment']);

        $query = $queries->buildSalesQuery($filters)->orderBy('sale_date', 'desc');
        $query->chunk(200, function ($sales) use ($handle) {
            foreach ($sales as $s) {
                $cgst = (float) $s->tax_amount / 2;
                $sgst = (float) $s->tax_amount / 2;
                fputcsv($handle, [
                    $s->invoice_number,
                    optional($s->sale_date)->format('Y-m-d'),
                    $s->customer->name ?? 'Walk-in',
                    $s->items?->count() ?? 0,
                    $s->subtotal,
                    number_format($cgst, 2, '.', ''),
                    number_format($sgst, 2, '.', ''),
                    $s->discount,
                    $s->total,
                    $s->payment_method,
                ]);
            }
        });
    }

    protected function writePurchasesCsv($handle, ReportQueryService $queries, array $filters): void
    {
        fputcsv($handle, ['Bill #', 'Date', 'Supplier', 'Items', 'Subtotal', 'Tax', 'Total']);

        $query = $queries->buildPurchaseQuery($filters)->orderBy('purchase_date', 'desc');
        $query->chunk(200, function ($purchases) use ($handle) {
            foreach ($purchases as $p) {
                fputcsv($handle, [
                    $p->bill_number,
                    optional($p->purchase_date)->format('Y-m-d'),
                    $p->supplier->name ?? '—',
                    $p->items?->count() ?? 0,
                    $p->subtotal,
                    $p->tax_amount,
                    $p->total,
                ]);
            }
        });
    }

    protected function writeExpensesCsv($handle, ReportQueryService $queries, array $filters): void
    {
        fputcsv($handle, ['Date', 'Voucher #', 'Category', 'Amount', 'Payment', 'Status', 'Reference']);

        $query = $queries->buildExpenseQuery($filters)->orderBy('expense_date', 'desc');
        $query->chunk(200, function ($expenses) use ($handle) {
            foreach ($expenses as $e) {
                fputcsv($handle, [
                    optional($e->expense_date)->format('Y-m-d'),
                    $e->voucher_number,
                    $e->expenseCategory->name ?? '—',
                    $e->amount,
                    $e->payment_method ?? '—',
                    $e->status,
                    $e->reference ?? '',
                ]);
            }
        });
    }

    protected function writeDayBookCsv($handle, array $filters): void
    {
        fputcsv($handle, ['Date', 'Reference', 'Type', 'Amount', 'User ID']);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $type = $filters['type'] ?? null;

        $query = DB::table('day_book_view'); // placeholder if materialized view exists

        // Fallback: if no dedicated view, build from existing union
        if (! DB::getSchemaBuilder()->hasTable('day_book_view')) {
            $sales = DB::table('sales')
                ->selectRaw("sale_date as date, invoice_number as ref, 'sale' as type, total as amount, user_id");
            $purchases = DB::table('purchases')
                ->selectRaw("purchase_date as date, bill_number as ref, 'purchase' as type, total as amount, user_id");
            $returns = DB::table('returns')
                ->selectRaw("return_date as date, return_number as ref, 'return' as type, -refund_amount as amount, user_id");
            $expenses = DB::table('expenses')
                ->selectRaw("expense_date as date, voucher_number as ref, 'expense' as type, amount as amount, user_id");

            if ($dateFrom) {
                $sales->whereDate('sale_date', '>=', $dateFrom);
                $purchases->whereDate('purchase_date', '>=', $dateFrom);
                $returns->whereDate('return_date', '>=', $dateFrom);
                $expenses->whereDate('expense_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $sales->whereDate('sale_date', '<=', $dateTo);
                $purchases->whereDate('purchase_date', '<=', $dateTo);
                $returns->whereDate('return_date', '<=', $dateTo);
                $expenses->whereDate('expense_date', '<=', $dateTo);
            }

            $union = $sales->unionAll($purchases)->unionAll($returns)->unionAll($expenses);
            $query = DB::table(DB::raw('(' . $union->toSql() . ') as day_book'))
                ->mergeBindings($union)
                ->orderBy('date')
                ->orderBy('ref');
        }

        if ($type) {
            $query->where('type', $type);
        }

        $query->orderBy('date')->orderBy('ref')->chunk(500, function ($rows) use ($handle) {
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->date ?? '',
                    $row->ref ?? '',
                    $row->type ?? '',
                    $row->amount ?? 0,
                    $row->user_id ?? '',
                ]);
            }
        });
    }
}

