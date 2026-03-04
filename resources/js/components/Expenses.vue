<template>
    <div class="expenses-container">
        <div class="page-header">
            <h1>Expenses</h1>
            <div class="header-actions">
                <button @click="showModal = true" class="btn btn-primary">Add Expense</button>
            </div>
        </div>
        <div class="filters">
            <input v-model="search" type="text" placeholder="Search voucher, notes..." class="search-input" />
            <input v-model="dateFrom" type="date" class="date-input" />
            <input v-model="dateTo" type="date" class="date-input" />
            <select v-model="categoryId" class="select-input">
                <option value="">All Categories</option>
                <option v-for="c in expenseCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <button @click="loadExpenses" class="btn btn-secondary">Filter</button>
        </div>

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher #</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(exp, idx) in filteredExpenses" :key="exp?.id ?? `exp-${idx}`">
                        <td>{{ formatDate(exp.expense_date) }}</td>
                        <td>{{ exp.voucher_number }}</td>
                        <td>{{ exp.expense_category?.name || '—' }}</td>
                        <td>₹{{ Number(exp.amount).toFixed(2) }}</td>
                        <td>{{ exp.payment_method || '—' }}</td>
                        <td><span :class="getStatusClass(exp.status)">{{ exp.status }}</span></td>
                        <td>
                            <button @click="editExpense(exp)" class="btn-sm btn-primary">Edit</button>
                            <button
                                v-if="exp.status === 'approved'"
                                @click="refundExpense(exp)"
                                class="btn-sm btn-secondary"
                            >
                                Refund
                            </button>
                            <button @click="deleteExpense(exp.id)" class="btn-sm btn-danger">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div ref="loadMoreTrigger" v-if="hasMore" class="load-more-trigger">
                <div v-if="loading" class="loading-indicator">Loading more...</div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredExpenses.length > 0" class="no-more-indicator">No more expenses</div>
        </div>

        <div v-if="showModal" class="modal-overlay" @click="showModal = false">
            <div class="modal-content" @click.stop>
                <h2>{{ editingExpense ? 'Edit Expense' : 'Add Expense' }}</h2>
                <form @submit.prevent="saveExpense">
                    <div class="form-group">
                        <label>Category</label>
                        <select v-model="form.expense_category_id" class="form-input">
                            <option :value="null">— Select —</option>
                            <option v-for="c in expenseCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount *</label>
                        <input v-model.number="form.amount" type="number" step="0.01" min="0" required />
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input v-model="form.expense_date" type="date" required />
                    </div>
                    <div class="form-group">
                        <label>Payment method</label>
                        <select v-model="form.payment_method" class="form-input">
                            <option value="">— Select —</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="credit">Credit</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input v-model="form.reference" type="text" placeholder="Cheque no, etc." />
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select v-model="form.status" class="form-input">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea v-model="form.notes" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';
import { handleApiError } from '@/utils/errorHandler';

export default {
    name: 'Expenses',
    setup() {
        const toast = useToast();
        const search = ref('');
        const dateFrom = ref('');
        const dateTo = ref('');
        const categoryId = ref('');
        const expenseCategories = ref([]);
        const showModal = ref(false);
        const editingExpense = ref(null);
        const form = ref({
            expense_category_id: null,
            amount: 0,
            expense_date: new Date().toISOString().slice(0, 10),
            payment_method: '',
            reference: '',
            status: 'approved',
            notes: ''
        });

        const {
            items: expenses,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            search: searchExpenses,
            applyFilters,
            updateFilter
        } = usePaginatedDropdown('/api/expenses', {
            searchParam: 'search',
            initialFilters: {},
            perPage: 10
        });

        const loadCategories = async () => {
            try {
                const res = await axios.get('/api/expense-categories/all');
                expenseCategories.value = res.data || [];
            } catch (e) {
                console.error('Failed to load expense categories', e);
            }
        };

        watch(search, (v) => searchExpenses(v || ''));
        watch([dateFrom, dateTo, categoryId], () => {
            applyFilters({
                date_from: dateFrom.value || null,
                date_to: dateTo.value || null,
                expense_category_id: categoryId.value || null
            });
        });

        const filteredExpenses = computed(() => (expenses.value || []).filter(e => e != null && e.id != null));

        const loadExpenses = () => loadInitial();

        const editExpense = (exp) => {
            editingExpense.value = exp;
            form.value = {
                expense_category_id: exp.expense_category_id ?? null,
                amount: exp.amount,
                expense_date: (exp.expense_date || '').toString().slice(0, 10),
                payment_method: exp.payment_method || '',
                reference: exp.reference || '',
                status: exp.status || 'approved',
                notes: exp.notes || ''
            };
            showModal.value = true;
        };

        const saveExpense = async () => {
            try {
                if (editingExpense.value) {
                    await axios.put(`/api/expenses/${editingExpense.value.id}`, form.value);
                } else {
                    await axios.post('/api/expenses', form.value);
                }
                loadInitial();
                showModal.value = false;
                resetForm();
                toast.success('Saved successfully');
            } catch (error) {
                handleApiError(error);
            }
        };

        const deleteExpense = async (id) => {
            if (!confirm('Delete this expense?')) return;
            try {
                await axios.delete(`/api/expenses/${id}`);
                loadInitial();
                toast.success('Deleted successfully');
            } catch (error) {
                handleApiError(error);
            }
        };

        const refundExpense = async (exp) => {
            if (!confirm('Mark this expense as refunded and create a receipt entry?')) return;
            try {
                await axios.post(`/api/expenses/${exp.id}/refund`);
                loadInitial();
                toast.success('Expense refunded successfully');
            } catch (error) {
                handleApiError(error);
            }
        };

        const resetForm = () => {
            form.value = {
                expense_category_id: null,
                amount: 0,
                expense_date: new Date().toISOString().slice(0, 10),
                payment_method: '',
                reference: '',
                status: 'approved',
                notes: ''
            };
            editingExpense.value = null;
        };

        const formatDate = (d) => (d ? new Date(d).toLocaleDateString() : '');
        const getStatusClass = (s) => {
            if (s === 'approved') return 'badge-success';
            if (s === 'pending') return 'badge-warning';
            if (s === 'cancelled') return 'badge-danger';
            return 'badge-secondary';
        };

        const tableContainer = ref(null);
        const loadMoreTrigger = ref(null);
        const handleScroll = (e) => {
            const el = e.target;
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 100 && hasMore.value && !loading.value) loadMore();
        };

        onMounted(() => {
            loadCategories();
            loadInitial();
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
            expenses,
            search,
            dateFrom,
            dateTo,
            categoryId,
            expenseCategories,
            loading,
            hasMore,
            handleScroll,
            tableContainer,
            loadMoreTrigger,
            showModal,
            editingExpense,
            form,
            filteredExpenses,
            loadExpenses,
            editExpense,
            saveExpense,
            deleteExpense,
            refundExpense,
            formatDate,
            getStatusClass
        };
    }
};
</script>

<style scoped>
.expenses-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.filters { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
.search-input, .date-input, .select-input { padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
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
.modal-content h2 { margin-top: 0; margin-bottom: 20px; color: #333; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
.form-group input, .form-group textarea, .form-group select, .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #667eea; color: white; }
.btn-secondary { background: #6c757d; color: white; }
</style>
