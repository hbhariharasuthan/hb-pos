<template>
    <div class="purchases-container">
        <div class="page-header">
            <h1>Purchase Bills</h1>
            <button @click="showModal = true" class="btn btn-primary">New Purchase Bill</button>
        </div>

        <div class="filters">
            <input v-model="dateFrom" type="date" class="date-input" />
            <input v-model="dateTo" type="date" class="date-input" />
            <button @click="loadPurchases" class="btn btn-secondary">Filter</button>
        </div>

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in filteredPurchases" :key="p.id">
                        <td>{{ p.bill_number }}</td>
                        <td>{{ formatDate(p.purchase_date) }}</td>
                        <td>{{ p.supplier?.name || '—' }}</td>
                        <td>{{ p.items?.length || 0 }}</td>
                        <td>₹{{ p.subtotal }}</td>
                        <td>₹{{ p.tax_amount }}</td>
                        <td>₹{{ p.total }}</td>
                        <td>
                            <button @click="viewBill(p.id)" class="btn-sm btn-primary">View Bill</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div ref="loadMoreTrigger" v-if="hasMore" class="load-more-trigger">
                <div v-if="loading" class="loading-indicator">Loading more...</div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredPurchases.length > 0" class="no-more-indicator">No more purchases to load</div>
        </div>

        <!-- New Purchase Modal -->
        <div v-if="showModal" class="modal-overlay" @click="showModal = false">
            <div class="modal-content large" @click.stop>
                <h2>New Purchase Bill</h2>
                <form @submit.prevent="savePurchase">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier (Customer)</label>
                            <select v-model="form.supplier_id" class="form-input">
                                <option value="">Select supplier</option>
                                <option v-for="c in suppliers" :key="c.id" :value="c.id">{{ c.name }} {{ c.phone ? ' – ' + c.phone : '' }}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Purchase Date *</label>
                            <input v-model="form.purchase_date" type="date" required class="form-input" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Items *</label>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Unit Cost (₹)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, idx) in form.items" :key="idx">
                                    <td>
                                        <select v-model="row.product_id" class="form-input" required @change="onProductChange(idx)">
                                            <option value="">Select product</option>
                                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input v-model.number="row.quantity" type="number" step="0.001" min="0.001" required class="form-input num" />
                                    </td>
                                    <td>
                                        <input v-model.number="row.unit_cost" type="number" step="0.01" min="0" required class="form-input num" />
                                    </td>
                                    <td>
                                        <button type="button" @click="removeItem(idx)" class="btn-sm btn-danger">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" @click="addItem" class="btn btn-outline">+ Add line</button>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Tax Rate (%)</label>
                            <input v-model.number="form.tax_rate" type="number" step="0.01" min="0" max="100" class="form-input" />
                        </div>
                        <div class="form-group">
                            <label>Bill Discount (₹)</label>
                            <input v-model.number="form.discount" type="number" step="0.01" min="0" class="form-input" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea v-model="form.notes" rows="2" class="form-input"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">Save Purchase</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';

