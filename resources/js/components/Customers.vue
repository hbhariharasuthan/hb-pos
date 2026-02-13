<template>
    <div class="customers-container">
        <div class="page-header">
            <h1>Customers Management</h1>
            <button @click="showModal = true" class="btn btn-primary">Add Customer</button>
        </div>

        <div class="filters">
            <input v-model="search" type="text" placeholder="Search customers..." class="search-input" />
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="customer in filteredCustomers" :key="customer.id">
                        <td>{{ customer.name }}</td>
                        <td>{{ customer.email || 'N/A' }}</td>
                        <td>{{ customer.phone || 'N/A' }}</td>
                        <td>{{ customer.address || 'N/A' }}</td>
                        <td>₹{{ customer.balance }}</td>
                        <td>
                            <button @click="editCustomer(customer)" class="btn-sm btn-primary">Edit</button>
                            <button @click="deleteCustomer(customer.id)" class="btn-sm btn-danger">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'Customers',
    setup() {
        const customers = ref([]);
        const search = ref('');
        const showModal = ref(false);
        const editingCustomer = ref(null);
        const form = ref({
            name: '',
            email: '',
            phone: '',
            address: '',
            city: '',
            state: '',
            postal_code: '',
            country: '',
            credit_limit: 0,
            is_active: true
        });

        const filteredCustomers = computed(() => {
            if (!search.value) return customers.value;
            const s = search.value.toLowerCase();
            return customers.value.filter(c => 
                c.name.toLowerCase().includes(s) ||
                (c.email && c.email.toLowerCase().includes(s)) ||
                (c.phone && c.phone.includes(s))
            );
        });

        const loadCustomers = async () => {
            try {
                const response = await axios.get('/api/customers', { params: { per_page: 1000 } });
                customers.value = response.data.data || response.data;
            } catch (error) {
                console.error('Error loading customers:', error);
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
                await loadCustomers();
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
                await loadCustomers();
            } catch (error) {
                alert(error.response?.data?.message || 'Error deleting customer');
            }
        };

        const resetForm = () => {
            form.value = {
                name: '',
                email: '',
                phone: '',
                address: '',
                city: '',
                state: '',
                postal_code: '',
                country: '',
                credit_limit: 0,
                is_active: true
            };
            editingCustomer.value = null;
        };

        onMounted(() => {
            loadCustomers();
        });

        return {
            customers,
            search,
            showModal,
            editingCustomer,
            form,
            filteredCustomers,
            editCustomer,
            saveCustomer,
            deleteCustomer
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
</style>
