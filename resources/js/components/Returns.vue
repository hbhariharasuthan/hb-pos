<template>
    <div class="returns-container">
        <div class="page-header">
            <h1>Sales Returns</h1>
            <div class="header-actions">
                <button @click="openCreateModal" class="btn btn-primary">New Return</button>
            </div>
        </div>
        <div class="filters">
            <input v-model="dateFrom" type="date" class="date-input" />
            <input v-model="dateTo" type="date" class="date-input" />
            <select v-model="statusFilter" class="select-input">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <PaginatedDropdown
                v-model="customerId"
                endpoint="/api/customers"
                value-key="id"
                label-key="name"
                secondary-label-key="phone"
                placeholder="Filter by customer"
                :include-all-option="true"
                all-option-label="All customers"
            />
            <button @click="loadReturns" class="btn btn-secondary">Filter</button>
        </div>

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Return #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Sale Invoice</th>
                        <th>Items</th>
                        <th>Refund (₹)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, idx) in filteredReturns" :key="r?.id ?? `ret-${idx}`">
                        <td>{{ r.return_number }}</td>
                        <td>{{ formatDate(r.return_date) }}</td>
                        <td>{{ r.customer?.name || '—' }}</td>
                        <td>{{ r.sale?.invoice_number || '—' }}</td>
                        <td>{{ r.items?.length || 0 }}</td>
                        <td>₹{{ Number(r.refund_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                        <td><span :class="getStatusClass(r.status)">{{ r.status }}</span></td>
                        <td>
                            <button @click="viewReturn(r)" class="btn-sm btn-primary">View</button>
                            <button v-if="r.status !== 'cancelled'" @click="cancelReturn(r)" class="btn-sm btn-danger">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div ref="loadMoreTrigger" v-if="hasMore" class="load-more-trigger">
                <div v-if="loading" class="loading-indicator">Loading more...</div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredReturns.length > 0" class="no-more-indicator">No more returns</div>
        </div>

        <!-- Create Return Modal -->
        <div v-if="showCreateModal" class="modal-overlay" @click="showCreateModal = false">
            <div class="modal-content large" @click.stop>
                <h2>New Sales Return</h2>
                <form @submit.prevent="submitReturn">
                    <div class="form-group">
                        <label>Sale (Invoice) *</label>
                        <PaginatedDropdown
                            v-model="createForm.sale_id"
                            endpoint="/api/sales"
                            value-key="id"
                            label-key="invoice_number"
                            secondary-label-key="customer.name"
                            placeholder="Search invoice / customer"
                            @update:modelValue="onSaleSelect"
                        />
                        <p v-if="selectedSale" class="form-hint">Customer: {{ selectedSale.customer?.name || 'Walk-in' }}, Total: ₹{{ Number(selectedSale.total).toFixed(2) }}</p>
                    </div>
                    <div v-if="selectedSale && selectedSale.items?.length" class="form-group">
                        <label>Items to return *</label>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Sold</th>
                                    <th>Return Qty</th>
                                    <th>Unit Price (₹)</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, idx) in createForm.items" :key="idx">
                                    <td>{{ line.product_name }}</td>
                                    <td>{{ line.sold_quantity }}</td>
                                    <td>
                                        <input v-model.number="line.quantity" type="number" min="0" :max="line.sold_quantity" class="form-input num" />
                                    </td>
                                    <td>{{ line.unit_price }}</td>
                                    <td>
                                        <select v-model="line.reason" class="form-input">
                                            <option value="defective">Defective</option>
                                            <option value="wrong_item">Wrong item</option>
                                            <option value="customer_request">Customer request</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="form-hint">Refund total: ₹{{ createFormRefundTotal.toFixed(2) }}</p>
                    </div>
                    <div class="form-group">
                        <label>Return reason (overall)</label>
                        <select v-model="createForm.reason" class="form-input">
                            <option value="defective">Defective</option>
                            <option value="wrong_item">Wrong item</option>
                            <option value="customer_request">Customer request</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Refund method</label>
                        <select v-model="createForm.refund_method" class="form-input">
                            <option value="">— Select —</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="credit_note">Credit note</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea v-model="createForm.notes" rows="2" class="form-input"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" @click="showCreateModal = false" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving || !createFormRefundTotal">Create Return</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Detail / View Modal -->
        <div v-if="detailReturn" class="modal-overlay" @click="detailReturn = null">
            <div class="modal-content large" @click.stop>
                <h2>Return {{ detailReturn.return_number }}</h2>
                <p><strong>Date:</strong> {{ formatDate(detailReturn.return_date) }} | <strong>Customer:</strong> {{ detailReturn.customer?.name || '—' }} | <strong>Sale:</strong> {{ detailReturn.sale?.invoice_number || '—' }}</p>
                <p><strong>Refund:</strong> ₹{{ Number(detailReturn.refund_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }} | <strong>Status:</strong> <span :class="getStatusClass(detailReturn.status)">{{ detailReturn.status }}</span></p>
                <table class="data-table">
                    <thead>
                        <tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Refund</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in (detailReturn.items || [])" :key="i">
                            <td>{{ item.product?.name }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>₹{{ item.unit_price }}</td>
                            <td>₹{{ item.refund_amount }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="form-actions">
                    <button v-if="detailReturn.status !== 'cancelled'" type="button" @click="cancelReturn(detailReturn); detailReturn = null" class="btn btn-danger">Cancel this return</button>
                    <button type="button" @click="detailReturn = null" class="btn btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';
import PaginatedDropdown from '../components/PaginatedDropdown.vue';
import { handleApiError } from '@/utils/errorHandler';

export default {
    name: 'Returns',
    components: {
        PaginatedDropdown
    },
    setup() {
        const route = useRoute();
        const toast = useToast();
        const dateFrom = ref('');
        const dateTo = ref('');
        const statusFilter = ref('');
        const customerId = ref(null);
        const showCreateModal = ref(false);
        const detailReturn = ref(null);
        const saving = ref(false);
        const salesForReturn = ref([]);
        const selectedSale = ref(null);
        const createForm = ref({
            sale_id: '',
            reason: 'customer_request',
            notes: '',
            refund_method: '',
            refund_reference: '',
            status: 'approved',
            items: []
        });

        const {
            items: returns,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            applyFilters,
        } = usePaginatedDropdown('/api/returns', {
            initialFilters: {},
            perPage: 15
        });

        const filteredReturns = computed(() => (returns.value || []).filter(r => r != null && r.id != null));

        const loadReturns = () => loadInitial();

        watch([dateFrom, dateTo, statusFilter, customerId], () => {
            applyFilters({
                date_from: dateFrom.value || null,
                date_to: dateTo.value || null,
                status: statusFilter.value || null,
                customer_id: customerId.value || null
            });
        });

        const createFormRefundTotal = computed(() => {
            const items = createForm.value.items || [];
            return items.reduce((sum, line) => sum + (Number(line.quantity || 0) * Number(line.unit_price || 0)), 0);
        });

        const onSaleSelect = async () => {
            const id = createForm.value.sale_id;
            selectedSale.value = null;
            createForm.value.items = [];
            if (!id) return;
            try {
                const res = await axios.get(`/api/sales/${id}`);
                const sale = res.data;
                selectedSale.value = sale;
                createForm.value.items = (sale.items || []).map(si => ({
                    product_id: si.product_id,
                    sale_item_id: si.id,
                    product_name: si.product?.name || 'Product',
                    sold_quantity: Number(si.quantity),
                    quantity: Number(si.quantity),
                    unit_price: Number(si.unit_price),
                    reason: 'customer_request'
                }));
            } catch (e) {
                handleApiError(e);
            }
        };

        const openCreateModal = () => {
            createForm.value = { sale_id: '', reason: 'customer_request', notes: '', refund_method: '', refund_reference: '', status: 'approved', items: [] };
            selectedSale.value = null;
            showCreateModal.value = true;
        };

        const submitReturn = async () => {
            const items = (createForm.value.items || []).filter(l => Number(l.quantity) > 0);
            if (!items.length) {
                toast.error('Select at least one item with quantity > 0');
                return;
            }
            const payload = {
                sale_id: Number(createForm.value.sale_id),
                reason: createForm.value.reason,
                notes: createForm.value.notes || null,
                refund_method: createForm.value.refund_method || null,
                refund_reference: createForm.value.refund_reference || null,
                status: createForm.value.status,
                items: items.map(l => ({
                    product_id: l.product_id,
                    sale_item_id: l.sale_item_id,
                    quantity: Number(l.quantity),
                    unit_price: Number(l.unit_price),
                    reason: l.reason
                }))
            };
            saving.value = true;
            try {
                await axios.post('/api/returns', payload);
                toast.success('Return created successfully');
                showCreateModal.value = false;
                loadInitial();
            } catch (error) {
                handleApiError(error);
            } finally {
                saving.value = false;
            }
        };

        const viewReturn = (r) => {
            detailReturn.value = r;
        };

        const cancelReturn = async (r) => {
            if (!confirm('Cancel this return? Stock and accounting will be reversed.')) return;
            try {
                await axios.delete(`/api/returns/${r.id}`);
                toast.success('Return cancelled');
                detailReturn.value = null;
                loadInitial();
            } catch (error) {
                handleApiError(error);
            }
        };

        const formatDate = (d) => (d ? new Date(d).toLocaleDateString() : '');
        const getStatusClass = (s) => {
            if (s === 'approved') return 'badge-success';
            if (s === 'pending') return 'badge-warning';
            if (s === 'cancelled' || s === 'rejected') return 'badge-danger';
            return 'badge-secondary';
        };

        const tableContainer = ref(null);
        const loadMoreTrigger = ref(null);
        const handleScroll = (e) => {
            const el = e.target;
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 100 && hasMore.value && !loading.value) loadMore();
        };

        onMounted(() => {
            loadInitial();
            const saleId = route.query.sale_id;
            if (saleId) {
                showCreateModal.value = true;
                createForm.value.sale_id = Number(saleId);
                onSaleSelect();
            }
            setTimeout(() => {
                if (loadMoreTrigger.value && tableContainer.value && typeof IntersectionObserver !== 'undefined') {
                    const obs = new IntersectionObserver(
                        (entries) => { if (entries[0].isIntersecting && hasMore.value && !loading.value) loadMore(); },
                        { root: tableContainer.value, rootMargin: '50px', threshold: 0.1 }
                    );
                    obs.observe(loadMoreTrigger.value);
                }
            }, 100);
        });

        return {
            dateFrom,
            dateTo,
            statusFilter,
            customerId,
            customers: undefined,
            returns,
            filteredReturns,
            loading,
            hasMore,
            loadReturns,
            showCreateModal,
            openCreateModal,
            createForm,
            createFormRefundTotal,
            selectedSale,
            salesForReturn,
            onSaleSelect,
            submitReturn,
            saving,
            detailReturn,
            viewReturn,
            cancelReturn,
            formatDate,
            getStatusClass,
            tableContainer,
            loadMoreTrigger,
            handleScroll
        };
    }
};
</script>

<style scoped>
.returns-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.filters { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
.date-input, .select-input { padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
.table-container { background: white; border-radius: 8px; overflow-y: auto; max-height: calc(100vh - 280px); }
.load-more-trigger { min-height: 50px; padding: 15px; text-align: center; }
.loading-indicator, .no-more-indicator, .load-more-hint { padding: 15px; text-align: center; color: #666; font-size: 14px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; }
.data-table td { padding: 12px; border-top: 1px solid #e0e0e0; }
.badge-success { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.badge-warning { background: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.badge-danger { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.badge-secondary { background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.btn-sm { padding: 6px 12px; font-size: 12px; margin-right: 5px; }
.btn-danger { background: #dc3545; color: white; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: white; border-radius: 8px; padding: 30px; max-width: 560px; width: 90%; max-height: 90vh; overflow-y: auto; }
.modal-content.large { max-width: 720px; }
.modal-content h2 { margin-top: 0; margin-bottom: 20px; color: #333; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
.form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
.form-input.num { width: 80px; }
.form-hint { font-size: 12px; color: #666; margin-top: 4px; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.items-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.items-table th, .items-table td { padding: 8px; border: 1px solid #e0e0e0; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #667eea; color: white; }
.btn-secondary { background: #6c757d; color: white; }
</style>