export default {
    name: 'Purchases',
    setup() {
        const router = useRouter();
        const showModal = ref(false);
        const saving = ref(false);
        const dateFrom = ref('');
        const dateTo = ref('');
        const suppliers = ref([]);
        const products = ref([]);

        const {
            items: purchases,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            applyFilters
        } = usePaginatedDropdown('/api/purchases', {
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

        const filteredPurchases = computed(() => (purchases.value || []).filter(p => p != null && p.id != null));

        const form = ref({
            supplier_id: '',
            purchase_date: new Date().toISOString().slice(0, 10),
            items: [{ product_id: '', quantity: 1, unit_cost: 0 }],
            tax_rate: 0,
            discount: 0,
            notes: ''
        });

        const loadPurchases = () => loadInitial();

        const loadSuppliers = async () => {
            try {
                const r = await axios.get('/api/customers', { params: { per_page: 500 } });
                const data = r.data.data ?? r.data;
                suppliers.value = Array.isArray(data) ? data.filter(c => c && c.id) : [];
            } catch (e) {
                suppliers.value = [];
            }
        };

        const loadProductsForModal = async () => {
            try {
                const r = await axios.get('/api/products', { params: { per_page: 200 } });
                const data = r.data.data ?? r.data;
                products.value = Array.isArray(data) ? data.filter(p => p && p.id) : [];
            } catch (e) {
                products.value = [];
            }
        }

        const onProductChange = (idx) => {
            const id = form.value.items[idx].product_id;
            const p = products.value.find(pr => pr.id == id);
            if (p && form.value.items[idx].unit_cost === 0) {
                form.value.items[idx].unit_cost = parseFloat(p.cost_price) || 0;
            }
        };

        const addItem = () => {
            form.value.items.push({ product_id: '', quantity: 1, unit_cost: 0 });
        };

        const removeItem = (idx) => {
            form.value.items.splice(idx, 1);
        };

        const savePurchase = async () => {
            const items = form.value.items.filter(i => i.product_id && i.quantity > 0 && i.unit_cost >= 0);
            if (!items.length) {
                alert('Add at least one item.');
                return;
            }
            saving.value = true;
            try {
                await axios.post('/api/purchases', {
                    supplier_id: form.value.supplier_id || null,
                    purchase_date: form.value.purchase_date,
                    items: items.map(i => ({
                        product_id: i.product_id,
                        quantity: i.quantity,
                        unit_cost: i.unit_cost,
                        discount: 0
                    })),
                    tax_rate: form.value.tax_rate || 0,
                    discount: form.value.discount || 0,
                    notes: form.value.notes || null
                });
                showModal.value = false;
                form.value = {
                    supplier_id: '',
                    purchase_date: new Date().toISOString().slice(0, 10),
                    items: [{ product_id: '', quantity: 1, unit_cost: 0 }],
                    tax_rate: 0,
                    discount: 0,
                    notes: ''
                };
                loadInitial();
            } catch (err) {
                alert(err.response?.data?.message || err.response?.data?.error || 'Failed to save purchase');
            } finally {
                saving.value = false;
            }
        };

        const viewBill = (id) => router.push(`/purchases/${id}/bill`);

        const formatDate = (d) => d ? new Date(d).toLocaleDateString() : '';

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

        watch(showModal, (open) => {
            if (open) {
                loadSuppliers();
                loadProductsForModal();
            }
        });

        onMounted(() => {
            loadInitial();
            setTimeout(() => setupScrollObserver(), 100);
        });

        watch([loadMoreTrigger, tableContainer], () => {
            if (loadMoreTrigger.value && tableContainer.value) setupScrollObserver();
        });

        return {
            showModal,
            saving,
            dateFrom,
            dateTo,
            form,
            suppliers,
            products,
            filteredPurchases,
            loading,
            hasMore,
            handleScroll,
            tableContainer,
            loadMoreTrigger,
            loadPurchases,
            addItem,
            removeItem,
            onProductChange,
            savePurchase,
            viewBill,
            formatDate
        };
    }
};
</script>

<style scoped>
.purchases-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.filters { display: flex; gap: 15px; margin-bottom: 20px; }
.date-input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.table-container { background: white; border-radius: 8px; overflow-y: auto; max-height: calc(100vh - 250px); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; }
.data-table td { padding: 12px; border-top: 1px solid #e0e0e0; }
.load-more-trigger { min-height: 50px; padding: 15px; text-align: center; }
.loading-indicator, .no-more-indicator, .load-more-hint { padding: 15px; text-align: center; color: #666; font-size: 14px; }
.loading-indicator { color: #667eea; }
.load-more-hint { color: #999; font-size: 12px; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #667eea; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-danger { background: #dc3545; color: white; }
.btn-outline { background: transparent; border: 2px solid #667eea; color: #667eea; margin-top: 8px; }
.btn-outline:hover { background: #667eea; color: white; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: white; border-radius: 8px; padding: 30px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
.modal-content.large { max-width: 800px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
.form-input.num { max-width: 120px; }
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.items-table th, .items-table td { padding: 8px; border: 1px solid #e0e0e0; text-align: left; }
.items-table th { background: #f8f9fa; font-size: 12px; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
</style>
