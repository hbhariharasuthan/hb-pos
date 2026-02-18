<template>
    <div class="paginated-dropdown">
        <div class="dropdown-header">
            <input
                v-model="localSearch"
                type="text"
                :placeholder="placeholder || 'Search...'"
                class="search-input"
                @input="handleSearch"
            />
        </div>
        <div
            ref="dropdownList"
            class="dropdown-list"
            @scroll="handleScroll"
        >
            <div v-if="loading && (validItems.length === 0 || items.length === 0)" class="loading-state">
                Loading...
            </div>
            <div v-else-if="isEmpty && !loading" class="empty-state">
                No items found
            </div>
            <template v-else>
                <div
                    v-for="item in validItems"
                    :key="getValue(item)"
                    class="dropdown-item"
                    :class="{ 'selected': getValue(item) === modelValue }"
                    @click="selectItem(item)"
                >
                    <span class="item-label">{{ getLabel(item) }}</span>
                    <span v-if="getSecondaryLabel(item)" class="item-secondary-label">{{ getSecondaryLabel(item) }}</span>
                </div>
            </template>
            <div v-if="loading && validItems.length > 0" class="loading-more">
                Loading more...
            </div>
            <div v-if="!hasMore && validItems.length > 0" class="no-more">
                No more items
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';

export default {
    name: 'PaginatedDropdown',
    props: {
        modelValue: {
            type: [String, Number, Object],
            default: null
        },
        emitFullItem: {
            type: Boolean,
            default: false
        },
        endpoint: {
            type: String,
            required: true
        },
        searchParam: {
            type: String,
            default: 'search'
        },
        filters: {
            type: Object,
            default: () => ({})
        },
        valueKey: {
            type: String,
            default: 'id'
        },
        labelKey: {
            type: String,
            default: 'name'
        },
        secondaryLabelKey: {
            type: String,
            default: null
        },
        placeholder: {
            type: String,
            default: 'Search...'
        }
    },
    emits: ['update:modelValue', 'select'],
    setup(props, { emit }) {
        const dropdownList = ref(null);
        const localSearch = ref('');

        const {
            items,
            loading,
            error,
            hasMore,
            isEmpty,
            loadInitial,
            loadMore,
            search,
            applyFilters,
            reset
        } = usePaginatedDropdown(props.endpoint, {
            searchParam: props.searchParam,
            initialFilters: props.filters,
            perPage: 10
        });

        // Watch for filter changes
        watch(() => props.filters, (newFilters) => {
            applyFilters(newFilters);
        }, { deep: true });

        // Handle search input
        const handleSearch = (event) => {
            localSearch.value = event.target.value;
            search(event.target.value);
        };

        // Handle scroll for infinite loading
        const handleScroll = (event) => {
            const element = event.target;
            const scrollBottom = element.scrollHeight - element.scrollTop - element.clientHeight;
            
            // Load more when within 50px of bottom
            if (scrollBottom < 50 && hasMore.value && !loading.value) {
                loadMore();
            }
        };

        // Filter out null/undefined items
        const validItems = computed(() => {
            return (items.value || []).filter(item => 
                item != null && 
                (typeof item === 'object' ? item[props.valueKey] != null : true)
            );
        });

        // Get value from item (with safety checks)
        const getValue = (item) => {
            if (item == null) return `null_${Date.now()}_${Math.random()}`;
            if (typeof item === 'object') {
                const value = item[props.valueKey];
                if (value == null) return `null_${Date.now()}_${Math.random()}`;
                return value;
            }
            return item;
        };

        // Get label from item
        const getLabel = (item) => {
            if (typeof item === 'object' && item !== null) {
                return item[props.labelKey] || String(item[props.valueKey]);
            }
            return String(item);
        };

        // Get secondary label from item
        const getSecondaryLabel = (item) => {
            if (typeof item === 'object' && item !== null && props.secondaryLabelKey) {
                return item[props.secondaryLabelKey] || '';
            }
            return '';
        };

        // Select item
        const selectItem = (item) => {
            const value = getValue(item);
            emit('update:modelValue', props.emitFullItem ? item : value);
            emit('select', item);
        };

        // Initialize on mount
        onMounted(() => {
            loadInitial();
        });

        // Cleanup on unmount
        onUnmounted(() => {
            reset();
        });

        return {
            dropdownList,
            localSearch,
            items,
            validItems,
            loading,
            error,
            hasMore,
            isEmpty,
            handleSearch,
            handleScroll,
            getValue,
            getLabel,
            getSecondaryLabel,
            selectItem
        };
    }
};
</script>

<style scoped>
.paginated-dropdown {
    border: 1px solid #ddd;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.dropdown-header {
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.search-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
}

.dropdown-list {
    max-height: 300px;
    overflow-y: auto;
    padding: 5px 0;
}

.dropdown-item {
    padding: 10px 15px;
    cursor: pointer;
    transition: background-color 0.2s;
    border-bottom: 1px solid #f0f0f0;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item.selected {
    background-color: #e7f0ff;
    font-weight: 500;
}

.item-label {
    display: block;
    color: #333;
    font-size: 14px;
    font-weight: 500;
}

.item-secondary-label {
    display: block;
    color: #666;
    font-size: 12px;
    margin-top: 2px;
}

.loading-state,
.loading-more,
.empty-state,
.no-more {
    padding: 15px;
    text-align: center;
    color: #666;
    font-size: 14px;
}

.loading-state,
.loading-more {
    color: #667eea;
}

.empty-state {
    color: #999;
}

.no-more {
    color: #ccc;
    font-size: 12px;
    padding: 10px;
}
</style>
