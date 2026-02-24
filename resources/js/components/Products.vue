<template>
    <div class="products-container">
        <div class="page-header">
            <h1>Products Management</h1>
            <div class="action-bar">
            <button @click="showModal = true" class="btn btn-primary">Add Product</button>
            <button class="btn outline" @click="showImport = true">Import Products</button>
            </div>
        </div>

        <div class="filters">
            <input v-model="search" type="text" placeholder="Search products..." class="search-input" />
            <div class="filter-dropdown">
                <PaginatedDropdown
                    v-model="categoryFilter"
                    endpoint="/api/categories"
                    value-key="id"
                    label-key="name"
                    placeholder="All Categories"
                    include-all-option
                    all-option-label="All Categories"
                />
            </div>
            <div class="filter-dropdown">
                <PaginatedDropdown
                    v-model="brandFilter"
                    endpoint="/api/brands"
                    value-key="id"
                    label-key="name"
                    placeholder="All Brands"
                    include-all-option
                    all-option-label="All Brands"
                />
            </div>
        </div>

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(product, idx) in filteredProducts" :key="product?.id ?? `product-${idx}`">
                        <td>{{ product.name }}</td>
                        <td>{{ product.sku }}</td>
                        <td>{{ product.category?.name || 'N/A' }}</td>
                        <td>{{ product.brand?.name || 'N/A' }}</td>
                        <td>₹{{ product.selling_price }}</td>
                        <td :class="{ 'low-stock': product.stock_quantity <= product.min_stock_level }">
                            {{ formatQty(product.stock_quantity, product.unit) }}
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
            <div 
                ref="loadMoreTrigger"
                v-if="hasMore"
                class="load-more-trigger"
            >
                <div v-if="loading" class="loading-indicator">
                    Loading more products...
                </div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredProducts.length > 0" class="no-more-indicator">
                No more products to load
            </div>
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
                        <PaginatedDropdown
                            v-model="form.category_id"
                            endpoint="/api/categories"
                            value-key="id"
                            label-key="name"
                            placeholder="Select Category"
                            emit-full-item
                        />
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <PaginatedDropdown
                            v-model="form.brand_id"
                            endpoint="/api/brands"
                            value-key="id"
                            label-key="name"
                            placeholder="Select Brand"
                            emit-full-item
                        />
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <select v-model="form.unit">
                            <option value="pcs">pcs (pieces)</option>
                            <option value="kg">kg</option>
                            <option value="g">g</option>
                            <option value="box">box</option>
                            <option value="meter">meter</option>
                            <option value="ltr">ltr</option>
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
                            <input v-model.number="form.stock_quantity" type="number" :step="isWeightUnit(form.unit) ? 0.001 : 1" min="0" />
                        </div>
                        <div class="form-group">
                            <label>Min Stock Level</label>
                            <input v-model.number="form.min_stock_level" type="number" :step="isWeightUnit(form.unit) ? 0.001 : 1" min="0" />
                        </div>
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
            type="products"
            @close="showImport = false"
        />
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { toId } from '../utils/toId.js';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';
import PaginatedDropdown from '../components/PaginatedDropdown.vue';
import ImportModal from './ImportModal.vue';
import { handleApiError } from '@/utils/errorHandler';

export default {
    name: 'Products',
     components: {
     PaginatedDropdown,ImportModal
     },
    setup() {
        const search = ref('');
        const categoryFilter = ref('');
        const brandFilter = ref('');
        const showModal = ref(false);
        const showImport = ref(false)
        const editingProduct = ref(null);
        const form = ref({
            name: '',
            sku: '',
            category_id: '',
            brand_id: '',
            cost_price: 0,
            selling_price: 0,
            stock_quantity: 0,
            min_stock_level: 0,
            unit: 'pcs',
            is_active: true
        });

        // Use paginated dropdown composable
        const {
            items: products,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            search: searchProducts,
            updateFilter
        } = usePaginatedDropdown('/api/products', {
            searchParam: 'search',
            initialFilters: {},
            perPage: 10
        });

        // Watch for search changes
        watch(search, (newValue) => {
            searchProducts(newValue);
        });

        // Watch for category filter changes
        watch(categoryFilter, (newValue) => {
            updateFilter('category_id', newValue || null);
        });
        watch(brandFilter, (newValue) => {
            updateFilter('brand_id', newValue || null);
        });

        const filteredProducts = computed(() => {
            // Filter out null/undefined items to prevent errors
            return (products.value || []).filter(product => product != null && product.id != null);
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

        const editProduct = (product) => {
            editingProduct.value = product;

            form.value = {
                ...product,
                category_id: product.category || null,
                brand_id: product.brand || null,
            };

            showModal.value = true;
        };

        const saveProduct = async () => {
            try {
                const payload = {
                    ...form.value,
                    category_id: toId(form.value.category_id),
                    brand_id: toId(form.value.brand_id),
                };
                if (editingProduct.value) {
                    await axios.put(`/api/products/${editingProduct.value.id}`, payload);
                } else {
                    await axios.post('/api/products', payload);
                }
                loadInitial(); // Reload from page 1
                showModal.value = false;
                resetForm();
            } catch (error) {
                handleApiError(error);
            }
        };

        const deleteProduct = async (id) => {
            if (!confirm('Are you sure you want to delete this product?')) return;
            try {
                await axios.delete(`/api/products/${id}`);
                loadInitial(); // Reload from page 1
                handleApiError("Record deleted");
            } catch (error) {
                handleApiError(error);
            }
        };

        const isWeightUnit = (unit) => {
            const u = (unit || '').toLowerCase();
            return ['kg', 'g', 'gm', 'gram', 'grams', 'ltr'].includes(u);
        };

        const formatQty = (qty, unit) => {
            if (qty === null || qty === undefined) return '0';
            const u = (unit || 'pcs').toLowerCase();
            const n = parseFloat(qty);
            if (isWeightUnit(u)) return Number(n) === parseInt(n, 10) ? n + ' ' + u : parseFloat(n).toFixed(3) + ' ' + u;
            return parseInt(n, 10) + ' ' + u;
        };

        const resetForm = () => {
            form.value = {
                name: '',
                sku: '',
                category_id: '',
                brand_id: '',
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
            products,
            search,
            categoryFilter,
            brandFilter,
            showModal,
            editingProduct,
            form,
            filteredProducts,
            loading,
            hasMore,
            handleScroll,
            tableContainer,
            loadMoreTrigger,
            loadMore,
            editProduct,
            saveProduct,
            deleteProduct,
            isWeightUnit,
            formatQty,
            showImport
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
    overflow: visible;
}

.search-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    flex: 1;
}

.filters .filter-dropdown {
    flex: 1;
    min-width: 150px;
    overflow: visible;
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
