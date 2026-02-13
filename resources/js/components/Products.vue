<template>
    <div class="products-container">
        <div class="page-header">
            <h1>Products Management</h1>
            <button @click="showModal = true" class="btn btn-primary">Add Product</button>
        </div>

        <div class="filters">
            <input v-model="search" type="text" placeholder="Search products..." class="search-input" />
            <select v-model="categoryFilter" class="select-input">
                <option value="">All Categories</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in filteredProducts" :key="product.id">
                        <td>{{ product.name }}</td>
                        <td>{{ product.sku }}</td>
                        <td>{{ product.category?.name || 'N/A' }}</td>
                        <td>${{ product.selling_price }}</td>
                        <td :class="{ 'low-stock': product.stock_quantity <= product.min_stock_level }">
                            {{ product.stock_quantity }} {{ product.unit }}
                        </td>
                        <td>
                            <span :class="product.is_active ? 'badge-success' : 'badge-danger'">
                                {{ product.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <button @click="editProduct(product)" class="btn-sm btn-primary">Edit</button>
                            <button @click="deleteProduct(product.id)" class="btn-sm btn-danger">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Product Modal -->
        <div v-if="showModal" class="modal-overlay" @click="showModal = false">
            <div class="modal-content" @click.stop>
                <h2>{{ editingProduct ? 'Edit Product' : 'Add Product' }}</h2>
                <form @submit.prevent="saveProduct">
                    <div class="form-group">
                        <label>Name *</label>
                        <input v-model="form.name" type="text" required />
                    </div>
                    <div class="form-group">
                        <label>SKU *</label>
                        <input v-model="form.sku" type="text" required />
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select v-model="form.category_id">
                            <option value="">Select Category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Cost Price *</label>
                            <input v-model.number="form.cost_price" type="number" step="0.01" required />
                        </div>
                        <div class="form-group">
                            <label>Selling Price *</label>
                            <input v-model.number="form.selling_price" type="number" step="0.01" required />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Stock Quantity</label>
                            <input v-model.number="form.stock_quantity" type="number" />
                        </div>
                        <div class="form-group">
                            <label>Min Stock Level</label>
                            <input v-model.number="form.min_stock_level" type="number" />
                        </div>
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
    name: 'Products',
    setup() {
        const products = ref([]);
        const categories = ref([]);
        const search = ref('');
        const categoryFilter = ref('');
        const showModal = ref(false);
        const editingProduct = ref(null);
        const form = ref({
            name: '',
            sku: '',
            category_id: '',
            cost_price: 0,
            selling_price: 0,
            stock_quantity: 0,
            min_stock_level: 0,
            unit: 'pcs',
            is_active: true
        });

        const filteredProducts = computed(() => {
            let filtered = products.value;
            if (search.value) {
                const s = search.value.toLowerCase();
                filtered = filtered.filter(p => 
                    p.name.toLowerCase().includes(s) ||
                    p.sku.toLowerCase().includes(s)
                );
            }
            if (categoryFilter.value) {
                filtered = filtered.filter(p => p.category_id == categoryFilter.value);
            }
            return filtered;
        });

        const loadProducts = async () => {
            try {
                const response = await axios.get('/api/products', { params: { per_page: 1000 } });
                products.value = response.data.data || response.data;
            } catch (error) {
                console.error('Error loading products:', error);
            }
        };

        const loadCategories = async () => {
            try {
                const response = await axios.get('/api/categories');
                categories.value = response.data;
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        };

        const editProduct = (product) => {
            editingProduct.value = product;
            form.value = { ...product };
            showModal.value = true;
        };

        const saveProduct = async () => {
            try {
                if (editingProduct.value) {
                    await axios.put(`/api/products/${editingProduct.value.id}`, form.value);
                } else {
                    await axios.post('/api/products', form.value);
                }
                await loadProducts();
                showModal.value = false;
                resetForm();
            } catch (error) {
                alert(error.response?.data?.message || 'Error saving product');
            }
        };

        const deleteProduct = async (id) => {
            if (!confirm('Are you sure you want to delete this product?')) return;
            try {
                await axios.delete(`/api/products/${id}`);
                await loadProducts();
            } catch (error) {
                alert(error.response?.data?.message || 'Error deleting product');
            }
        };

        const resetForm = () => {
            form.value = {
                name: '',
                sku: '',
                category_id: '',
                cost_price: 0,
                selling_price: 0,
                stock_quantity: 0,
                min_stock_level: 0,
                unit: 'pcs',
                is_active: true
            };
            editingProduct.value = null;
        };

        onMounted(() => {
            loadProducts();
            loadCategories();
        });

        return {
            products,
            categories,
            search,
            categoryFilter,
            showModal,
            editingProduct,
            form,
            filteredProducts,
            editProduct,
            saveProduct,
            deleteProduct
        };
    }
};
</script>

<style scoped>
.products-container {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.search-input, .select-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    flex: 1;
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

.low-stock {
    color: #e74c3c;
    font-weight: bold;
}

.badge-success {
    background: #28a745;
    color: white;
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

.form-group input,
.form-group select {
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
