<template>
    <div class="sales-container">
        <div class="page-header">
            <h1>Sales History</h1>
            <div class="header-actions">
                <input v-model="dateFrom" type="date" class="date-input" />
                <input v-model="dateTo" type="date" class="date-input" />
                <button @click="loadSales" class="btn btn-secondary">Filter</button>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="sale in sales" :key="sale.id">
                        <td>{{ sale.invoice_number }}</td>
                        <td>{{ formatDate(sale.sale_date) }}</td>
                        <td>{{ sale.customer?.name || 'Walk-in' }}</td>
                        <td>{{ sale.items?.length || 0 }}</td>
                        <td>${{ sale.total }}</td>
                        <td>{{ sale.payment_method }}</td>
                        <td>
                            <span :class="getStatusClass(sale.status)">{{ sale.status }}</span>
                        </td>
                        <td>
                            <button @click="viewInvoice(sale.id)" class="btn-sm btn-primary">View Invoice</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

export default {
    name: 'Sales',
    setup() {
        const router = useRouter();
        const sales = ref([]);
        const dateFrom = ref('');
        const dateTo = ref('');

        const loadSales = async () => {
            try {
                const params = {};
                if (dateFrom.value) params.date_from = dateFrom.value;
                if (dateTo.value) params.date_to = dateTo.value;
                const response = await axios.get('/api/sales', { params });
                sales.value = response.data.data || response.data;
            } catch (error) {
                console.error('Error loading sales:', error);
            }
        };

        const viewInvoice = (id) => {
            router.push(`/sales/${id}/invoice`);
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleDateString();
        };

        const getStatusClass = (status) => {
            const classes = {
                completed: 'badge-success',
                pending: 'badge-warning',
                cancelled: 'badge-danger',
                refunded: 'badge-info'
            };
            return classes[status] || 'badge-secondary';
        };

        onMounted(() => {
            loadSales();
        });

        return {
            sales,
            dateFrom,
            dateTo,
            loadSales,
            viewInvoice,
            formatDate,
            getStatusClass
        };
    }
};
</script>

<style scoped>
.sales-container {
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

.date-input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
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
}

.data-table td {
    padding: 12px;
    border-top: 1px solid #e0e0e0;
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

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn {
    padding: 8px 16px;
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
