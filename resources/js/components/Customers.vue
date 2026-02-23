<template>
    <div class="customers-container">
        <div class="page-header">
            <h1>Customers Management</h1>
            <div class="action-bar">
            <button @click="showModal = true" class="btn btn-primary">Add Customer</button>
            <button class="btn outline" @click="showImport = true">Import Customers</button>
            </div>
        </div>

        <div class="filters">
            <input v-model="search" type="text" placeholder="Search customers..." class="search-input" />
        </div>

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>GST number</th>
                        <th>Address</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(customer, idx) in filteredCustomers" :key="customer?.id ?? `customer-${idx}`">
                        <td>{{ customer.name }}</td>
                        <td>{{ customer.email || 'N/A' }}</td>
                        <td>{{ customer.phone || 'N/A' }}</td>
                        <td>{{ customer.gst_number || 'N/A' }}</td>
                        <td>{{ customer.address || 'N/A' }}</td>
                        <td>₹{{ customer.balance }}</td>
                        <td>
                            <button @click="editCustomer(customer)" class="btn-sm btn-primary">Edit</button>
                            <button @click="deleteCustomer(customer.id)" class="btn-sm btn-danger">Delete</button>
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
                    Loading more customers...
                </div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredCustomers.length > 0" class="no-more-indicator">
                No more customers to load
            </div>
        </div>

        <!-- Customer Modal -->
        <div v-if="showModal" class="modal-overlay" @click="showModal = false">
            <div class="modal-content" @click.stop>
                <h2>{{ editingCustomer ? 'Edit Customer' : 'Add Customer' }}</h2>
                <form @submit.prevent="saveCustomer">
                    <div class="form-group">
                        <label>Name *</label>
                        <input v-model="form.name" type="text" required />
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input v-model="form.email" type="email" />
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input v-model="form.phone" type="text" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input v-model="form.address" type="text" />
                    </div>
                     <div class="form-group">
                            <label>GST Number</label>
                            <input v-model="form.gst_number" type="text" />
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input v-model="form.city" type="text" />
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <input v-model="form.state" type="text" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Credit Limit</label>
                        <input v-model.number="form.credit_limit" type="number" step="0.01" />
                    </div>
                    <div class="form-group" v-if="!editingCustomer">
                        <label>Opening Balance *</label>
                        <input v-model.number="form.opening_balance" type="number" step="0.01" min="0" required />
                    </div>
                    <div class="form-actions">
                        <button type="button" @click="showModal = false" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
        <ImportModal
            :show="showImport"
            type="customers"
            @close="showImport = false"
        />
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';
import ImportModal from './ImportModal.vue';

export default {
    name: 'Customers',
    components: {
        ImportModal   // ✅ REQUIRED
    },
    setup() {
        const search = ref('');
        const showModal = ref(false);
        const showImport = ref(false)
        const editingCustomer = ref(null);
        const form = ref({
            name: '',
            email: '',
            phone: '',
            gst_number: '',
            address: '',
            city: '',
            state: '',
            postal_code: '',
            country: '',
            credit_limit: 0,
            opening_balance: 0,
            is_active: true
        });

        // Use paginated dropdown composable
        const {
            items: customers,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            search: searchCustomers
        } = usePaginatedDropdown('/api/customers', {
            searchParam: 'search',
            initialFilters: {},
            perPage: 10
        });

        // Watch for search changes
        watch(search, (newValue) => {
            searchCustomers(newValue);
        });

        const filteredCustomers = computed(() => {
            // Filter out null/undefined items to prevent errors
            return (customers.value || []).filter(customer => customer != null && customer.id != null);
        });

        // Intersection Observer for infinite scroll
        const scrollObserver = ref(null);
        const loadMoreTrigger = ref(null);
        const tableContainer = ref(null);

        // Setup intersection observer for infinite scroll
        const setupScrollObserver = () => {
            if (typeof IntersectionObserver === 'undefined') {
                // Fallback to scroll event for older browsers
                return;
            }

            if (!tableContainer.value || !loadMoreTrigger.value) {
                return;
            }

            scrollObserver.value = new IntersectionObserver(
                (entries) => {
                    const entry = entries[0];
                    if (entry.isIntersecting && hasMore.value && !loading.value) {
                        loadMore();
                    }
                },
                {
                    root: tableContainer.value, // Use scrollable container as root
                    rootMargin: '50px', // Trigger 50px before reaching the bottom
                    threshold: 0.1
                }
            );

            // Observe the trigger element
            scrollObserver.value.observe(loadMoreTrigger.value);
        };

        // Fallback scroll handler for older browsers
        const handleScroll = (event) => {
            const element = event.target;
            const scrollBottom = element.scrollHeight - element.scrollTop - element.clientHeight;
            
            // Load more when within 100px of bottom
            if (scrollBottom < 100 && hasMore.value && !loading.value) {
                loadMore();
            }
        };

        const editCustomer = (customer) => {
            editingCustomer.value = customer;
            form.value = { ...customer };
            showModal.value = true;
        };

        const saveCustomer = async () => {
            try {
                if (editingCustomer.value) {
                    await axios.put(`/api/customers/${editingCustomer.value.id}`, form.value);
                } else {
                    await axios.post('/api/customers', form.value);
                }
                loadInitial(); // Reload from page 1
                showModal.value = false;
                resetForm();
            } catch (error) {
                alert(error.response?.data?.message || 'Error saving customer');
            }
        };

        const deleteCustomer = async (id) => {
            if (!confirm('Are you sure you want to delete this customer?')) return;
            try {
                await axios.delete(`/api/customers/${id}`);
                loadInitial(); // Reload from page 1
            } catch (error) {
                alert(error.response?.data?.message || 'Error deleting customer');
            }
        };

        const resetForm = () => {
            form.value = {
                name: '',
                email: '',
                phone: '',
                gst_number: '',
                address: '',
                city: '',
                state: '',
                postal_code: '',
                country: '',
                credit_limit: 0,
                opening_balance: 0,
                is_active: true
            };
            editingCustomer.value = null;
        };

        onMounted(() => {
            loadInitial();
            // Setup scroll observer after DOM is ready
            setTimeout(() => {
                setupScrollObserver();
            }, 100);
        });

        // Watch for loadMoreTrigger element and setup observer
        watch([loadMoreTrigger, tableContainer], () => {
            if (loadMoreTrigger.value && tableContainer.value) {
                setupScrollObserver();
            }
        });

        return {
            customers,
            search,
            showModal,
            editingCustomer,
            form,
            filteredCustomers,
            loading,
            hasMore,
            handleScroll,
            tableContainer,
            loadMoreTrigger,
            loadMore,
            editCustomer,
            saveCustomer,
            deleteCustomer,
            showImport
        };
    }
};
</script>

<style scoped>
.customers-container {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.filters {
    margin-bottom: 20px;
}

.search-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    width: 100%;
    max-width: 400px;
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

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    margin-right: 5px;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 8px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
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
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
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
.action-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}

.btn {
    padding: 10px 16px;
    font-size: 14px;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* Primary button */
.btn.primary {
    background-color: #2563eb; /* blue */
    color: white;
}

.btn.primary:hover {
    background-color: #1d4ed8;
}

/* Outline button */
.btn.outline {
    background: transparent;
    color: #2563eb;
    border: 1px solid #2563eb;
}

.btn.outline:hover {
    background-color: #2563eb;
    color: white;
}
</style>
