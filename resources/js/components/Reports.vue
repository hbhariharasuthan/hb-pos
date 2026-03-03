<template>
    <div class="reports-container">
        <div class="page-header">
            <h1>Reports & Analytics</h1>
            <div class="header-actions">
                <select v-model="reportType" class="select-input" @change="loadReport">
                    <option value="dashboard">Dashboard Stats</option>
                    <option value="products">Product Report</option>
                    <option value="inventory">Inventory Report</option>
                    <option value="sales">Sales Report</option>
                    <option value="purchases">Purchase Report</option>
                    <option value="expenses">Expense Report</option>
                    <option value="day-book">Day Book Report</option>
                </select>
            </div>
        </div>

        <!-- Date Filters -->
        <div class="filters" v-if="reportType !== 'dashboard'">
            <input v-model="filters.date_from" type="date" class="date-input" placeholder="From Date" />
            <input v-model="filters.date_to" type="date" class="date-input" placeholder="To Date" />
            <input
                v-if="reportType !== 'day-book'"
                v-model="reportSearch"
                type="text"
                class="search-input"
                placeholder="Search in current report..."
            />
            <select
                v-if="reportType === 'day-book'"
                v-model="dayBookType"
                class="date-input"
            >
                <option value="">All Types</option>
                <option value="sale">Sales</option>
                <option value="purchase">Purchases</option>
                <option value="return">Returns</option>
                <option value="expense">Expenses</option>
            </select>
            <button @click="loadReport" class="btn btn-primary">Apply Filters</button>
            <button @click="clearFilters" class="btn btn-secondary">Clear Filters</button>
            <button
                @click="handleExport"
                class="btn btn-secondary"
                :disabled="exportInProgress"
            >
                {{ exportInProgress ? 'Exporting…' : 'Export' }}
            </button>
            <span class="filter-hint">Maximum date range for reports and exports is 6 months.</span>
        </div>

        <!-- Dashboard Stats Widgets -->
        <div v-if="reportType === 'dashboard' && dashboardStats" class="stats-widgets">
            <div class="stat-widget">
                <div class="widget-icon">💰</div>
                <div class="widget-content">
                    <h3>Today's Sales</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.sales?.today_revenue ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.sales?.today_count ?? 0 }} transactions</p>
                </div>
            </div>

            <div class="stat-widget">
                <div class="widget-icon">📊</div>
                <div class="widget-content">
                    <h3>Sales This Month</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.sales?.month_revenue ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.sales?.month_count ?? 0 }} transactions</p>
                </div>
            </div>

            <div class="stat-widget">
                <div class="widget-icon">📦</div>
                <div class="widget-content">
                    <h3>Products</h3>
                    <p class="widget-value">{{ dashboardStats.products?.total ?? 0 }}</p>
                    <p class="widget-label">{{ dashboardStats.products?.low_stock ?? 0 }} low stock</p>
                </div>
            </div>

            <div class="stat-widget">
                <div class="widget-icon">💵</div>
                <div class="widget-content">
                    <h3>Inventory Value</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.inventory?.total_value ?? 0) }}</p>
                    <p class="widget-label">Total stock value</p>
                </div>
            </div>

            <!-- Purchase -->
            <div v-if="dashboardStats.purchases" class="stat-widget">
                <div class="widget-icon">🛒</div>
                <div class="widget-content">
                    <h3>Today's Purchases</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.purchases.today_amount ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.purchases.today_count ?? 0 }} bills</p>
                </div>
            </div>
            <div v-if="dashboardStats.purchases" class="stat-widget">
                <div class="widget-icon">📋</div>
                <div class="widget-content">
                    <h3>Purchases This Month</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.purchases.month_amount ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.purchases.month_count ?? 0 }} bills</p>
                </div>
            </div>

            <!-- Expense -->
            <div v-if="dashboardStats.expenses" class="stat-widget">
                <div class="widget-icon">🧾</div>
                <div class="widget-content">
                    <h3>Today's Expenses</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.expenses.today_amount ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.expenses.today_count ?? 0 }} entries</p>
                </div>
            </div>
            <div v-if="dashboardStats.expenses" class="stat-widget">
                <div class="widget-icon">📉</div>
                <div class="widget-content">
                    <h3>Expenses This Month</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.expenses.month_amount ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.expenses.month_count ?? 0 }} entries</p>
                </div>
            </div>

            <!-- Day Book (today summary) -->
            <div v-if="dashboardStats.day_book?.today" class="stat-widget day-book-widget">
                <div class="widget-icon">📒</div>
                <div class="widget-content">
                    <h3>Day Book – Today</h3>
                    <table class="day-book-table">
                        <tbody>
                            <tr>
                                <td class="day-book-label">Sales</td>
                                <td class="day-book-amount">₹{{ formatCurrency(dashboardStats.day_book.today?.sale ?? 0) }}</td>
                                <td class="day-book-count">{{ dashboardStats.day_book.today_count?.sale ?? 0 }} {{ (dashboardStats.day_book.today_count?.sale ?? 0) === 1 ? 'entry' : 'entries' }}</td>
                            </tr>
                            <tr>
                                <td class="day-book-label">Purchases</td>
                                <td class="day-book-amount">₹{{ formatCurrency(dashboardStats.day_book.today?.purchase ?? 0) }}</td>
                                <td class="day-book-count">{{ dashboardStats.day_book.today_count?.purchase ?? 0 }} {{ (dashboardStats.day_book.today_count?.purchase ?? 0) === 1 ? 'bill' : 'bills' }}</td>
                            </tr>
                            <tr>
                                <td class="day-book-label">Returns</td>
                                <td class="day-book-amount">₹{{ formatCurrency(dashboardStats.day_book.today?.return ?? 0) }}</td>
                                <td class="day-book-count">{{ dashboardStats.day_book.today_count?.return ?? 0 }} {{ (dashboardStats.day_book.today_count?.return ?? 0) === 1 ? 'entry' : 'entries' }}</td>
                            </tr>
                            <tr>
                                <td class="day-book-label">Expenses</td>
                                <td class="day-book-amount">₹{{ formatCurrency(dashboardStats.day_book.today?.expense ?? 0) }}</td>
                                <td class="day-book-count">{{ dashboardStats.day_book.today_count?.expense ?? 0 }} {{ (dashboardStats.day_book.today_count?.expense ?? 0) === 1 ? 'entry' : 'entries' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Credit -->
            <div v-if="dashboardStats.credit" class="stat-widget">
                <div class="widget-icon">💳</div>
                <div class="widget-content">
                    <h3>Credit Sales Today</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.credit.credit_sales_today ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.credit.credit_sales_today_count ?? 0 }} transactions</p>
                </div>
            </div>
            <div v-if="dashboardStats.credit" class="stat-widget">
                <div class="widget-icon">📅</div>
                <div class="widget-content">
                    <h3>Credit Sales This Month</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.credit.credit_sales_month ?? 0) }}</p>
                    <p class="widget-label">{{ dashboardStats.credit.credit_sales_month_count ?? 0 }} transactions</p>
                </div>
            </div>
            <div v-if="dashboardStats.credit && (dashboardStats.credit.outstanding_balance ?? 0) > 0" class="stat-widget">
                <div class="widget-icon">⚠️</div>
                <div class="widget-content">
                    <h3>Outstanding (Credit)</h3>
                    <p class="widget-value">₹{{ formatCurrency(dashboardStats.credit.outstanding_balance ?? 0) }}</p>
                    <p class="widget-label">Customer balance due</p>
                </div>
            </div>
        </div>

        <!-- Product Report -->
        <div v-if="reportType === 'products' && productReport" class="report-section">
            <div class="report-stats">
                <div class="stat-card">
                    <h3>Total Products</h3>
                    <p>{{ productReport.stats.total_products }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total Value</h3>
                    <p>₹{{ productReport.stats.total_value.toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Low Stock</h3>
                    <p>{{ productReport.stats.low_stock_count }}</p>
                </div>
                <div class="stat-card">
                    <h3>Out of Stock</h3>
                    <p>{{ productReport.stats.out_of_stock_count }}</p>
                </div>
            </div>

            <div ref="reportScrollContainer" class="table-container report-scroll" @scroll="handleReportScroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Stock</th>
                            <th>Cost Price</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(product, idx) in filteredProductRows" :key="product?.id ?? `product-${idx}`">
                            <td>{{ product.name }}</td>
                            <td>{{ product.sku }}</td>
                            <td>{{ product.category?.name || 'N/A' }}</td>
                            <td>{{ product.brand?.name || 'N/A' }}</td>
                            <td>{{ formatReportQty(product.stock_quantity, product.unit) }}</td>
                            <td>₹{{ product.cost_price }}</td>
                            <td>₹{{ (product.stock_quantity * product.cost_price).toFixed(2) }}</td>
                            <td>
                                <span :class="getProductStatusClass(product)">
                                    {{ getProductStatus(product) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="reportHasMore && reportType === 'products'" class="load-more-trigger">
                    <div v-if="reportLoading" class="loading-indicator">Loading more...</div>
                    <div v-else class="load-more-hint">Scroll for more</div>
                </div>
                <div v-if="!reportHasMore && filteredProductRows.length > 0 && reportType === 'products'" class="no-more-indicator">No more records</div>
            </div>
        </div>

        <!-- Inventory Report -->
        <div v-if="reportType === 'inventory' && inventoryReport" class="report-section">
            <div class="report-stats">
                <div class="stat-card">
                    <h3>Total Movements</h3>
                    <p>{{ inventoryReport.stats.total_movements }}</p>
                </div>
                <div class="stat-card">
                    <h3>Purchases</h3>
                    <p>{{ inventoryReport.stats.purchases }}</p>
                </div>
                <div class="stat-card">
                    <h3>Sales</h3>
                    <p>{{ inventoryReport.stats.sales }}</p>
                </div>
                <div class="stat-card">
                    <h3>Returns</h3>
                    <p>{{ inventoryReport.stats.returns }}</p>
                </div>
            </div>

            <div ref="reportScrollContainer" class="table-container report-scroll" @scroll="handleReportScroll">
                <h3>Stock Movements</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(movement, idx) in filteredInventoryMovements" :key="movement?.id ?? `movement-${idx}`">
                            <td>{{ formatDate(movement.created_at) }}</td>
                            <td>{{ movement.product?.name }}</td>
                            <td>
                                <span :class="getMovementTypeClass(movement.type)">
                                    {{ movement.type.toUpperCase() }}
                                </span>
                            </td>
                            <td :class="movement.quantity > 0 ? 'positive' : 'negative'">
                                {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                            </td>
                            <td>₹{{ movement.unit_cost || 'N/A' }}</td>
                            <td>{{ movement.user?.name || 'System' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="reportHasMore && reportType === 'inventory'" class="load-more-trigger">
                    <div v-if="reportLoading" class="loading-indicator">Loading more...</div>
                    <div v-else class="load-more-hint">Scroll for more</div>
                </div>
                <div v-if="!reportHasMore && filteredInventoryMovements.length > 0 && reportType === 'inventory'" class="no-more-indicator">No more records</div>
            </div>
        </div>

        <!-- Sales Report -->
        <div v-if="reportType === 'sales' && salesReport" class="report-section">
            <div class="report-stats">
                <div class="stat-card">
                    <h3>Total Sales</h3>
                    <p>{{ salesReport.stats.total_sales }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <p>₹{{ salesReport.stats.total_revenue.toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Items Sold</h3>
                    <p>{{ salesReport.stats.total_items_sold }}</p>
                </div>
                <div class="stat-card">
                    <h3>Average Sale</h3>
                    <p>₹{{ salesReport.stats.average_sale.toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Cash Sales</h3>
                    <p>₹{{ salesReport.stats.cash_sales.toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Card Sales</h3>
                    <p>₹{{ salesReport.stats.card_sales.toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total CGST</h3>
                    <p>₹{{ (salesReport.stats.total_cgst || 0).toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total SGST</h3>
                    <p>₹{{ (salesReport.stats.total_sgst || 0).toFixed(2) }}</p>
                </div>
            </div>

            <div ref="reportScrollContainer" class="table-container report-scroll" @scroll="handleReportScroll">
                <h3>Sales Transactions</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Subtotal</th>
                            <th>CGST</th>
                            <th>SGST</th>
                            <th>Discount</th>
                            <th>Total</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(sale, idx) in filteredSalesRows" :key="sale?.id ?? `sale-${idx}`">
                            <td>{{ sale.invoice_number }}</td>
                            <td>{{ formatDate(sale.sale_date) }}</td>
                            <td>{{ sale.customer?.name || 'Walk-in' }}</td>
                            <td>{{ sale.items?.length || 0 }}</td>
                            <td>₹{{ sale.subtotal }}</td>
                            <td>₹{{ reportCgst(sale).toFixed(2) }}</td>
                            <td>₹{{ reportSgst(sale).toFixed(2) }}</td>
                            <td>₹{{ sale.discount }}</td>
                            <td>₹{{ sale.total }}</td>
                            <td>{{ sale.payment_method }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="reportHasMore && reportType === 'sales'" class="load-more-trigger">
                    <div v-if="reportLoading" class="loading-indicator">Loading more...</div>
                    <div v-else class="load-more-hint">Scroll for more</div>
                </div>
                <div v-if="!reportHasMore && filteredSalesRows.length > 0 && reportType === 'sales'" class="no-more-indicator">No more records</div>
            </div>

            <div v-if="salesReport.top_products && salesReport.top_products.length > 0" class="table-container">
                <h3>Top Selling Products</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Brand</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(product, index) in (salesReport?.top_products || []).filter(p => p != null && (p?.product_id != null || p?.id != null))" :key="product?.product_id ?? product?.id ?? `top-${index}`">
                            <td>{{ index + 1 }}. {{ product.product_name }}</td>
                            <td>{{ product.sku }}</td>
                            <td>{{ product.brand_name || 'N/A' }}</td>
                            <td>{{ product.quantity }}</td>
                            <td>₹{{ product.revenue.toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Purchase Report -->
        <div v-if="reportType === 'purchases' && purchaseReport" class="report-section">
            <div class="report-stats">
                <div class="stat-card">
                    <h3>Total Purchases</h3>
                    <p>{{ purchaseReport.stats?.total_purchases || 0 }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total Amount</h3>
                    <p>₹{{ (purchaseReport.stats?.total_amount || 0).toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total Items</h3>
                    <p>{{ purchaseReport.stats?.total_items || 0 }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total Tax</h3>
                    <p>₹{{ (purchaseReport.stats?.total_tax || 0).toFixed(2) }}</p>
                </div>
            </div>

            <div ref="reportScrollContainer" class="table-container report-scroll" @scroll="handleReportScroll">
                <h3>Purchase Bills</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Items</th>
                            <th>Subtotal</th>
                            <th>Tax</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(purchase, idx) in filteredPurchaseRows" :key="purchase?.id ?? `purchase-${idx}`">
                            <td>{{ purchase.bill_number }}</td>
                            <td>{{ formatDate(purchase.purchase_date) }}</td>
                            <td>{{ purchase.supplier?.name || '—' }}</td>
                            <td>{{ purchase.items?.length || 0 }}</td>
                            <td>₹{{ purchase.subtotal }}</td>
                            <td>₹{{ purchase.tax_amount }}</td>
                            <td>₹{{ purchase.total }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="reportHasMore && reportType === 'purchases'" class="load-more-trigger">
                    <div v-if="reportLoading" class="loading-indicator">Loading more...</div>
                    <div v-else class="load-more-hint">Scroll for more</div>
                </div>
                <div v-if="!reportHasMore && filteredPurchaseRows.length > 0 && reportType === 'purchases'" class="no-more-indicator">No more records</div>
            </div>
        </div>

        <!-- Expense Report -->
        <div v-if="reportType === 'expenses' && expenseReport" class="report-section">
            <div class="report-stats">
                <div class="stat-card">
                    <h3>Total Expenses</h3>
                    <p>{{ expenseReport.stats?.total_expenses || 0 }}</p>
                </div>
                <div class="stat-card">
                    <h3>Total Amount</h3>
                    <p>₹{{ (expenseReport.stats?.total_amount || 0).toFixed(2) }}</p>
                </div>
                <div class="stat-card">
                    <h3>Approved</h3>
                    <p>{{ expenseReport.stats?.approved_count || 0 }}</p>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <p>{{ expenseReport.stats?.pending_count || 0 }}</p>
                </div>
                <div class="stat-card">
                    <h3>Cancelled</h3>
                    <p>{{ expenseReport.stats?.cancelled_count || 0 }}</p>
                </div>
            </div>

            <div ref="reportScrollContainer" class="table-container report-scroll" @scroll="handleReportScroll">
                <h3>Expense Entries</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Voucher #</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(exp, idx) in filteredExpenseRows" :key="exp?.id ?? `expense-${idx}`">
                            <td>{{ formatDate(exp.expense_date) }}</td>
                            <td>{{ exp.voucher_number }}</td>
                            <td>{{ exp.expense_category?.name || '—' }}</td>
                            <td>₹{{ exp.amount }}</td>
                            <td>{{ exp.payment_method || '—' }}</td>
                            <td>{{ exp.status }}</td>
                            <td>{{ exp.reference || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="reportHasMore && reportType === 'expenses'" class="load-more-trigger">
                    <div v-if="reportLoading" class="loading-indicator">Loading more...</div>
                    <div v-else class="load-more-hint">Scroll for more</div>
                </div>
                <div v-if="!reportHasMore && filteredExpenseRows.length > 0 && reportType === 'expenses'" class="no-more-indicator">No more records</div>
            </div>
        </div>

        <!-- Day Book Report -->
        <div v-if="reportType === 'day-book' && dayBookReport" class="report-section">
            <div class="report-stats">
                <div class="stat-card" v-for="t in dayBookTypes" :key="t.value">
                    <h3>{{ t.label }}</h3>
                    <p>₹{{ (dayBookTotals[t.value]?.total_amount || 0).toFixed(2) }}</p>
                    <p class="widget-label">{{ dayBookTotals[t.value]?.total_entries || 0 }} entries</p>
                </div>
            </div>
            <div ref="reportScrollContainer" class="table-container report-scroll" @scroll="handleReportScroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in (dayBookReport.entries || [])" :key="idx">
                            <td>{{ formatDate(row.date) }}</td>
                            <td>{{ row.ref || '—' }}</td>
                            <td>{{ row.type || '—' }}</td>
                            <td>₹{{ Number(row.amount || 0).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="reportHasMore && reportType === 'day-book'" class="load-more-trigger">
                    <div v-if="reportLoading" class="loading-indicator">Loading more...</div>
                    <div v-else class="load-more-hint">Scroll for more</div>
                </div>
                <div v-if="!reportHasMore && (dayBookReport.entries || []).length > 0 && reportType === 'day-book'" class="no-more-indicator">No more records</div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { handleApiError } from '@/utils/errorHandler';

export default {
    name: 'Reports',
    setup() {
        const route = useRoute();
        const toast = useToast();
        const reportType = ref('dashboard');
        const dashboardStats = ref(null);
        const productReport = ref(null);
        const inventoryReport = ref(null);
        const salesReport = ref(null);
        const purchaseReport = ref(null);
        const expenseReport = ref(null);
        const dayBookReport = ref(null);
        const reportSearch = ref('');
        const reportLoading = ref(false);
        const exportInProgress = ref(false);
        const currentExportId = ref(null);
        const exportStatus = ref(null);
        const reportPage = ref(1);
        const reportLastPage = ref(1);
        const reportScrollContainer = ref(null);
        const dayBookType = ref('');
        let exportPollTimer = null;

        const filters = ref({
            date_from: '',
            date_to: ''
        });

        const reportHasMore = computed(() => reportPage.value < reportLastPage.value);

        const normalizeDateRange = () => {
            const from = filters.value.date_from;
            const to = filters.value.date_to;
            if (!from || !to) return;
            if (from > to) {
                // Swap to ensure from <= to
                const tmp = filters.value.date_from;
                filters.value.date_from = filters.value.date_to;
                filters.value.date_to = tmp;
            }
        };

        const isDateRangeTooWide = () => {
            const from = filters.value.date_from;
            const to = filters.value.date_to;
            if (!from || !to) return false;
            const fromDate = new Date(from);
            const toDate = new Date(to);
            if (Number.isNaN(fromDate.getTime()) || Number.isNaN(toDate.getTime())) return false;
            const diffMs = Math.abs(toDate.getTime() - fromDate.getTime());
            const diffDays = diffMs / (1000 * 60 * 60 * 24);
            // Approximate 6 months as 186 days
            return diffDays > 186;
        };

        const buildReportParams = (page = 1) => {
            const params = { page, per_page: 15 };
            if (filters.value.date_from) params.date_from = filters.value.date_from;
            if (filters.value.date_to) params.date_to = filters.value.date_to;
            if (reportType.value === 'day-book' && dayBookType.value) {
                params.type = dayBookType.value;
            }
            return params;
        };

        const clearFilters = () => {
            filters.value.date_from = '';
            filters.value.date_to = '';
            reportSearch.value = '';
            dayBookType.value = '';
            reportPage.value = 1;
            loadReport();
        };

        const loadReport = async () => {
            try {
                if (reportType.value === 'dashboard') {
                    const response = await axios.get('/api/reports/dashboard-stats');
                    dashboardStats.value = response.data;
                    return;
                }
                normalizeDateRange();
                if (isDateRangeTooWide()) {
                    handleApiError('Maximum date range for reports is 6 months. Please narrow the dates.');
                    return;
                }
                reportLoading.value = true;
                reportPage.value = 1;
                const params = buildReportParams(1);
                if (reportType.value === 'products') {
                    const response = await axios.get('/api/reports/products', { params });
                    const d = response.data;
                    productReport.value = { stats: d.stats, products: d.products || [] };
                    reportLastPage.value = d.last_page ?? 1;
                } else if (reportType.value === 'inventory') {
                    const response = await axios.get('/api/reports/inventory', { params });
                    const d = response.data;
                    inventoryReport.value = { stats: d.stats, movements: d.movements || [] };
                    reportLastPage.value = d.last_page ?? 1;
                } else if (reportType.value === 'sales') {
                    const response = await axios.get('/api/reports/sales', { params });
                    const d = response.data;
                    salesReport.value = { stats: d.stats, sales: d.sales || [], top_products: d.top_products || [], daily_sales: d.daily_sales || [] };
                    reportLastPage.value = d.last_page ?? 1;
                } else if (reportType.value === 'purchases') {
                    const response = await axios.get('/api/reports/purchases', { params });
                    const d = response.data;
                    purchaseReport.value = { stats: d.stats, purchases: d.purchases || [] };
                    reportLastPage.value = d.last_page ?? 1;
                } else if (reportType.value === 'expenses') {
                    const response = await axios.get('/api/reports/expenses', { params });
                    const d = response.data;
                    expenseReport.value = { stats: d.stats, expenses: d.expenses || [] };
                    reportLastPage.value = d.last_page ?? 1;
                } else if (reportType.value === 'day-book') {
                    const response = await axios.get('/api/reports/day-book', { params });
                    const d = response.data;
                    dayBookReport.value = { stats: d.stats || {}, entries: d.entries || [] };
                    reportLastPage.value = d.last_page ?? 1;
                }
            } catch (error) {
                console.error('Error loading report:', error);
                handleApiError('Error loading report');
            } finally {
                reportLoading.value = false;
            }
        };

        const loadMoreReport = async () => {
            if (reportLoading.value || !reportHasMore.value) return;
            const nextPage = reportPage.value + 1;
            reportLoading.value = true;
            try {
                const params = buildReportParams(nextPage);
                if (reportType.value === 'products') {
                    const response = await axios.get('/api/reports/products', { params });
                    const d = response.data;
                    if (productReport.value && d.products?.length) {
                        productReport.value.products = [...(productReport.value.products || []), ...d.products];
                    }
                } else if (reportType.value === 'inventory') {
                    const response = await axios.get('/api/reports/inventory', { params });
                    const d = response.data;
                    if (inventoryReport.value && d.movements?.length) {
                        inventoryReport.value.movements = [...(inventoryReport.value.movements || []), ...d.movements];
                    }
                } else if (reportType.value === 'sales') {
                    const response = await axios.get('/api/reports/sales', { params });
                    const d = response.data;
                    if (salesReport.value && d.sales?.length) {
                        salesReport.value.sales = [...(salesReport.value.sales || []), ...d.sales];
                    }
                } else if (reportType.value === 'purchases') {
                    const response = await axios.get('/api/reports/purchases', { params });
                    const d = response.data;
                    if (purchaseReport.value && d.purchases?.length) {
                        purchaseReport.value.purchases = [...(purchaseReport.value.purchases || []), ...d.purchases];
                    }
                } else if (reportType.value === 'expenses') {
                    const response = await axios.get('/api/reports/expenses', { params });
                    const d = response.data;
                    if (expenseReport.value && d.expenses?.length) {
                        expenseReport.value.expenses = [...(expenseReport.value.expenses || []), ...d.expenses];
                    }
                } else if (reportType.value === 'day-book') {
                    const response = await axios.get('/api/reports/day-book', { params });
                    const d = response.data;
                    if (dayBookReport.value && d.entries?.length) {
                        dayBookReport.value.entries = [...(dayBookReport.value.entries || []), ...d.entries];
                    }
                }
                reportPage.value = nextPage;
            } catch (error) {
                console.error('Error loading more:', error);
            } finally {
                reportLoading.value = false;
            }
        };

        const handleReportScroll = (e) => {
            const el = e.target;
            if (!el || reportLoading.value || !reportHasMore.value) return;
            const scrollBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
            if (scrollBottom < 100) loadMoreReport();
        };

        const getReportTypeSlug = () => reportType.value;

        const startExportPolling = () => {
            if (!currentExportId.value) return;
            if (exportPollTimer) {
                clearInterval(exportPollTimer);
            }
            exportPollTimer = setInterval(async () => {
                try {
                    const { data } = await axios.get(`/api/report-exports/${currentExportId.value}`);
                    exportStatus.value = data.status;
                    if (data.status === 'completed') {
                        clearInterval(exportPollTimer);
                        exportPollTimer = null;
                        await downloadExportFile();
                        exportInProgress.value = false;
                    } else if (data.status === 'failed') {
                        clearInterval(exportPollTimer);
                        exportPollTimer = null;
                        exportInProgress.value = false;
                        handleApiError('Export failed. Please try again.');
                    }
                } catch (error) {
                    console.error('Error checking export status:', error);
                    clearInterval(exportPollTimer);
                    exportPollTimer = null;
                    exportInProgress.value = false;
                    handleApiError('Error checking export status');
                }
            }, 2000);
        };

        const downloadExportFile = async () => {
            if (!currentExportId.value) return;
            try {
                const response = await axios.get(`/api/report-exports/${currentExportId.value}/download`, {
                    responseType: 'blob'
                });
                const filename = `${getReportTypeSlug()}_report_${new Date().toISOString().split('T')[0]}.csv`;
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const a = document.createElement('a');
                a.href = url;
                a.setAttribute('download', filename);
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Error downloading export:', error);
                handleApiError('Error downloading export');
            }
        };

        const handleExport = async () => {
            normalizeDateRange();
            if (isDateRangeTooWide()) {
                handleApiError('Maximum date range for exports is 6 months. Please narrow the dates.');
                return;
            }
            if (exportInProgress.value) return;

            const payload = {
                report_type: getReportTypeSlug(),
                format: 'csv',
                filters: {}
            };
            if (filters.value.date_from) payload.filters.date_from = filters.value.date_from;
            if (filters.value.date_to) payload.filters.date_to = filters.value.date_to;
            if (reportType.value === 'day-book' && dayBookType.value) {
                payload.filters.type = dayBookType.value;
            }

            try {
                exportInProgress.value = true;
                exportStatus.value = 'pending';
                const { data } = await axios.post('/api/report-exports', payload);
                currentExportId.value = data.id;
                toast.success('Export started. You will get the file shortly.');
                startExportPolling();
            } catch (error) {
                console.error('Error starting export:', error);
                exportInProgress.value = false;
                handleApiError('Error starting export');
            }
        };

        const getProductStatus = (product) => {
            if (product.stock_quantity === 0) return 'Out of Stock';
            if (product.stock_quantity <= product.min_stock_level) return 'Low Stock';
            return 'In Stock';
        };

        const getProductStatusClass = (product) => {
            if (product.stock_quantity === 0) return 'badge-danger';
            if (product.stock_quantity <= product.min_stock_level) return 'badge-warning';
            return 'badge-success';
        };

        const getMovementTypeClass = (type) => {
            const classes = {
                purchase: 'badge-success',
                sale: 'badge-danger',
                return: 'badge-info',
                adjustment: 'badge-warning'
            };
            return classes[type] || 'badge-secondary';
        };

        const formatDate = (date) => {
            return date ? new Date(date).toLocaleDateString() : '';
        };

        /** Format amount with Indian grouping (e.g. 60,55,55,109.61) so large values stay readable and don't overflow. */
        const formatCurrency = (value) => {
            if (value === null || value === undefined || isNaN(value)) return '0.00';
            const n = parseFloat(value);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const exportDayBook = async (format) => {
            try {
                const params = { format };
                if (filters.value.date_from) params.date_from = filters.value.date_from;
                if (filters.value.date_to) params.date_to = filters.value.date_to;
                if (dayBookType.value) params.type = dayBookType.value;
                const response = await axios.get('/api/reports/day-book/export', {
                    params,
                    responseType: 'blob'
                });
                const ext = format === 'xlsx' ? 'xlsx' : 'csv';
                const filename = `day-book-${new Date().toISOString().slice(0, 10)}.${ext}`;
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const a = document.createElement('a');
                a.href = url;
                a.setAttribute('download', filename);
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                handleApiError(error);
            }
        };

        const reportCgst = (sale) => (parseFloat(sale?.tax_amount) || 0) / 2;
        const reportSgst = (sale) => (parseFloat(sale?.tax_amount) || 0) / 2;

        const formatReportQty = (qty, unit) => {
            if (qty === null || qty === undefined) return '0';
            const n = parseFloat(qty);
            const u = (unit || 'pcs').toLowerCase();
            const isWeight = ['kg', 'g', 'gm', 'ltr'].includes(u);
            if (isWeight) return Number(n) === parseInt(n, 10) ? n + ' ' + u : parseFloat(n).toFixed(3) + ' ' + u;
            return parseInt(n, 10) + ' ' + u;
        };

        const normalizedIncludes = (source, term) => {
            if (!term) return true;
            if (source === null || source === undefined) return false;
            return String(source).toLowerCase().includes(term);
        };

        const filteredProductRows = computed(() => {
            const rows = (productReport.value?.products || []).filter(p => p != null && p.id != null);
            const term = (reportSearch.value || '').trim().toLowerCase();
            if (!term) return rows;
            return rows.filter((p) => {
                return (
                    normalizedIncludes(p.name, term) ||
                    normalizedIncludes(p.sku, term) ||
                    normalizedIncludes(p.category?.name, term) ||
                    normalizedIncludes(p.brand?.name, term) ||
                    normalizedIncludes(p.stock_quantity, term) ||
                    normalizedIncludes(p.cost_price, term)
                );
            });
        });

        const filteredInventoryMovements = computed(() => {
            const rows = (inventoryReport.value?.movements || []).filter(m => m != null && m.id != null);
            const term = (reportSearch.value || '').trim().toLowerCase();
            if (!term) return rows;
            return rows.filter((m) => {
                return (
                    normalizedIncludes(m.product?.name, term) ||
                    normalizedIncludes(m.type, term) ||
                    normalizedIncludes(m.user?.name, term) ||
                    normalizedIncludes(m.quantity, term) ||
                    normalizedIncludes(m.unit_cost, term)
                );
            });
        });

        const filteredSalesRows = computed(() => {
            const rows = (salesReport.value?.sales || []).filter(s => s != null && s.id != null);
            const term = (reportSearch.value || '').trim().toLowerCase();
            if (!term) return rows;
            return rows.filter((s) => {
                return (
                    normalizedIncludes(s.invoice_number, term) ||
                    normalizedIncludes(s.customer?.name, term) ||
                    normalizedIncludes(s.payment_method, term) ||
                    normalizedIncludes(s.total, term) ||
                    normalizedIncludes(s.subtotal, term)
                );
            });
        });

        const filteredPurchaseRows = computed(() => {
            const rows = (purchaseReport.value?.purchases || []).filter(p => p != null && p.id != null);
            const term = (reportSearch.value || '').trim().toLowerCase();
            if (!term) return rows;
            return rows.filter((p) => {
                return (
                    normalizedIncludes(p.bill_number, term) ||
                    normalizedIncludes(p.supplier?.name, term) ||
                    normalizedIncludes(p.total, term) ||
                    normalizedIncludes(p.subtotal, term)
                );
            });
        });

        const dayBookTypes = [
            { value: 'sale', label: 'Sales' },
            { value: 'purchase', label: 'Purchases' },
            { value: 'return', label: 'Returns' },
            { value: 'expense', label: 'Expenses' }
        ];

        const dayBookTotals = computed(() => {
            const rows = dayBookReport.value?.stats?.totals_by_type || [];
            const map = {};
            rows.forEach((r) => {
                const key = r.type;
                if (!map[key]) {
                    map[key] = { total_amount: 0, total_entries: 0 };
                }
                map[key].total_amount = Number(r.total_amount || 0);
                map[key].total_entries = Number(r.total_entries || 0);
            });
            return map;
        });

        const filteredExpenseRows = computed(() => {
            const rows = (expenseReport.value?.expenses || []).filter(e => e != null && e.id != null);
            const term = (reportSearch.value || '').trim().toLowerCase();
            if (!term) return rows;
            return rows.filter((e) => {
                return (
                    normalizedIncludes(e.voucher_number, term) ||
                    normalizedIncludes(e.expense_category?.name, term) ||
                    normalizedIncludes(e.payment_method, term) ||
                    normalizedIncludes(e.status, term) ||
                    normalizedIncludes(e.amount, term)
                );
            });
        });

        onMounted(() => {
            if (route.query.report === 'day-book') {
                reportType.value = 'day-book';
            }
            loadReport();
        });

        onUnmounted(() => {
            if (exportPollTimer) {
                clearInterval(exportPollTimer);
            }
        });

        watch(() => route.query.report, (val) => {
            if (val === 'day-book') {
                reportType.value = 'day-book';
                loadReport();
            }
        });

        return {
            reportType,
            dayBookReport,
            exportDayBook,
            dashboardStats,
            productReport,
            inventoryReport,
            salesReport,
            purchaseReport,
            dayBookType,
            expenseReport,
            filters,
            reportSearch,
            reportLoading,
            reportHasMore,
            reportScrollContainer,
            loadReport,
            clearFilters,
            loadMoreReport,
            handleReportScroll,
            handleExport,
            reportCgst,
            reportSgst,
            getProductStatus,
            getProductStatusClass,
            getMovementTypeClass,
            formatDate,
            formatCurrency,
            formatReportQty,
            filteredProductRows,
            filteredInventoryMovements,
            filteredSalesRows,
            filteredPurchaseRows,
            filteredExpenseRows,
            dayBookTypes,
            dayBookTotals
        };
    }
};
</script>

<style scoped>
.reports-container {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.select-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}

.filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-hint {
    font-size: 12px;
    color: #6b7280;
    align-self: center;
}

.date-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.search-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    min-width: 220px;
    flex: 1;
}

.stats-widgets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-widget {
    background: white;
    border-radius: 12px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    min-width: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.stat-widget:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.widget-icon {
    font-size: 48px;
    flex-shrink: 0;
}

.widget-content {
    min-width: 0;
}

.widget-content h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

.widget-value {
    margin: 0 0 5px 0;
    font-size: 28px;
    font-weight: bold;
    color: #333;
    font-variant-numeric: tabular-nums;
    overflow-wrap: break-word;
    word-break: break-all;
}

.widget-label {
    margin: 0;
    font-size: 12px;
    color: #999;
}

.day-book-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-top: 8px;
}

.day-book-table td {
    padding: 6px 0;
    vertical-align: middle;
}

.day-book-table tr:not(:last-child) td {
    border-bottom: 1px solid #eee;
}

.day-book-label {
    color: #444;
    font-weight: 600;
    width: 100px;
}

.day-book-amount {
    color: #333;
    font-variant-numeric: tabular-nums;
    padding-right: 12px;
}

.day-book-count {
    color: #666;
    font-size: 12px;
    white-space: nowrap;
}

.report-section {
    margin-top: 20px;
}

.report-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

.stat-card p {
    margin: 0;
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
}

.table-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    overflow-x: auto;
}

.report-scroll {
    max-height: calc(100vh - 320px);
    overflow-y: auto;
}

.load-more-trigger {
    min-height: 50px;
    padding: 15px;
    text-align: center;
}

.report-scroll .loading-indicator,
.report-scroll .load-more-hint,
.report-scroll .no-more-indicator {
    padding: 12px;
    text-align: center;
    color: #666;
    font-size: 14px;
}

.report-scroll .loading-indicator {
    color: #667eea;
}

.report-scroll .load-more-hint {
    color: #999;
    font-size: 12px;
}

.table-container h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e0e0e0;
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
}

.positive {
    color: #28a745;
    font-weight: bold;
}

.negative {
    color: #dc3545;
    font-weight: bold;
}

.badge-success {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-warning {
    background: #ffc107;
    color: #333;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-danger {
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-info {
    background: #17a2b8;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-secondary {
    background: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}
</style>
