<template>
    <div class="master-container">
        <div class="page-header">
            <h1>GST Slabs (HSN) Management</h1>
            <div class="action-bar">
                <button @click="showModal = true" class="btn btn-primary">Add GST Slab</button>
                <button class="btn outline" @click="showImport = true">Import GST Slabs</button>
            </div>
        </div>

        <div class="filters">
            <input v-model="search" type="text" placeholder="Search by HSN code or description..." class="search-input" />
        </div>

        <div ref="tableContainer" class="table-container" @scroll="handleScroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>HSN Code</th>
                        <th>GST %</th>
                        <th>Description</th>
                        <th>Products Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(slab, idx) in filteredSlabs" :key="slab?.id ?? `slab-${idx}`">
                        <td>{{ slab.hsn_code }}</td>
                        <td>{{ slab.gst_percent ?? 0 }}%</td>
                        <td>{{ slab.description || '—' }}</td>
                        <td>{{ slab.products_count ?? 0 }}</td>
                        <td>
                            <button @click="editSlab(slab)" class="btn-sm btn-primary">Edit</button>
                            <button @click="deleteSlab(slab.id)" class="btn-sm btn-danger">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div ref="loadMoreTrigger" v-if="hasMore" class="load-more-trigger">
                <div v-if="loading" class="loading-indicator">Loading more...</div>
                <div v-else class="load-more-hint">Scroll for more</div>
            </div>
            <div v-if="!hasMore && filteredSlabs.length > 0" class="no-more-indicator">No more slabs to load</div>
        </div>

        <!-- GST Slab Modal -->
        <div v-if="showModal" class="modal-overlay" @click="showModal = false">
            <div class="modal-content" @click.stop>
                <h2>{{ editingSlab ? 'Edit GST Slab' : 'Add GST Slab' }}</h2>
                <form @submit.prevent="saveSlab">
                    <div class="form-group">
                        <label>HSN Code *</label>
                        <input v-model="form.hsn_code" type="text" maxlength="10" placeholder="e.g. 998314" required />
                    </div>
                    <div class="form-group">
                        <label>GST % *</label>
                        <input v-model.number="form.gst_percent" type="number" min="0" max="100" step="0.01" required />
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea v-model="form.description" rows="3" placeholder="Optional description"></textarea>
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
            type="gst_slabs"
            @close="showImport = false"
        />
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';
import ImportModal from './ImportModal.vue';
import { handleApiError } from '@/utils/errorHandler';

export default {
    name: 'GstSlabs',
    components: { ImportModal },
    setup() {
        const toast = useToast();
        const search = ref('');
        const showModal = ref(false);
        const showImport = ref(false);
        const editingSlab = ref(null);
        const form = ref({
            hsn_code: '',
            gst_percent: 0,
            description: '',
        });

        const {
            items: slabs,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            search: searchSlabs,
        } = usePaginatedDropdown('/api/gst-slabs', {
            searchParam: 'search',
            initialFilters: {},
            perPage: 10,
        });

        watch(search, (v) => searchSlabs(v));

        const filteredSlabs = computed(() => (slabs.value || []).filter((s) => s != null && s.id != null));

        const editSlab = (slab) => {
            editingSlab.value = slab;
            form.value = {
                hsn_code: slab.hsn_code ?? '',
                gst_percent: slab.gst_percent ?? 0,
                description: slab.description ?? '',
            };
            showModal.value = true;
        };

        const saveSlab = async () => {
            try {
                if (editingSlab.value) {
                    await axios.put(`/api/gst-slabs/${editingSlab.value.id}`, form.value);
                } else {
                    await axios.post('/api/gst-slabs', form.value);
                }
                loadInitial();
                showModal.value = false;
                resetForm();
                toast.success(editingSlab.value ? 'GST slab updated' : 'GST slab added');
            } catch (error) {
                handleApiError(error);
            }
        };

        const deleteSlab = async (id) => {
            if (!confirm('Are you sure you want to delete this GST slab?')) return;
            try {
                await axios.delete(`/api/gst-slabs/${id}`);
                loadInitial();
                toast.success('GST slab deleted successfully');
            } catch (error) {
                handleApiError(error);
            }
        };

        const resetForm = () => {
            form.value = {
                hsn_code: '',
                gst_percent: 0,
                description: '',
            };
            editingSlab.value = null;
        };

        const loadMoreTrigger = ref(null);
        const tableContainer = ref(null);

        const setupScrollObserver = () => {
            if (typeof IntersectionObserver === 'undefined' || !tableContainer.value || !loadMoreTrigger.value) return;
            const observer = new IntersectionObserver(
                (entries) => {
                    if (entries[0].isIntersecting && hasMore.value && !loading.value) loadMore();
                },
                { root: tableContainer.value, rootMargin: '50px', threshold: 0.1 }
            );
            observer.observe(loadMoreTrigger.value);
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
            slabs,
            search,
            loading,
            hasMore,
            handleScroll,
            tableContainer,
            loadMoreTrigger,
            showModal,
            editingSlab,
            form,
            filteredSlabs,
            editSlab,
            saveSlab,
            deleteSlab,
            showImport,
        };
    },
};
</script>

<style scoped>
.master-container {
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

.search-input {
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
    max-width: 500px;
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
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
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
}
</style>
