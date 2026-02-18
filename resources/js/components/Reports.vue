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
                </select>
            </div>
        </div>

        <!-- Date Filters -->
        <div class="filters" v-if="reportType !== 'dashboard'">
            <input v-model="filters.date_from" type="date" class="date-input" placeholder="From Date" />
            <input v-model="filters.date_to" type="date" class="date-input" placeholder="To Date" />
            <button @click="loadReport" class="btn btn-primary">Apply Filters</button>
            <button @click="exportReport" class="btn btn-secondary">Export</button>
        </div>

        <!-- Dashboard Stats Widgets -->
        <div v-if="reportType === 'dashboard' && dashboardStats" class="stats-widgets">
            <div class="stat-widget">
                <div class="widget-icon">💰</div>
                <div class="widget-content">
                    <h3>Today's Sales</h3>
                    <p class="widget-value">₹{{ dashboardStats.sales.today_revenue.toFixed(2) }}</p>
                    <p class="widget-label">{{ dashboardStats.sales.today_count }} transactions</p>
                </div>
            </div>

            <div class="stat-widget">
                <div class="widget-icon">📊</div>
                <div class="widget-content">
                    <h3>This Month</h3>
                    <p class="widget-value">₹{{ dashboardStats.sales.month_revenue.toFixed(2) }}</p>
                    <p class="widget-label">{{ dashboardStats.sales.month_count }} transactions</p>
                </div>
            </div>

            <div class="stat-widget">
                <div class="widget-icon">📦</div>
                <div class="widget-content">
                    <h3>Products</h3>
                    <p class="widget-value">{{ dashboardStats.products.total }}</p>
                    <p class="widget-label">{{ dashboardStats.products.low_stock }} low stock</p>
                </div>
            </div>

            <div class="stat-widget">
                <div class="widget-icon">💵</div>
                <div class="widget-content">
                    <h3>Inventory Value</h3>
                    <p class="widget-value">₹{{ dashboardStats.inventory.total_value.toFixed(2) }}</p>
                    <p class="widget-label">Total stock value</p>
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

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Cost Price</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in (productReport?.products || []).filter(p => p != null && p.id != null)" :key="product.id">
                            <td>{{ product.name }}</td>
                            <td>{{ product.sku }}</td>
                            <td>{{ product.category?.name || 'N/A' }}</td>
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

            <div class="table-container">
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
                        <tr v-for="movement in (inventoryReport?.movements || []).filter(m => m != null && m.id != null)" :key="movement.id">
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
            </div>

            <div class="table-container">
                <h3>Sales Transactions</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Subtotal</th>
                            <th>Tax</th>
                            <th>Discount</th>
                            <th>Total</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sale in (salesReport?.sales || []).filter(s => s != null && s.id != null)" :key="sale.id">
                            <td>{{ sale.invoice_number }}</td>
                            <td>{{ formatDate(sale.sale_date) }}</td>
                            <td>{{ sale.customer?.name || 'Walk-in' }}</td>
                            <td>{{ sale.items?.length || 0 }}</td>
                            <td>₹{{ sale.subtotal }}</td>
                            <td>₹{{ sale.tax_amount }}</td>
                            <td>₹{{ sale.discount }}</td>
                            <td>₹{{ sale.total }}</td>
                            <td>{{ sale.payment_method }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="salesReport.top_products && salesReport.top_products.length > 0" class="table-container">
                <h3>Top Selling Products</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(product, index) in (salesReport?.top_products || []).filter(p => p != null && (p.product_id != null || p.id != null))" :key="product.product_id || product.id || index">
                            <td>{{ index + 1 }}. {{ product.product_name }}</td>
                            <td>{{ product.sku }}</td>
                            <td>{{ product.quantity }}</td>
                            <td>₹{{ product.revenue.toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'Reports',
    setup() {
        const reportType = ref('dashboard');
        const dashboardStats = ref(null);
        const productReport = ref(null);
        const inventoryReport = ref(null);
        const salesReport = ref(null);
        
        const filters = ref({
            date_from: '',
            date_to: ''
        });

        const loadReport = async () => {
            try {
                if (reportType.value === 'dashboard') {
                    const response = await axios.get('/api/reports/dashboard-stats');
                    dashboardStats.value = response.data;
                } else if (reportType.value === 'products') {
                    const params = {};
                    if (filters.value.date_from) params.date_from = filters.value.date_from;
                    if (filters.value.date_to) params.date_to = filters.value.date_to;
                    const response = await axios.get('/api/reports/products', { params });
                    productReport.value = response.data;
                } else if (reportType.value === 'inventory') {
                    const params = {};
                    if (filters.value.date_from) params.date_from = filters.value.date_from;
                    if (filters.value.date_to) params.date_to = filters.value.date_to;
                    const response = await axios.get('/api/reports/inventory', { params });
                    inventoryReport.value = response.data;
                } else if (reportType.value === 'sales') {
                    const params = {};
                    if (filters.value.date_from) params.date_from = filters.value.date_from;
                    if (filters.value.date_to) params.date_to = filters.value.date_to;
                    const response = await axios.get('/api/reports/sales', { params });
                    salesReport.value = response.data;
                }
            } catch (error) {
                console.error('Error loading report:', error);
                alert('Error loading report');
            }
        };

        const exportReport = () => {
            // Simple CSV export
            let csv = '';
            let data = [];

            if (reportType.value === 'products' && productReport.value) {
                csv = 'Product,SKU,Category,Stock,Cost Price,Value,Status\n';
                productReport.value.products.forEach(p => {
                    csv += `"${p.name}","${p.sku}","${p.category?.name || 'N/A'}","${p.stock_quantity}","${p.cost_price}","${(p.stock_quantity * p.cost_price).toFixed(2)}","${getProductStatus(p)}"\n`;
                });
            } else if (reportType.value === 'inventory' && inventoryReport.value) {
                csv = 'Date,Product,Type,Quantity,Unit Cost,User,Notes\n';
                inventoryReport.value.movements.forEach(m => {
                    csv += `"${formatDate(m.created_at)}","${m.product?.name || 'N/A'}","${m.type.toUpperCase()}","${m.quantity}","${m.unit_cost || 'N/A'}","${m.user?.name || 'System'}","${(m.notes || '').replace(/"/g, '""')}"\n`;
                });
            } else if (reportType.value === 'sales' && salesReport.value) {
                csv = 'Invoice #,Date,Customer,Items,Subtotal,Tax,Discount,Total,Payment\n';
                salesReport.value.sales.forEach(s => {
                    csv += `"${s.invoice_number}","${formatDate(s.sale_date)}","${s.customer?.name || 'Walk-in'}","${s.items?.length || 0}","${s.subtotal}","${s.tax_amount}","${s.discount}","${s.total}","${s.payment_method}"\n`;
                });
            }

            if (csv) {
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${reportType.value}_report_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                // Clean up
                setTimeout(() => window.URL.revokeObjectURL(url), 100);
            } else {
                alert('No data available to export');
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
            return new Date(date).toLocaleDateString();
        };

        const formatReportQty = (qty, unit) => {
            if (qty === null || qty === undefined) return '0';
            const n = parseFloat(qty);
            const u = (unit || 'pcs').toLowerCase();
            const isWeight = ['kg', 'g', 'gm', 'ltr'].includes(u);
            if (isWeight) return Number(n) === parseInt(n, 10) ? n + ' ' + u : parseFloat(n).toFixed(3) + ' ' + u;
            return parseInt(n, 10) + ' ' + u;
        };

        onMounted(() => {
            loadReport();
        });

        return {
            reportType,
            dashboardStats,
            productReport,
            inventoryReport,
            salesReport,
            filters,
            loadReport,
            exportReport,
            getProductStatus,
            getProductStatusClass,
            getMovementTypeClass,
            formatDate,
            formatReportQty
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

.date-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
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
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.stat-widget:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.widget-icon {
    font-size: 48px;
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
}

.widget-label {
    margin: 0;
    font-size: 12px;
    color: #999;
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
    font-size: 24px;
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
