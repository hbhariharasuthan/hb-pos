<template>
    <div class="inventory-container">
        <div class="page-header">
            <h1>Inventory Management</h1>
            <div class="header-actions">
                <button @click="showAdjustModal = true" class="btn btn-primary">Adjust Stock</button>
                <button @click="loadLowStock" class="btn btn-warning">Low Stock Alerts</button>
            </div>
        </div>

        <div class="filters">
            <input v-model="search" type="text" placeholder="Search products..." class="search-input" />
            <select v-model="categoryFilter" class="select-input">
                <option value="">All Categories</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <select v-model="stockFilter" class="select-input">
                <option value="">All Stock Levels</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
                <option value="in_stock">In Stock</option>
            </select>
            <button @click="loadInventory" class="btn btn-secondary">Refresh</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h3>Total Products</h3>
                    <p class="stat-value">{{ stats.totalProducts }}</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3>In Stock</h3>
                    <p class="stat-value">{{ stats.inStock }}</p>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <h3>Low Stock</h3>
                    <p class="stat-value">{{ stats.lowStock }}</p>
                </div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon">❌</div>
                <div class="stat-info">
                    <h3>Out of Stock</h3>
                    <p class="stat-value">{{ stats.outOfStock }}</p>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Min Level</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in filteredProducts" :key="product.id">
                        <td>
                            <strong>{{ product.name }}</strong>
                        </td>
                        <td>{{ product.sku }}</td>
                        <td>{{ product.category?.name || 'N/A' }}</td>
                        <td :class="getStockClass(product)">
                            {{ product.stock_quantity }} {{ product.unit }}
                        </td>
                        <td>{{ product.min_stock_level }} {{ product.unit }}</td>
                        <td>${{ product.cost_price }}</td>
                        <td>${{ (product.stock_quantity * product.cost_price).toFixed(2) }}</td>
                        <td>
                            <span :class="getStatusBadge(product)">
                                {{ getStockStatus(product) }}
                            </span>
                        </td>
                        <td>
                            <button @click="adjustStock(product)" class="btn-sm btn-primary">Adjust</button>
                            <button @click="viewHistory(product)" class="btn-sm btn-info">History</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Stock Adjustment Modal -->
        <div v-if="showAdjustModal" class="modal-overlay" @click="showAdjustModal = false">
            <div class="modal-content" @click.stop>
                <h2>Adjust Stock</h2>
                <form @submit.prevent="saveAdjustment">
                    <div class="form-group">
                        <label>Product</label>
                        <select v-model="adjustForm.product_id" @change="loadProductDetails" required>
                            <option value="">Select Product</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                        </select>
                    </div>
                    <div v-if="selectedProduct" class="product-info-box">
                        <p><strong>Current Stock:</strong> {{ selectedProduct.stock_quantity }} {{ selectedProduct.unit }}</p>
                        <p><strong>Min Level:</strong> {{ selectedProduct.min_stock_level }} {{ selectedProduct.unit }}</p>
                    </div>
                    <div class="form-group">
                        <label>Adjustment Type *</label>
                        <select v-model="adjustForm.type" required>
                            <option value="purchase">Purchase (Add Stock)</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input 
                            v-model.number="adjustForm.quantity" 
                            type="number" 
                            :placeholder="adjustForm.type === 'purchase' ? 'Enter quantity to add' : 'Enter adjustment amount (+/-)'"
                            required 
                        />
                        <small v-if="adjustForm.type === 'adjustment'">
                            Use positive number to add, negative to subtract
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Unit Cost</label>
                        <input v-model.number="adjustForm.unit_cost" type="number" step="0.01" />
                        <small>Leave empty to use product's current cost price</small>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea v-model="adjustForm.notes" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" @click="closeAdjustModal" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Adjustment</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stock History Modal -->
        <div v-if="showHistoryModal" class="modal-overlay" @click="showHistoryModal = false">
            <div class="modal-content large" @click.stop>
                <h2>Stock Movement History - {{ selectedProduct?.name }}</h2>
                <div class="history-list">
                    <div v-for="movement in stockHistory" :key="movement.id" class="history-item">
                        <div class="history-date">{{ formatDate(movement.created_at) }}</div>
                        <div class="history-type" :class="getMovementTypeClass(movement.type)">
                            {{ movement.type.toUpperCase() }}
                        </div>
                        <div class="history-quantity" :class="movement.quantity > 0 ? 'positive' : 'negative'">
                            {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                        </div>
                        <div class="history-cost">${{ movement.unit_cost || 'N/A' }}</div>
                        <div class="history-user">{{ movement.user?.name || 'System' }}</div>
                        <div class="history-notes">{{ movement.notes || '-' }}</div>
                    </div>
                    <div v-if="stockHistory.length === 0" class="no-history">
                        No stock movements found
                    </div>
                </div>
                <div class="modal-actions">
                    <button @click="showHistoryModal = false" class="btn btn-secondary">Close</button>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert Modal -->
        <div v-if="showLowStockModal" class="modal-overlay" @click="showLowStockModal = false">
            <div class="modal-content" @click.stop>
                <h2>Low Stock Alerts</h2>
                <div class="low-stock-list">
                    <div v-for="product in lowStockProducts" :key="product.id" class="low-stock-item">
                        <div class="item-info">
                            <h4>{{ product.name }}</h4>
                            <p>SKU: {{ product.sku }}</p>
                            <p>Current: {{ product.stock_quantity }} | Min: {{ product.min_stock_level }}</p>
                        </div>
                        <button @click="adjustStock(product)" class="btn-sm btn-primary">Restock</button>
                    </div>
                    <div v-if="lowStockProducts.length === 0" class="no-alerts">
                        No low stock items
                    </div>
                </div>
                <div class="modal-actions">
                    <button @click="showLowStockModal = false" class="btn btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'Inventory',
    setup() {
        const products = ref([]);
        const categories = ref([]);
        const stockHistory = ref([]);
        const lowStockProducts = ref([]);
        const search = ref('');
        const categoryFilter = ref('');
        const stockFilter = ref('');
        const showAdjustModal = ref(false);
        const showHistoryModal = ref(false);
        const showLowStockModal = ref(false);
        const selectedProduct = ref(null);
        
        const adjustForm = ref({
            product_id: '',
            type: 'purchase',
            quantity: 0,
            unit_cost: null,
            notes: ''
        });

        const stats = computed(() => {
            const total = products.value.length;
            const inStock = products.value.filter(p => p.stock_quantity > p.min_stock_level).length;
            const lowStock = products.value.filter(p => p.stock_quantity > 0 && p.stock_quantity <= p.min_stock_level).length;
            const outOfStock = products.value.filter(p => p.stock_quantity === 0).length;
            
            return { totalProducts: total, inStock, lowStock, outOfStock };
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
            
            if (stockFilter.value === 'low') {
                filtered = filtered.filter(p => p.stock_quantity > 0 && p.stock_quantity <= p.min_stock_level);
            } else if (stockFilter.value === 'out') {
                filtered = filtered.filter(p => p.stock_quantity === 0);
            } else if (stockFilter.value === 'in_stock') {
                filtered = filtered.filter(p => p.stock_quantity > p.min_stock_level);
            }
            
            return filtered;
        });

        const loadInventory = async () => {
            try {
                const response = await axios.get('/api/products', { params: { per_page: 1000 } });
                products.value = response.data.data || response.data;
            } catch (error) {
                console.error('Error loading inventory:', error);
                alert('Error loading inventory');
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

        const loadLowStock = async () => {
            try {
                const response = await axios.get('/api/stock/low-stock');
                lowStockProducts.value = response.data;
                showLowStockModal.value = true;
            } catch (error) {
                console.error('Error loading low stock:', error);
                alert('Error loading low stock alerts');
            }
        };

        const loadProductDetails = () => {
            if (adjustForm.value.product_id) {
                selectedProduct.value = products.value.find(p => p.id == adjustForm.value.product_id);
                if (selectedProduct.value && !adjustForm.value.unit_cost) {
                    adjustForm.value.unit_cost = selectedProduct.value.cost_price;
                }
            }
        };

        const adjustStock = (product) => {
            selectedProduct.value = product;
            adjustForm.value = {
                product_id: product.id,
                type: 'purchase',
                quantity: 0,
                unit_cost: product.cost_price,
                notes: ''
            };
            showAdjustModal.value = true;
        };

        const saveAdjustment = async () => {
            try {
                const payload = {
                    product_id: adjustForm.value.product_id,
                    type: adjustForm.value.type,
                    quantity: adjustForm.value.type === 'purchase' 
                        ? Math.abs(adjustForm.value.quantity)
                        : adjustForm.value.quantity,
                    unit_cost: adjustForm.value.unit_cost || null,
                    notes: adjustForm.value.notes
                };

                await axios.post('/api/stock/adjust', payload);
                alert('Stock adjusted successfully');
                await loadInventory();
                closeAdjustModal();
            } catch (error) {
                const message = error.response?.data?.message || 'Error adjusting stock';
                const errors = error.response?.data?.errors;
                if (errors) {
                    alert(message + ': ' + JSON.stringify(errors));
                } else {
                    alert(message);
                }
            }
        };

        const viewHistory = async (product) => {
            selectedProduct.value = product;
            try {
                const response = await axios.get('/api/stock/movements', {
                    params: { product_id: product.id, per_page: 100 }
                });
                stockHistory.value = response.data.data || response.data;
                showHistoryModal.value = true;
            } catch (error) {
                console.error('Error loading history:', error);
                alert('Error loading stock history');
            }
        };

        const closeAdjustModal = () => {
            showAdjustModal.value = false;
            selectedProduct.value = null;
            adjustForm.value = {
                product_id: '',
                type: 'purchase',
                quantity: 0,
                unit_cost: null,
                notes: ''
            };
        };

        const getStockClass = (product) => {
            if (product.stock_quantity === 0) return 'stock-out';
            if (product.stock_quantity <= product.min_stock_level) return 'stock-low';
            return 'stock-ok';
        };

        const getStockStatus = (product) => {
            if (product.stock_quantity === 0) return 'Out of Stock';
            if (product.stock_quantity <= product.min_stock_level) return 'Low Stock';
            return 'In Stock';
        };

        const getStatusBadge = (product) => {
            if (product.stock_quantity === 0) return 'badge-danger';
            if (product.stock_quantity <= product.min_stock_level) return 'badge-warning';
            return 'badge-success';
        };

        const getMovementTypeClass = (type) => {
            const classes = {
                purchase: 'type-purchase',
                sale: 'type-sale',
                return: 'type-return',
                adjustment: 'type-adjustment'
            };
            return classes[type] || 'type-other';
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleString();
        };

        onMounted(() => {
            loadInventory();
            loadCategories();
        });

        return {
            products,
            categories,
            stockHistory,
            lowStockProducts,
            search,
            categoryFilter,
            stockFilter,
            showAdjustModal,
            showHistoryModal,
            showLowStockModal,
            selectedProduct,
            adjustForm,
            stats,
            filteredProducts,
            loadInventory,
            loadLowStock,
            loadProductDetails,
            adjustStock,
            saveAdjustment,
            viewHistory,
            closeAdjustModal,
            getStockClass,
            getStockStatus,
            getStatusBadge,
            getMovementTypeClass,
            formatDate
        };
    }
};
</script>

<style scoped>
.inventory-container {
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

.filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-input, .select-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    flex: 1;
    min-width: 200px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card.warning {
    border-left: 4px solid #ffc107;
}

.stat-card.danger {
    border-left: 4px solid #dc3545;
}

.stat-icon {
    font-size: 32px;
}

.stat-info h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #666;
}

.stat-value {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
    color: #333;
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

.stock-out {
    color: #dc3545;
    font-weight: bold;
}

.stock-low {
    color: #ffc107;
    font-weight: bold;
}

.stock-ok {
    color: #28a745;
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

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    margin-right: 5px;
}

.btn-info {
    background: #17a2b8;
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

.modal-content.large {
    max-width: 900px;
}

.product-info-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
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
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.history-list {
    max-height: 500px;
    overflow-y: auto;
}

.history-item {
    display: grid;
    grid-template-columns: 150px 100px 80px 100px 150px 1fr;
    gap: 15px;
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
    align-items: center;
}

.history-date {
    font-size: 12px;
    color: #666;
}

.history-type {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.type-purchase {
    background: #28a745;
    color: white;
}

.type-sale {
    background: #dc3545;
    color: white;
}

.type-return {
    background: #17a2b8;
    color: white;
}

.type-adjustment {
    background: #ffc107;
    color: #333;
}

.history-quantity.positive {
    color: #28a745;
    font-weight: bold;
}

.history-quantity.negative {
    color: #dc3545;
    font-weight: bold;
}

.low-stock-list {
    max-height: 400px;
    overflow-y: auto;
}

.low-stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
}

.no-history, .no-alerts {
    text-align: center;
    padding: 40px;
    color: #999;
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

.btn-warning {
    background: #ffc107;
    color: #333;
}
</style>
