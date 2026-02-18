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

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
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
                    <tr v-for="sale in filteredSales" :key="sale.id">
                        <td>{{ sale.invoice_number }}</td>
                        <td>{{ formatDate(sale.sale_date) }}</td>
                        <td>{{ sale.customer?.name || 'Walk-in' }}</td>
                        <td>{{ sale.items?.length || 0 }}</td>
                        <td>₹{{ sale.total }}</td>
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
            <div
                ref="loadMoreTrigger"
                v-if="hasMore"
                class="load-more-trigger"
            >
                <div v-if="loading" class="loading-indicator">
                    Loading more sales...
                </div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredSales.length > 0" class="no-more-indicator">
                No more sales to load
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';

export default {
    name: 'Sales',
    setup() {
        const router = useRouter();
        const dateFrom = ref('');
        const dateTo = ref('');

        const {
            items: sales,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            applyFilters
        } = usePaginatedDropdown('/api/sales', {
            searchParam: 'search',
            initialFilters: {},
            perPage: 10
        });

        watch([dateFrom, dateTo], () => {
            applyFilters({
                date_from: dateFrom.value || null,
                date_to: dateTo.value || null
            });
        });

        const filteredSales = computed(() => (sales.value || []).filter(s => s != null && s.id != null));

        const loadSales = () => loadInitial();

        const scrollObserver = ref(null);
        const loadMoreTrigger = ref(null);
        const tableContainer = ref(null);

        const setupScrollObserver = () => {
            if (typeof IntersectionObserver === 'undefined' || !tableContainer.value || !loadMoreTrigger.value) return;
            scrollObserver.value = new IntersectionObserver(
                (entries) => {
                    if (entries[0].isIntersecting && hasMore.value && !loading.value) loadMore();
                },
                { root: tableContainer.value, rootMargin: '50px', threshold: 0.1 }
            );
            scrollObserver.value.observe(loadMoreTrigger.value);
        };

        const handleScroll = (e) => {
            const el = e.target;
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 100 && hasMore.value && !loading.value) loadMore();
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
            loadInitial();
            setTimeout(() => setupScrollObserver(), 100);
        });

        watch([loadMoreTrigger, tableContainer], () => {
            if (loadMoreTrigger.value && tableContainer.value) setupScrollObserver();
        });

        return {
            dateFrom,
            dateTo,
            filteredSales,
            loading,
            hasMore,
            handleScroll,
            tableContainer,
            loadMoreTrigger,
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
    overflow-y: auto;
    max-height: calc(100vh - 250px);
}

.load-more-trigger {
    min-height: 50px;
    padding: 15px;
    text-align: center;
}

.loading-indicator,
.no-more-indicator,
.load-more-hint {
    padding: 15px;
    text-align: center;
    color: #666;
    font-size: 14px;
}

.loading-indicator {
    color: #667eea;
}

.load-more-hint {
    color: #999;
    font-size: 12px;
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
