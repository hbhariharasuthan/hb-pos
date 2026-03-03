<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\ReturnModel;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Exports\DayBookExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected const DAY_BOOK_CHUNK_SIZE = 2000;

    /**
     * Build the day book union query (sales, purchases, returns, expenses) with optional date filter.
     */
    protected function buildDayBookQuery(?string $dateFrom, ?string $dateTo)
    {
        // Use literal type labels instead of bound parameters to avoid
        // parameter-mismatch issues in the UNION subquery.
        $salesQuery = Sale::query()
            ->selectRaw("sale_date as date, invoice_number as ref, 'sale' as type, total as amount, user_id");
        $purchasesQuery = Purchase::query()
            ->selectRaw("purchase_date as date, bill_number as ref, 'purchase' as type, total as amount, user_id");
        $returnsQuery = ReturnModel::query()
            ->selectRaw("return_date as date, return_number as ref, 'return' as type, -refund_amount as amount, user_id");
        $expensesQuery = Expense::query()
            ->selectRaw("expense_date as date, voucher_number as ref, 'expense' as type, amount as amount, user_id");

        if ($dateFrom) {
            $salesQuery->whereDate('sale_date', '>=', $dateFrom);
            $purchasesQuery->whereDate('purchase_date', '>=', $dateFrom);
            $returnsQuery->whereDate('return_date', '>=', $dateFrom);
            $expensesQuery->whereDate('expense_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $salesQuery->whereDate('sale_date', '<=', $dateTo);
            $purchasesQuery->whereDate('purchase_date', '<=', $dateTo);
            $returnsQuery->whereDate('return_date', '<=', $dateTo);
            $expensesQuery->whereDate('expense_date', '<=', $dateTo);
        }

        $union = $salesQuery
            ->unionAll($purchasesQuery)
            ->unionAll($returnsQuery)
            ->unionAll($expensesQuery);

        return DB::table(DB::raw("({$union->toSql()}) as day_book"))
            ->mergeBindings($union->getQuery())
            ->orderBy('date')
            ->orderBy('ref');
    }

    public function productReport(Request $request)
    {
        $query = Product::with(['category', 'brand']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('low_stock_only')) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        if ($request->has('date_from') || $request->has('date_to')) {
            $query->whereHas('stockMovements', function ($q) use ($request) {
                if ($request->has('date_from')) {
                    $q->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->has('date_to')) {
                    $q->whereDate('created_at', '<=', $request->date_to);
                }
            });
        }

        $statsQuery = clone $query;
        $stats = [
            'total_products' => $statsQuery->count(),
            'total_value' => (float) $statsQuery->get()->sum(fn ($p) => $p->stock_quantity * $p->cost_price),
            'low_stock_count' => (clone $statsQuery)->whereRaw('stock_quantity <= min_stock_level')->count(),
            'out_of_stock_count' => (clone $statsQuery)->where('stock_quantity', 0)->count(),
            'total_stock_quantity' => $statsQuery->sum('stock_quantity'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'stats' => $stats,
            'products' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function inventoryReport(Request $request)
    {
        $query = StockMovement::with(['product', 'user']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $baseQuery = clone $query;
        $stats = [
            'total_movements' => $baseQuery->count(),
            'purchases' => (clone $baseQuery)->where('type', 'purchase')->sum('quantity'),
            'sales' => abs((clone $baseQuery)->where('type', 'sale')->sum('quantity')),
            'returns' => (clone $baseQuery)->where('type', 'return')->sum('quantity'),
            'adjustments' => (clone $baseQuery)->where('type', 'adjustment')->sum('quantity'),
            'total_value' => (float) (clone $baseQuery)->where('type', 'purchase')->get()->sum(fn ($m) => $m->quantity * ($m->unit_cost ?? 0)),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'stats' => $stats,
            'movements' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function salesReport(Request $request)
    {
        $query = Sale::with(['customer', 'user', 'items.product.brand']);

        if ($request->has('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $baseQuery = clone $query;
        $totalSales = $baseQuery->count();
        $totalRevenue = (float) (clone $baseQuery)->sum('total');
        $totalItemsSold = (int) (clone $baseQuery)->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')->sum('sale_items.quantity');
        $stats = [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'total_items_sold' => $totalItemsSold,
            'average_sale' => $totalSales > 0 ? $totalRevenue / $totalSales : 0,
            'cash_sales' => (float) (clone $baseQuery)->where('payment_method', 'cash')->sum('total'),
            'card_sales' => (float) (clone $baseQuery)->where('payment_method', 'card')->sum('total'),
            'credit_sales' => (float) (clone $baseQuery)->where('payment_method', 'credit')->sum('total'),
            'total_tax' => (float) (clone $baseQuery)->sum('tax_amount'),
            'total_cgst' => (float) (clone $baseQuery)->sum('tax_amount') / 2,
            'total_sgst' => (float) (clone $baseQuery)->sum('tax_amount') / 2,
            'total_discount' => (float) (clone $baseQuery)->sum('discount'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('sale_date', 'desc')->paginate($perPage);
        $sales = $paginated->items();

        $dailySales = collect([]);
        $productSales = [];
        foreach ($sales as $sale) {
            foreach ($sale->items ?? [] as $item) {
                $productId = $item->product_id;
                if (! isset($productSales[$productId])) {
                    $productSales[$productId] = [
                        'product_id' => $productId,
                        'product_name' => $item->product->name ?? 'N/A',
                        'sku' => $item->product->sku ?? '',
                        'brand_name' => optional($item->product->brand)->name,
                        'quantity' => 0,
                        'revenue' => 0,
                    ];
                }
                $productSales[$productId]['quantity'] += $item->quantity;
                $productSales[$productId]['revenue'] += $item->total;
            }
        }
        $topProducts = collect($productSales)->sortByDesc('revenue')->take(10)->values();

        return response()->json([
            'stats' => $stats,
            'sales' => $sales,
            'daily_sales' => $dailySales,
            'top_products' => $topProducts,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function purchaseReport(Request $request)
    {
        $query = Purchase::with(['supplier', 'items.product']);

        if ($request->has('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }

        $baseQuery = clone $query;
        $stats = [
            'total_purchases' => $baseQuery->count(),
            'total_amount' => (float) $baseQuery->sum('total'),
            'total_items' => (int) (clone $baseQuery)->join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')->sum('purchase_items.quantity'),
            'total_tax' => (float) $baseQuery->sum('tax_amount'),
            'total_subtotal' => (float) $baseQuery->sum('subtotal'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('purchase_date', 'desc')->paginate($perPage);

        return response()->json([
            'stats' => $stats,
            'purchases' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function expenseReport(Request $request)
    {
        $query = Expense::with(['expenseCategory', 'user']);

        if ($request->has('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        $baseQuery = clone $query;
        $stats = [
            'total_expenses' => $baseQuery->count(),
            'total_amount' => (float) $baseQuery->sum('amount'),
            'approved_count' => (clone $baseQuery)->where('status', 'approved')->count(),
            'pending_count' => (clone $baseQuery)->where('status', 'pending')->count(),
            'cancelled_count' => (clone $baseQuery)->where('status', 'cancelled')->count(),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('expense_date', 'desc')->paginate($perPage);

        return response()->json([
            'stats' => $stats,
            'expenses' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Day Book report: chronological list of financial transactions (sales, purchases, returns, expenses).
     * Single UNION query over existing tables — no separate day_book_entries table (Phase 1).
     * Query params: date_from, date_to (optional), per_page (default 15), page.
     */
    public function dayBookReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $typeFilter = $request->query('type');
        $allowedTypes = ['sale', 'purchase', 'return', 'expense'];
        if ($typeFilter !== null && $typeFilter !== '' && ! in_array($typeFilter, $allowedTypes, true)) {
            $typeFilter = null;
        }
        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        // Base query for stats (all types)
        $baseQuery = $this->buildDayBookQuery($dateFrom, $dateTo);

        // Totals grouped by type for widgets (remove ORDER BY to satisfy ONLY_FULL_GROUP_BY)
        $totalsByType = (clone $baseQuery)
            ->reorder()
            ->select('type', \DB::raw('COUNT(*) as total_entries'), \DB::raw('SUM(amount) as total_amount'))
            ->groupBy('type')
            ->get();

        // Entries: if a specific type is requested, build a focused query for that type;
        // otherwise use the full union.
        if ($typeFilter) {
            switch ($typeFilter) {
                case 'sale':
                    $entriesQuery = Sale::query()
                        ->selectRaw("sale_date as date, invoice_number as ref, 'sale' as type, total as amount, user_id");
                    if ($dateFrom) {
                        $entriesQuery->whereDate('sale_date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $entriesQuery->whereDate('sale_date', '<=', $dateTo);
                    }
                    break;
                case 'purchase':
                    $entriesQuery = Purchase::query()
                        ->selectRaw("purchase_date as date, bill_number as ref, 'purchase' as type, total as amount, user_id");
                    if ($dateFrom) {
                        $entriesQuery->whereDate('purchase_date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $entriesQuery->whereDate('purchase_date', '<=', $dateTo);
                    }
                    break;
                case 'return':
                    $entriesQuery = ReturnModel::query()
                        ->selectRaw("return_date as date, return_number as ref, 'return' as type, -refund_amount as amount, user_id");
                    if ($dateFrom) {
                        $entriesQuery->whereDate('return_date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $entriesQuery->whereDate('return_date', '<=', $dateTo);
                    }
                    break;
                case 'expense':
                    $entriesQuery = Expense::query()
                        ->selectRaw("expense_date as date, voucher_number as ref, 'expense' as type, amount as amount, user_id");
                    if ($dateFrom) {
                        $entriesQuery->whereDate('expense_date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $entriesQuery->whereDate('expense_date', '<=', $dateTo);
                    }
                    break;
                default:
                    $entriesQuery = $this->buildDayBookQuery($dateFrom, $dateTo);
                    break;
            }
        } else {
            $entriesQuery = $this->buildDayBookQuery($dateFrom, $dateTo);
        }

        $total = (clone $entriesQuery)->count();
        $currentPage = (int) $request->input('page', 1);
        $entries = $entriesQuery
            ->orderBy('date')
            ->orderBy('ref')
            ->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $stats = [
            'total_entries' => $total,
            'totals_by_type' => $totalsByType,
        ];

        return response()->json([
            'stats' => $stats,
            'entries' => $entries,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Day Book export (CSV or Excel). Streams in chunks to support large records.
     * Query params: format=csv|xlsx, date_from, date_to.
     */
    public function dayBookExport(Request $request)
    {
        $request->validate(['format' => 'required|in:csv,xlsx']);
        $format = $request->input('format');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $filename = 'day-book-' . now()->format('Y-m-d-His');

        if ($format === 'csv') {
            return $this->dayBookExportCsv($dateFrom, $dateTo, $filename);
        }

        return Excel::download(
            new DayBookExport($dateFrom, $dateTo, $this),
            $filename . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Stream CSV in chunks so large record sets don't load into memory.
     */
    protected function dayBookExportCsv(?string $dateFrom, ?string $dateTo, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ];

        return new StreamedResponse(function () use ($dateFrom, $dateTo) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, ['Date', 'Reference', 'Type', 'Amount', 'User Name']);

            $offset = 0;
            $chunkSize = self::DAY_BOOK_CHUNK_SIZE;
            do {
                $query = $this->buildDayBookQuery($dateFrom, $dateTo);
                $rows = $query->offset($offset)->limit($chunkSize)->get();
                $userIds = $rows->pluck('user_id')->filter()->unique();
                $userNames = User::whereIn('id', $userIds)->pluck('name', 'id');
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->date ?? '',
                        $row->ref ?? '',
                        $row->type ?? '',
                        $row->amount ?? 0,
                        $row->user_id ? ($userNames[$row->user_id] ?? $row->user_id) : '',
                    ]);
                }
                $offset += $chunkSize;
            } while ($rows->count() === $chunkSize);

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Fetch one chunk of day book rows (for Excel export). Used by DayBookExport.
     * Each row includes user_name (resolved from user_id) for export display.
     */
    public function getDayBookChunk(?string $dateFrom, ?string $dateTo, int $limit, int $offset)
    {
        $rows = $this->buildDayBookQuery($dateFrom, $dateTo)->offset($offset)->limit($limit)->get();
        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
        $userNames = $userIds ? User::whereIn('id', $userIds)->pluck('name', 'id') : collect();
        foreach ($rows as $row) {
            $row->user_name = $row->user_id ? ($userNames[$row->user_id] ?? (string) $row->user_id) : '';
        }
        return $rows;
    }

    /**
     * Ledger report: lines for an account with running balance (Phase 3).
     * Query params: account_id (required), date_from, date_to, per_page.
     */
    public function ledgerReport(Request $request)
    {
        $request->validate(['account_id' => 'required|exists:accounts,id']);
        $accountId = (int) $request->account_id;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = max(1, min(100, (int) $request->input('per_page', 25)));

        $account = Account::findOrFail($accountId);
        $openingBalance = (float) $account->opening_balance;

        $lines = JournalEntryLine::query()
            ->where('journal_entry_lines.account_id', $accountId)
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->when($dateFrom, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $dateTo))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->select(
                'journal_entry_lines.id',
                'journal_entry_lines.journal_entry_id',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                'journal_entries.entry_date',
                'journal_entries.voucher_number',
                'journal_entries.narration'
            )
            ->get();

        $running = $openingBalance;
        $ledgerLines = [];
        foreach ($lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $running += $debit - $credit;
            $ledgerLines[] = [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'entry_date' => $line->entry_date,
                'voucher_number' => $line->voucher_number,
                'narration' => $line->narration,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($running, 2),
            ];
        }

        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $stats = [
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'opening_balance' => $openingBalance,
            'total_debit' => (float) $totalDebit,
            'total_credit' => (float) $totalCredit,
            'closing_balance' => $openingBalance + (float) $totalDebit - (float) $totalCredit,
        ];

        $paginated = collect($ledgerLines)->forPage((int) $request->input('page', 1), $perPage);

        return response()->json([
            'stats' => $stats,
            'lines' => $paginated->values()->all(),
            'current_page' => (int) $request->input('page', 1),
            'per_page' => $perPage,
            'total' => count($ledgerLines),
            'last_page' => (int) ceil(count($ledgerLines) / $perPage),
        ]);
    }

    /**
     * Trial balance: all accounts with debit/credit totals and balance for period (Phase 3).
     */
    public function trialBalanceReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        $periodLines = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->when($dateFrom, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $dateTo))
            ->groupBy('account_id')
            ->selectRaw('account_id, sum(journal_entry_lines.debit) as total_debit, sum(journal_entry_lines.credit) as total_credit')
            ->get()
            ->keyBy('account_id');

        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($accounts as $account) {
            $ob = (float) $account->opening_balance;
            $period = $periodLines->get($account->id);
            $dr = (float) ($period->total_debit ?? 0);
            $cr = (float) ($period->total_credit ?? 0);
            $balance = $ob + $dr - $cr;
            $totalDebit += $balance > 0 ? $balance : 0;
            $totalCredit += $balance < 0 ? abs($balance) : 0;
            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'opening_balance' => $ob,
                'period_debit' => $dr,
                'period_credit' => $cr,
                'closing_debit' => $balance > 0 ? $balance : 0,
                'closing_credit' => $balance < 0 ? abs($balance) : 0,
            ];
        }

        return response()->json([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'rows' => $rows,
        ]);
    }

    /**
     * Profit & Loss: income - expense for period (Phase 3).
     */
    public function profitLossReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $incomeAccounts = Account::where('type', Account::TYPE_INCOME)->where('is_active', true)->pluck('id');
        $expenseAccounts = Account::where('type', Account::TYPE_EXPENSE)->where('is_active', true)->pluck('id');

        $incomeTotal = $this->accountPeriodTotal($incomeAccounts, $dateFrom, $dateTo, true);
        $expenseTotal = $this->accountPeriodTotal($expenseAccounts, $dateFrom, $dateTo, false);

        $incomeBreakdown = $this->accountPeriodBreakdown($incomeAccounts->toArray(), $dateFrom, $dateTo, true);
        $expenseBreakdown = $this->accountPeriodBreakdown($expenseAccounts->toArray(), $dateFrom, $dateTo, false);

        $netProfit = $incomeTotal - $expenseTotal;

        return response()->json([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'income' => ['total' => $incomeTotal, 'breakdown' => $incomeBreakdown],
            'expense' => ['total' => $expenseTotal, 'breakdown' => $expenseBreakdown],
            'net_profit' => $netProfit,
        ]);
    }

    /**
     * Balance sheet: assets, liabilities, equity as of date (Phase 3).
     */
    public function balanceSheetReport(Request $request)
    {
        $asOnDate = $request->input('date_to') ?? $request->input('as_on_date') ?? now()->format('Y-m-d');

        $assets = $this->accountBalancesByType(Account::TYPE_ASSET, $asOnDate);
        $liabilities = $this->accountBalancesByType(Account::TYPE_LIABILITY, $asOnDate);
        $equity = $this->accountBalancesByType(Account::TYPE_EQUITY, $asOnDate);

        $totalAssets = $assets['total'];
        $totalLiabEquity = $liabilities['total'] + $equity['total'];
        $plBalance = $this->profitLossBalanceToDate($asOnDate);
        $totalEquityInclPL = $equity['total'] + $plBalance;

        return response()->json([
            'as_on_date' => $asOnDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'profit_loss_to_date' => $plBalance,
            'total_assets' => $totalAssets,
            'total_liabilities_equity' => $liabilities['total'] + $totalEquityInclPL,
        ]);
    }

    /**
     * GST outward report (sales with tax) - Phase 3.
     */
    public function gstOutwardReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Sale::with(['customer', 'items.product.gstSlab'])
            ->when($dateFrom, fn ($q) => $q->whereDate('sale_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sale_date', '<=', $dateTo))
            ->orderBy('sale_date');

        $sales = $query->get();
        $rows = [];
        $totalTaxable = 0;
        $totalTax = 0;
        $totalAmount = 0;
        foreach ($sales as $sale) {
            $taxable = (float) $sale->subtotal - (float) $sale->discount;
            $tax = (float) $sale->tax_amount;
            $total = (float) $sale->total;
            $totalTaxable += $taxable;
            $totalTax += $tax;
            $totalAmount += $total;
            $rows[] = [
                'invoice_number' => $sale->invoice_number,
                'sale_date' => $sale->sale_date->format('Y-m-d'),
                'customer' => $sale->customer ? $sale->customer->name : null,
                'gst_number' => $sale->customer ? $sale->customer->gst_number : null,
                'taxable_value' => round($taxable, 2),
                'tax_amount' => round($tax, 2),
                'total' => round($total, 2),
            ];
        }

        return response()->json([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_taxable_value' => round($totalTaxable, 2),
            'total_tax' => round($totalTax, 2),
            'total_amount' => round($totalAmount, 2),
            'rows' => $rows,
        ]);
    }

    /**
     * GST purchase register (for ITC) - Phase 3.
     */
    public function gstPurchaseRegisterReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Purchase::with(['supplier', 'items.product.gstSlab'])
            ->when($dateFrom, fn ($q) => $q->whereDate('purchase_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('purchase_date', '<=', $dateTo))
            ->orderBy('purchase_date');

        $purchases = $query->get();
        $rows = [];
        $totalTaxable = 0;
        $totalTax = 0;
        $totalAmount = 0;
        foreach ($purchases as $purchase) {
            $taxable = (float) $purchase->subtotal - (float) $purchase->discount;
            $tax = (float) $purchase->tax_amount;
            $total = (float) $purchase->total;
            $totalTaxable += $taxable;
            $totalTax += $tax;
            $totalAmount += $total;
            $rows[] = [
                'bill_number' => $purchase->bill_number,
                'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
                'supplier' => $purchase->supplier ? $purchase->supplier->name : null,
                'gst_number' => $purchase->supplier ? $purchase->supplier->gst_number : null,
                'taxable_value' => round($taxable, 2),
                'tax_amount' => round($tax, 2),
                'total' => round($total, 2),
            ];
        }

        return response()->json([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_taxable_value' => round($totalTaxable, 2),
            'total_tax' => round($totalTax, 2),
            'total_amount' => round($totalAmount, 2),
            'rows' => $rows,
        ]);
    }

    protected function accountPeriodTotal($accountIds, ?string $dateFrom, ?string $dateTo, bool $isIncome): float
    {
        if ($accountIds->isEmpty()) {
            return 0.0;
        }
        $opening = Account::whereIn('id', $accountIds)->sum('opening_balance');
        $q = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('account_id', $accountIds)
            ->when($dateFrom, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $dateTo));
        $dr = (float) (clone $q)->sum('journal_entry_lines.debit');
        $cr = (float) (clone $q)->sum('journal_entry_lines.credit');
        if ($isIncome) {
            return $opening + $cr - $dr;
        }
        return $opening + $dr - $cr;
    }

    protected function accountPeriodBreakdown(array $accountIds, ?string $dateFrom, ?string $dateTo, bool $isIncome): array
    {
        if (empty($accountIds)) {
            return [];
        }
        $accounts = Account::whereIn('id', $accountIds)->get()->keyBy('id');
        $lines = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('account_id', $accountIds)
            ->when($dateFrom, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $dateTo))
            ->selectRaw('account_id, sum(journal_entry_lines.debit) as dr, sum(journal_entry_lines.credit) as cr')
            ->groupBy('account_id')
            ->get();
        $breakdown = [];
        foreach ($lines as $line) {
            $account = $accounts->get($line->account_id);
            $ob = (float) ($account ? $account->opening_balance : 0);
            $dr = (float) $line->dr;
            $cr = (float) $line->cr;
            $balance = $isIncome ? ($ob + $cr - $dr) : ($ob + $dr - $cr);
            $breakdown[] = [
                'account_id' => $line->account_id,
                'code' => $account ? $account->code : '',
                'name' => $account ? $account->name : '',
                'amount' => round($balance, 2),
            ];
        }
        return $breakdown;
    }

    protected function accountBalancesByType(string $type, string $asOnDate): array
    {
        $accounts = Account::where('type', $type)->where('is_active', true)->orderBy('code')->get();
        $lines = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->whereDate('journal_entries.entry_date', '<=', $asOnDate)
            ->selectRaw('account_id, sum(journal_entry_lines.debit) as dr, sum(journal_entry_lines.credit) as cr')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');
        $rows = [];
        $total = 0;
        foreach ($accounts as $account) {
            $ob = (float) $account->opening_balance;
            $l = $lines->get($account->id);
            $dr = $l ? (float) $l->dr : 0;
            $cr = $l ? (float) $l->cr : 0;
            $balance = $ob + $dr - $cr;
            $total += $balance;
            $rows[] = ['code' => $account->code, 'name' => $account->name, 'balance' => round($balance, 2)];
        }
        return ['rows' => $rows, 'total' => round($total, 2)];
    }

    protected function profitLossBalanceToDate(string $asOnDate): float
    {
        $incomeIds = Account::where('type', Account::TYPE_INCOME)->where('is_active', true)->pluck('id');
        $expenseIds = Account::where('type', Account::TYPE_EXPENSE)->where('is_active', true)->pluck('id');
        $income = $this->accountPeriodTotal($incomeIds, null, $asOnDate, true);
        $expense = $this->accountPeriodTotal($expenseIds, null, $asOnDate, false);
        return $income - $expense;
    }

    public function dashboardStats()
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        // Sales stats
        $todaySales = Sale::whereDate('sale_date', today())->get();
        $monthSales = Sale::where('sale_date', '>=', $thisMonth)->get();

        // Purchase stats (today & this month)
        $todayPurchases = Purchase::whereDate('purchase_date', today())->get();
        $monthPurchases = Purchase::where('purchase_date', '>=', $thisMonth)->get();

        // Expense stats (today & this month; exclude cancelled)
        $todayExpenses = Expense::whereDate('expense_date', today())->whereNotIn('status', ['cancelled'])->get();
        $monthExpenses = Expense::where('expense_date', '>=', $thisMonth)->whereNotIn('status', ['cancelled'])->get();

        // Day book summary for today (sales, purchases, returns, expenses)
        $dayBookToday = [
            'sale' => (float) Sale::whereDate('sale_date', today())->sum('total'),
            'purchase' => (float) Purchase::whereDate('purchase_date', today())->sum('total'),
            'return' => (float) ReturnModel::whereDate('return_date', today())->sum('refund_amount'),
            'expense' => (float) Expense::whereDate('expense_date', today())->whereNotIn('status', ['cancelled'])->sum('amount'),
        ];
        $dayBookTodayCount = [
            'sale' => Sale::whereDate('sale_date', today())->count(),
            'purchase' => Purchase::whereDate('purchase_date', today())->count(),
            'return' => ReturnModel::whereDate('return_date', today())->count(),
            'expense' => Expense::whereDate('expense_date', today())->whereNotIn('status', ['cancelled'])->count(),
        ];

        // Credit-based: credit sales (today & month) and outstanding customer balance
        $creditSalesToday = (float) Sale::whereDate('sale_date', today())->where('payment_method', 'credit')->sum('total');
        $creditSalesMonth = (float) Sale::where('sale_date', '>=', $thisMonth)->where('payment_method', 'credit')->sum('total');
        $creditSalesTodayCount = Sale::whereDate('sale_date', today())->where('payment_method', 'credit')->count();
        $creditSalesMonthCount = Sale::where('sale_date', '>=', $thisMonth)->where('payment_method', 'credit')->count();
        $outstandingBalance = (float) Customer::where('is_active', true)->where('balance', '>', 0)->sum('balance');

        // Product stats
        $totalProducts = Product::where('is_active', true)->count();
        $lowStockProducts = Product::whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->count();

        // Inventory value
        $inventoryValue = Product::where('is_active', true)
            ->get()
            ->sum(function ($product) {
                return $product->stock_quantity * $product->cost_price;
            });

        return response()->json([
            'sales' => [
                'today_revenue' => (float) $todaySales->sum('total'),
                'today_count' => $todaySales->count(),
                'month_revenue' => (float) $monthSales->sum('total'),
                'month_count' => $monthSales->count(),
            ],
            'purchases' => [
                'today_amount' => (float) $todayPurchases->sum('total'),
                'today_count' => $todayPurchases->count(),
                'month_amount' => (float) $monthPurchases->sum('total'),
                'month_count' => $monthPurchases->count(),
            ],
            'expenses' => [
                'today_amount' => (float) $todayExpenses->sum('amount'),
                'today_count' => $todayExpenses->count(),
                'month_amount' => (float) $monthExpenses->sum('amount'),
                'month_count' => $monthExpenses->count(),
            ],
            'day_book' => [
                'today' => $dayBookToday,
                'today_count' => $dayBookTodayCount,
            ],
            'credit' => [
                'credit_sales_today' => $creditSalesToday,
                'credit_sales_today_count' => $creditSalesTodayCount,
                'credit_sales_month' => $creditSalesMonth,
                'credit_sales_month_count' => $creditSalesMonthCount,
                'outstanding_balance' => $outstandingBalance,
            ],
            'products' => [
                'total' => $totalProducts,
                'low_stock' => $lowStockProducts,
            ],
            'inventory' => [
                'total_value' => (float) $inventoryValue,
            ],
        ]);
    }
}
