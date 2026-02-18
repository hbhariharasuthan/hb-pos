<template>
    <div class="categories-container">
        <div class="page-header">
            <h1>Categories Management</h1>
            <button @click="showModal = true" class="btn btn-primary">Add Category</button>
        </div>

        <div class="filters">
            <input v-model="search" type="text" placeholder="Search categories..." class="search-input" />
            <select v-model="statusFilter" class="select-input">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Products Count</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="category in filteredCategories" :key="category.id">
                        <td>{{ category.name }}</td>
                        <td>{{ category.code || 'N/A' }}</td>
                        <td>{{ category.description || 'N/A' }}</td>
                        <td>{{ category.products_count || 0 }}</td>
                        <td>
                            <span :class="category.is_active ? 'badge-success' : 'badge-danger'">
                                {{ category.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <button @click="editCategory(category)" class="btn-sm btn-primary">Edit</button>
                            <button @click="deleteCategory(category.id)" class="btn-sm btn-danger">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div ref="loadMoreTrigger" v-if="hasMore" class="load-more-trigger">
                <div v-if="loading" class="loading-indicator">Loading more categories...</div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredCategories.length > 0" class="no-more-indicator">No more categories to load</div>
        </div>

        <!-- Category Modal -->
        <div v-if="showModal" class="modal-overlay" @click="showModal = false">
            <div class="modal-content" @click.stop>
                <h2>{{ editingCategory ? 'Edit Category' : 'Add Category' }}</h2>
                <form @submit.prevent="saveCategory">
                    <div class="form-group">
                        <label>Name *</label>
                        <input v-model="form.name" type="text" required />
                    </div>
                    <div class="form-group">
                        <label>Code</label>
                        <input v-model="form.code" type="text" placeholder="Optional category code" />
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea v-model="form.description" rows="3" placeholder="Category description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input v-model="form.is_active" type="checkbox" />
                            Active
                        </label>
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
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';

export default {
    name: 'Categories',
    setup() {
        const search = ref('');
        const statusFilter = ref('');
        const showModal = ref(false);
        const editingCategory = ref(null);
        const form = ref({
            name: '',
            code: '',
            description: '',
            is_active: true
        });

        const {
            items: categories,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            search: searchCategories,
            updateFilter
        } = usePaginatedDropdown('/api/categories', {
            searchParam: 'search',
            initialFilters: {},
            perPage: 10
        });

        watch(search, (v) => searchCategories(v));
        watch(statusFilter, (v) => {
            if (v === 'active') updateFilter('is_active', 1);
            else if (v === 'inactive') updateFilter('is_active', 0);
            else updateFilter('is_active', null);
        });

        const filteredCategories = computed(() => (categories.value || []).filter(c => c != null && c.id != null));

        const loadCategories = () => loadInitial();

        const editCategory = (category) => {
            editingCategory.value = category;
            form.value = { ...category };
            showModal.value = true;
        };

        const saveCategory = async () => {
            try {
                if (editingCategory.value) {
                    await axios.put(`/api/categories/${editingCategory.value.id}`, form.value);
                } else {
                    await axios.post('/api/categories', form.value);
                }
                loadInitial();
                showModal.value = false;
                resetForm();
            } catch (error) {
                alert(error.response?.data?.message || 'Error saving category');
            }
        };

        const deleteCategory = async (id) => {
            if (!confirm('Are you sure you want to delete this category?')) return;
            try {
                await axios.delete(`/api/categories/${id}`);
                loadInitial();
            } catch (error) {
                alert(error.response?.data?.message || 'Error deleting category');
            }
        };

        const resetForm = () => {
            form.value = {
                name: '',
                code: '',
                description: '',
                is_active: true
            };
            editingCategory.value = null;
        };

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

        onMounted(() => {
            loadInitial();
            setTimeout(() => setupScrollObserver(), 100);
        });

        watch([loadMoreTrigger, tableContainer], () => {
            if (loadMoreTrigger.value && tableContainer.value) setupScrollObserver();
        });

        return {
            categories,
            search,
            loading,
            hasMore,
            handleScroll,
            tableContainer,
            loadMoreTrigger,
            statusFilter,
            showModal,
            editingCategory,
            form,
            filteredCategories,
            editCategory,
            saveCategory,
            deleteCategory
        };
    }
};
</script>

<style scoped>
.categories-container {
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

.loading-indicator { color: #667eea; }
.load-more-hint { color: #999; font-size: 12px; }

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

.modal-content h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
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
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
}

.form-group input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
}

.form-group label input[type="checkbox"] {
    display: inline-flex;
    align-items: center;
    gap: 8px;
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
