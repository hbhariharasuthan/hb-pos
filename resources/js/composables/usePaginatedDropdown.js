import { ref, computed, watch } from 'vue';
import axios from 'axios';

/**
 * Composable for handling paginated dropdown data with infinite scroll
 * @param {string} endpoint - API endpoint URL
 * @param {Object} options - Configuration options
 * @param {string} options.searchParam - Search parameter name (default: 'search')
 * @param {Object} options.initialFilters - Initial filter values
 * @param {number} options.perPage - Items per page (default: 10)
 * @param {Function} options.mapResponse - Function to map response data
 */
export function usePaginatedDropdown(endpoint, options = {}) {
    const {
        searchParam = 'search',
        initialFilters = {},
        perPage = 10,
        mapResponse = null
    } = options;

    // State
    const items = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const total = ref(0);
    const searchQuery = ref('');
    const filters = ref({ ...initialFilters });
    const debounceTimer = ref(null);

    // Computed
    const hasMore = computed(() => currentPage.value < lastPage.value);
    const isEmpty = computed(() => items.value.length === 0 && !loading.value);

    /**
     * Debounce function for search
     */
    const debounce = (func, delay) => {
        return (...args) => {
            if (debounceTimer.value) {
                clearTimeout(debounceTimer.value);
            }
            debounceTimer.value = setTimeout(() => func(...args), delay);
        };
    };

    /**
     * Map response data if mapper function provided
     */
    const mapData = (data) => {
        if (mapResponse && typeof mapResponse === 'function') {
            return data.map(mapResponse);
        }
        return data;
    };

    /**
     * Load data from API
     */
    const loadData = async (page = 1, append = false) => {
        if (loading.value) return;

        loading.value = true;
        error.value = null;

        try {
            const params = {
                page,
                per_page: perPage,
            };

            // Add search query if provided
            if (searchQuery.value) {
                params[searchParam] = searchQuery.value;
            }

            // Add filters
            Object.keys(filters.value).forEach(key => {
                if (filters.value[key] !== null && filters.value[key] !== undefined && filters.value[key] !== '') {
                    params[key] = filters.value[key];
                }
            });

            const response = await axios.get(endpoint, { params });
            const responseData = response.data;

            // Handle Laravel pagination response
            if (responseData.data && Array.isArray(responseData.data)) {
                const mappedData = mapData(responseData.data);
                // Filter out null/undefined items to prevent errors
                const validData = mappedData.filter(item => item != null && (typeof item === 'object' ? item.id != null : true));
                
                if (append) {
                    items.value = [...items.value, ...validData];
                } else {
                    items.value = validData;
                }

                currentPage.value = responseData.current_page || page;
                lastPage.value = responseData.last_page || 1;
                total.value = responseData.total || validData.length;
            } else if (Array.isArray(responseData)) {
                // Handle non-paginated response (fallback)
                const mappedData = mapData(responseData);
                // Filter out null/undefined items to prevent errors
                const validData = mappedData.filter(item => item != null && (typeof item === 'object' ? item.id != null : true));
                items.value = append ? [...items.value, ...validData] : validData;
                currentPage.value = 1;
                lastPage.value = 1;
                total.value = validData.length;
            } else {
                throw new Error('Unexpected response format');
            }
        } catch (err) {
            error.value = err.response?.data?.message || err.message || 'Failed to load data';
            console.error('Error loading paginated data:', err);
            if (!append) {
                items.value = [];
            }
        } finally {
            loading.value = false;
        }
    };

    /**
     * Load initial data
     */
    const loadInitial = () => {
        currentPage.value = 1;
        loadData(1, false);
    };

    /**
     * Load next page (for infinite scroll)
     */
    const loadMore = async () => {
        if (!hasMore.value || loading.value) return;
        await loadData(currentPage.value + 1, true);
    };

    /**
     * Search function with debounce
     */
    const search = debounce((query) => {
        searchQuery.value = query;
        currentPage.value = 1;
        loadData(1, false);
    }, 300);

    /**
     * Apply filters
     */
    const applyFilters = (newFilters) => {
        filters.value = { ...filters.value, ...newFilters };
        currentPage.value = 1;
        loadData(1, false);
    };

    /**
     * Update a single filter
     */
    const updateFilter = (key, value) => {
        filters.value[key] = value;
        currentPage.value = 1;
        loadData(1, false);
    };

    /**
     * Reset to initial state
     */
    const reset = () => {
        items.value = [];
        currentPage.value = 1;
        lastPage.value = 1;
        total.value = 0;
        searchQuery.value = '';
        filters.value = { ...initialFilters };
        error.value = null;
    };

    /**
     * Refresh current data
     */
    const refresh = () => {
        loadData(currentPage.value, false);
    };

    return {
        // State
        items,
        loading,
        error,
        currentPage,
        lastPage,
        total,
        searchQuery,
        filters,
        
        // Computed
        hasMore,
        isEmpty,
        
        // Methods
        loadInitial,
        loadMore,
        search,
        applyFilters,
        updateFilter,
        reset,
        refresh,
    };
}
