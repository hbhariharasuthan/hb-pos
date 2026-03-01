<template>
    <div class="pos-container">
        <div class="pos-header">
            <h1>Point of Sale</h1>
            <div class="pos-actions">
                <button @click="clearCart" class="btn btn-secondary">Clear</button>
                <button @click="toggleCustomerModal" class="btn btn-secondary">Select Customer</button>
            </div>
        </div>

        <div class="pos-content">
            <div class="pos-left">
                     <div class="product-search">
                        <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search products by name, SKU, or barcode..."
                        class="search-input"
                        @input="onSearchInput"
                        />
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

                <div ref="gridContainer" class="products-grid-wrapper" @scroll="handleScroll">
                    <div class="products-grid">
                        <div
                            v-for="(product, idx) in filteredProducts"
                            :key="product?.id ?? `product-${idx}`"
                            class="product-card"
                            :class="{ 'low-stock': product.stock_quantity <= product.min_stock_level }"
                            @click="addToCart(product)"
                        >
                            <div class="product-info">
                                <h3>{{ product.name }}</h3>
                                <p class="product-sku">SKU: {{ product.sku }}</p>
                                <p class="product-price">₹{{ product.selling_price }}</p>
                                <p class="product-stock">Stock: {{ formatQty(product.stock_quantity, product.unit) }}</p>
                            </div>
                            <div v-if="product.stock_quantity <= product.min_stock_level" class="low-stock-tooltip">
                                ⚠️ Low Stock Alert<br>
                                Current: {{ product.stock_quantity }}<br>
                                Minimum: {{ product.min_stock_level }}
                            </div>
                        </div>
                    </div>
                    <div
                        ref="loadMoreTrigger"
                        v-if="hasMore"
                        class="load-more-trigger"
                    >
                        <div v-if="loading" class="loading-indicator">Loading more products...</div>
                        <div v-else class="load-more-hint">Scroll for more</div>
                    </div>
                    <div v-if="!hasMore && filteredProducts.length > 0" class="no-more-indicator">No more products to load</div>
                </div>
            </div>

            <div class="pos-right">
                <div class="cart-section">
                    <h2>Cart</h2>
                    <div v-if="cart.length === 0" class="empty-cart">
                        <p>Cart is empty</p>
                    </div>
                    <div v-else class="cart-items">
                        <div v-for="(item, index) in cart" :key="index" class="cart-item">
                            <div class="item-info">
                                <h4>{{ item.name }}</h4>
                                <div class="price-row">
                                    <span class="currency">₹</span>

                                    <template v-if="posConfig.view_total_editable">
                                        <template v-if="editingPriceIndex === index">
                                           <input
                                            v-model="item.price"
                                            type="text"
                                            inputmode="decimal"
                                            class="qty-input"
                                            @blur="validatePrice(index); recalculateCart(); editingPriceIndex = null"
                                            :title="`Original: ₹${item.original_price}`"
                                            autofocus
                                        />

                                        </template>
                                        <template v-else>
                                            <span class="text-display price-text" @click="startEditPrice(index)">
                                                {{ Number(item.price || 0).toFixed(2) }}
                                            </span>
                                        </template>
                                    </template>
                                    <template v-else>
                                        <span class="text-display">
                                                {{ Number(item.price || 0).toFixed(2) }}
                                        </span>
                                    </template>

                                    <small class="price-mult">
                                        × {{ formatCartQty(item.quantity, item.unit) }} {{ item.unit || 'pcs' }}
                                    </small>
                                </div>
                            </div>
                            <div class="item-actions">
                                <button @click="updateQuantity(index, item.quantity - qtyStep(item.unit))" class="btn-qty">-</button>
                                <input
                                    v-model.number="item.quantity"
                                    type="number"
                                    :step="qtyStep(item.unit)"
                                    :min="qtyStep(item.unit)"
                                    class="qty-input"
                                    @change="validateCartQty(index)"
                                />
                                <button @click="updateQuantity(index, item.quantity + qtyStep(item.unit))" class="btn-qty">+</button>
                                <button @click="removeFromCart(index)" class="btn-remove">×</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>₹{{ subtotal.toFixed(2) }}</span>
                    </div>
                    <div class="summary-row">
                        <label>Tax Rate (%):</label>
                        <input v-model.number="taxRate" type="number" min="0" max="100" class="input-small" />
                    </div>
                    <template v-if="taxAmount > 0">
                        <div class="summary-row">
                            <span>CGST ({{ cgstRate.toFixed(1) }}%):</span>
                            <span>₹{{ cgstAmount.toFixed(2) }}</span>
                        </div>
                        <div class="summary-row">
                            <span>SGST ({{ sgstRate.toFixed(1) }}%):</span>
                            <span>₹{{ sgstAmount.toFixed(2) }}</span>
                        </div>
                    </template>
                    <div class="summary-row">
                        <label>Discount:</label>
                        <input v-model.number="discount" type="number" min="0" class="input-small" />
                    </div>
                    <div class="summary-row">
                        <label>Payment Method:</label>
                        <select v-model="paymentMethod" class="input-small">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="credit">Credit</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>₹{{ total.toFixed(2) }}</span>
                    </div>
                </div>

                <button @click="processSale" class="btn btn-primary btn-large" :disabled="cart.length === 0 || processing">
                    {{ processing ? 'Processing...' : 'Complete Sale' }}
                </button>
            </div>
        </div>


        <!-- Customer Modal -->
        <div v-if="showCustomerModal" class="modal-overlay" @click="toggleCustomerModal">
            <div class="modal-content" @click.stop>
                <h2>Select Customer</h2>
                
                <div v-if="showAddCustomerForm" class="add-customer-form">
                    <h3>Add New Customer</h3>
                    <div class="form-group">
                        <label>Name *</label>
                        <input v-model="newCustomer.name" type="text" placeholder="Customer name" required />
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input v-model="newCustomer.phone" type="text" placeholder="Phone number" required />
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input v-model="newCustomer.email" type="email" placeholder="Email (optional)" />
                    </div>
                    <div class="form-actions">
                        <button type="button" @click="showAddCustomerForm = false" class="btn btn-secondary">Cancel</button>
                        <button type="button" @click="addNewCustomer" class="btn btn-primary">Add Customer</button>
                    </div>
                </div>

                <div v-else>
                    <div class="customer-item walk-in" @click="selectCustomer(null)">
                        <strong>Walk-in Customer</strong>
                    </div>
                    <PaginatedDropdown
                        :model-value="selectedCustomer?.id"
                        endpoint="/api/customers"
                        search-param="search"
                        value-key="id"
                        label-key="name"
                        secondary-label-key="phone"
                        placeholder="Search customers..."
                        :emit-full-item="true"
                        @select="handleCustomerSelect"
                    />
                    <div class="add-customer-option">
                        <button @click="showAddCustomerForm = true" class="btn btn-outline">+ Add New Customer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import PaginatedDropdown from './PaginatedDropdown.vue';
import { usePaginatedDropdown } from '../composables/usePaginatedDropdown.js';
import { useClientInfo } from '@/composables/useClientInfo.js'
import { handleApiError } from '@/utils/errorHandler';

export default {
    name: 'POS',
    components: { PaginatedDropdown },
    setup() {
        const cart = ref([]);
        const posConfig = ref({ view_total_editable: false });
        const editingPriceIndex = ref(null);
        const searchQuery = ref('');
        const taxRate = ref(0);
        const discount = ref(0);
        const paymentMethod = ref('cash');
        const selectedCustomer = ref(null);
        const showCustomerModal = ref(false);
        const showAddCustomerForm = ref(false);
        const processing = ref(false);
        const lastSale = ref(null);
        const showReceipt = ref(false);
        const categoryFilter = ref('');
        const brandFilter = ref('');
        const client = useClientInfo();
        const newCustomer = ref({
            name: '',
            phone: '',
            email: ''
        });

        const {
            items: products,
            loading,
            hasMore,
            loadInitial,
            loadMore,
            search: searchProducts,
            updateFilter
        } = usePaginatedDropdown('/api/pos/products', {
            searchParam: 'search',
            initialFilters: {},
            perPage: 20
        });

        watch(searchQuery, (v) => searchProducts(v));

        // Watch for category filter changes
        watch(categoryFilter, (newValue) => {
            updateFilter('category_id', newValue || null);
        });
        watch(brandFilter, (newValue) => {
            updateFilter('brand_id', newValue || null);
        });

        const filteredProducts = computed(() => (products.value || []).filter(p => p != null && p.id != null));

        const onSearchInput = () => {
            searchProducts(searchQuery.value);
        };

        const subtotal = computed(() => {
            return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        });

        const taxAmount = computed(() => {
            return (subtotal.value - discount.value) * (taxRate.value / 100);
        });

        const cgstRate = computed(() => (taxRate.value || 0) / 2);
        const sgstRate = computed(() => (taxRate.value || 0) / 2);
        const cgstAmount = computed(() => (taxAmount.value || 0) / 2);
        const sgstAmount = computed(() => (taxAmount.value || 0) / 2);

        const total = computed(() => {
            return subtotal.value - discount.value + taxAmount.value;
        });

        const handleCustomerSelect = (customer) => {
            if (!customer) {
                selectCustomer(null);
                return;
            }
            selectCustomer(customer);
        };

        const scrollObserver = ref(null);
        const loadMoreTrigger = ref(null);
        const gridContainer = ref(null);

        const setupScrollObserver = () => {
            if (typeof IntersectionObserver === 'undefined' || !gridContainer.value || !loadMoreTrigger.value) return;
            scrollObserver.value = new IntersectionObserver(
                (entries) => {
                    if (entries[0].isIntersecting && hasMore.value && !loading.value) loadMore();
                },
                { root: gridContainer.value, rootMargin: '80px', threshold: 0.1 }
            );
            scrollObserver.value.observe(loadMoreTrigger.value);
        };

        const handleScroll = (e) => {
            const el = e.target;
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 120 && hasMore.value && !loading.value) loadMore();
        };

        const isWeightUnit = (unit) => {
            const u = (unit || 'pcs').toLowerCase();
            return ['kg', 'g', 'gm', 'gram', 'ltr'].includes(u);
        };

        const qtyStep = (unit) => isWeightUnit(unit) ? 0.5 : 1;

        const formatQty = (qty, unit) => {
            if (qty === null || qty === undefined) return '0';
            const n = parseFloat(qty);
            const u = (unit || 'pcs').toLowerCase();
            if (isWeightUnit(u)) return Number(n) === parseInt(n, 10) ? n : parseFloat(n).toFixed(2);
            return parseInt(n, 10);
        };

        const formatCartQty = (qty, unit) => {
            if (qty === null || qty === undefined) return '0';
            const n = parseFloat(qty);
            const u = (unit || 'pcs').toLowerCase();
            if (isWeightUnit(u)) return Number(n) === parseInt(n, 10) ? n : parseFloat(n).toFixed(2);
            return parseInt(n, 10);
        };

        const addToCart = (product) => {
            const stock = parseFloat(product.stock_quantity);
            if (stock <= 0) {
                handleApiError('Product out of stock');
                return;
            }
            const unit = product.unit || 'pcs';
            const step = qtyStep(unit);

            const existingItem = cart.value.find(item => item.id === product.id);
            if (existingItem) {
                const newQty = parseFloat(existingItem.quantity) + step;
                if (newQty <= stock) {
                    existingItem.quantity = isWeightUnit(unit) ? parseFloat(newQty.toFixed(3)) : Math.floor(newQty);
                } else {
                    handleApiError('Insufficient stock. Available: ' + formatQty(stock, unit) + ' ' + unit);
                }
            } else {
                const startQty = isWeightUnit(unit) ? 0.5 : 1;
                cart.value.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.selling_price),
                    original_price: parseFloat(product.selling_price),
                    quantity: startQty,
                    unit: unit,
                    product: product
                });
            }
        };

        const validatePrice = (index) => {
            const item = cart.value[index];

            let p = parseFloat(item.price);

            if (isNaN(p) || p < 0) {
                item.price = parseFloat(
                    item.original_price ?? item.product?.selling_price ?? 0
                ).toFixed(2);
            } else {
                item.price = p.toFixed(2);
            }
        };

        const recalculateCart = () => {
            cart.value = cart.value.map(i => ({ ...i }));
        };

        const startEditPrice = (index) => {
            editingPriceIndex.value = index;
        };


        const removeFromCart = (index) => {
            cart.value.splice(index, 1);
        };

        const clearCart = () => {
            cart.value = [];
            discount.value = 0;
            taxRate.value = 0;
        };

        // Adjust quantity with bounds checking and stock validation
        const updateQuantity = (index, newQty) => {
            const item = cart.value[index];
            if (!item) return;

            const stock = parseFloat(item.product?.stock_quantity || 0);
            const step = qtyStep(item.unit);

            // Prevent less than minimum step
            if (newQty < step) {
                newQty = step;
            }

            // Prevent exceeding stock
            if (newQty > stock) {
                handleApiError(
                    'Insufficient stock. Available: ' +
                        formatQty(stock, item.unit) + ' ' + item.unit
                );
                return;
            }

            item.quantity = isWeightUnit(item.unit)
                ? parseFloat(newQty.toFixed(3))
                : Math.floor(newQty);
        };

        // Ensure manually entered quantities are valid
        const validateCartQty = (index) => {
            const item = cart.value[index];
            if (!item) return;

            let qty = parseFloat(item.quantity);
            const stock = parseFloat(item.product?.stock_quantity || 0);
            const step = qtyStep(item.unit);

            if (isNaN(qty) || qty < step) {
                qty = step;
            }

            if (qty > stock) {
                handleApiError(
                    'Insufficient stock. Available: ' +
                    formatQty(stock, item.unit) + ' ' + item.unit
                );
                qty = stock;
            }

            item.quantity = isWeightUnit(item.unit)
                ? parseFloat(qty.toFixed(3))
                : Math.floor(qty);
        };

        // Retrieve POS configuration from server
        const fetchPosConfig = async () => {
            try {
                const response = await axios.get('/api/pos/config');

                // Adjust depending on your API structure
                posConfig.value = response.data.config || response.data;
            } catch (error) {
                console.error('Error fetching POS config:', error);
            }
        };

        const printThermalReceipt = (saleData) => {
            lastSale.value = saleData;
            
            // Generate receipt HTML directly
            const date = new Date(saleData.sale_date).toLocaleString('en-IN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            let itemsHTML = '';
            saleData.items.forEach(item => {
                const itemName = (item.product?.name || 'N/A').substring(0, 30);
                const u = (item.product?.unit || 'pcs').toLowerCase();
                const isWeight = ['kg', 'g', 'gm', 'ltr'].includes(u);
                const qtyStr = isWeight && Number(item.quantity) !== parseInt(item.quantity, 10)
                    ? parseFloat(item.quantity).toFixed(2) + ' ' + u
                    : item.quantity + ' ' + u;
                itemsHTML += `
                    <div style="margin: 6px 0; padding-bottom: 4px; border-bottom: 1px dotted #eee;">
                        <div style="font-weight: 500; margin-bottom: 2px; font-size: 11px;">${itemName}</div>
                        <div style="display: flex; justify-content: space-between; margin-left: 10px; font-size: 11px;">
                            <span>Qty: ${qtyStr}</span>
                            <span>₹${parseFloat(item.unit_price).toFixed(2)}</span>
                            <span>₹${parseFloat(item.total).toFixed(2)}</span>
                        </div>
                    </div>
                `;
            });

            const receiptHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Receipt - ${saleData.invoice_number}</title>
                    <style>
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                        body {
                            margin: 0;
                            padding: 5mm;
                            font-family: 'Courier New', monospace;
                            font-size: 12px;
                            width: 80mm;
                            background: white;
                            color: #000;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 10px;
                        }
                        .company-name {
                            font-size: 18px;
                            font-weight: bold;
                            margin-bottom: 5px;
                        }
                        .company-address {
                            font-size: 11px;
                            margin-bottom: 3px;
                        }
                        .company-contact {
                            font-size: 10px;
                            color: #666;
                        }
                        .divider {
                            text-align: center;
                            margin: 8px 0;
                            font-size: 11px;
                            border-top: 1px dashed #000;
                            padding-top: 4px;
                        }
                        .divider-thick {
                            text-align: center;
                            margin: 8px 0;
                            font-size: 11px;
                            border-top: 2px solid #000;
                            padding-top: 4px;
                        }
                        .row {
                            display: flex;
                            justify-content: space-between;
                            margin: 4px 0;
                            font-size: 12px;
                        }
                        .total-row {
                            font-weight: bold;
                            font-size: 14px;
                            border-top: 1px dashed #000;
                            padding-top: 4px;
                            margin-top: 4px;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 15px;
                        }
                        .footer-text {
                            font-size: 10px;
                            margin: 4px 0;
                            color: #666;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="company-name">${ client?.name }</div>
                        <div class="company-address">${ client?.location }</div>
                        <div class="company-contact">${ client?.phone}</div>
                    </div>
                    <div class="divider"></div>
                    <div class="row">
                        <span>Invoice:</span>
                        <span>${saleData.invoice_number}</span>
                    </div>
                    <div class="row">
                        <span>Date:</span>
                        <span>${date}</span>
                    </div>
                    ${saleData.customer ? `<div class="row"><span>Customer:</span><span>${saleData.customer.name}</span></div>` : ''}
                    ${saleData.customer?.gst_number ? `<div class="row"><span>GST:</span><span>${saleData.customer.gst_number}</span></div>` : ''}
                    <div class="divider"></div>
                    ${itemsHTML}
                    <div class="divider"></div>
                    <div class="row">
                        <span>Subtotal:</span>
                        <span>₹${parseFloat(saleData.subtotal).toFixed(2)}</span>
                    </div>
                    ${saleData.discount > 0 ? `<div class="row"><span>Discount:</span><span>-₹${parseFloat(saleData.discount).toFixed(2)}</span></div>` : ''}
                    ${saleData.tax_amount > 0 ? `
                    <div class="row"><span>CGST (${(parseFloat(saleData.tax_rate || 0) / 2).toFixed(1)}%):</span><span>₹${(parseFloat(saleData.tax_amount) / 2).toFixed(2)}</span></div>
                    <div class="row"><span>SGST (${(parseFloat(saleData.tax_rate || 0) / 2).toFixed(1)}%):</span><span>₹${(parseFloat(saleData.tax_amount) / 2).toFixed(2)}</span></div>
                    ` : ''}
                    <div class="row total-row">
                        <span>TOTAL:</span>
                        <span>₹${parseFloat(saleData.total).toFixed(2)}</span>
                    </div>
                    <div class="divider-thick"></div>
                    <div class="row">
                        <span>Payment:</span>
                        <span>${saleData.payment_method.toUpperCase()}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="footer">
                        <div class="footer-text">Thank you for your business!</div>
                        <div class="footer-text">Visit us at hbitpartner.com</div>
                        <div class="footer-text">Your IT Partner</div>
                    </div>
                    <div class="divider-thick"></div>
                    <div style="height: 20mm;"></div>
                </body>
                </html>
            `;
            
            // Open print window and trigger print
            const printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write(receiptHTML);
                printWindow.document.close();
                printWindow.focus();
                
                // Trigger print dialog automatically
                setTimeout(() => {
                    printWindow.print();
                    // Keep window open briefly, then close
                    setTimeout(() => {
                        printWindow.close();
                    }, 500);
                }, 500);
            }
        };

        const toggleCustomerModal = () => {
            showCustomerModal.value = !showCustomerModal.value;
            if (!showCustomerModal.value) {
                showAddCustomerForm.value = false;
                newCustomer.value = { name: '', phone: '', email: '' };
            }
        };

        const selectCustomer = (customer) => {
            selectedCustomer.value = customer;
            showCustomerModal.value = false;
            showAddCustomerForm.value = false;
            newCustomer.value = { name: '', phone: '', email: '' };
        };

        // Removed searchAndAddCustomer - handled by PaginatedDropdown

        const addNewCustomer = async () => {
            if (!newCustomer.value.name || !newCustomer.value.phone) {
                handleApiError('Please enter customer name and phone number');
                return;
            }

            try {
                const response = await axios.post('/api/customers', {
                    name: newCustomer.value.name,
                    phone: newCustomer.value.phone,
                    email: newCustomer.value.email || null
                });

                // 🔥 IMPORTANT: adjust based on your API structure
                const createdCustomer = response.data.customer || response.data.data || response.data;

                // Automatically select the created customer
                selectedCustomer.value = createdCustomer;

                // Close modal
                showCustomerModal.value = false;
                showAddCustomerForm.value = false;

                // Reset form
                newCustomer.value = { name: '', phone: '', email: '' };

                handleApiError('Customer added and selected successfully!');

            } catch (error) {
                const message = error.response?.data?.message || 'Error adding customer';
                handleApiError(message);
            }
        };

        const processSale = async () => {
            if (cart.value.length === 0) return;
            processing.value = true;

            try {
                if (posConfig.value.view_total_editable) {
                    cart.value.forEach((it, i) => validatePrice(i));
                }
                const items = cart.value.map(item => ({
                    product_id: item.id,
                    quantity: item.quantity,
                    unit_price: parseFloat(item.price).toFixed(2),
                    discount: 0
                }));

                // Validate credit limit before processing
                if (paymentMethod.value === 'credit' && selectedCustomer.value) {
                    // Check if customer has sufficient credit
                    const customerResponse = await axios.get(`/api/customers/${selectedCustomer.value.id}`);
                    const customer = customerResponse.data;
                    const availableCredit = parseFloat(customer.credit_limit) - parseFloat(customer.balance);
                    const saleTotal = total.value;

                    if (saleTotal > availableCredit) {
                        handleApiError(`Insufficient credit limit. Available balance: ₹${availableCredit.toFixed(2)}`);
                        processing.value = false;
                        return;
                    }
                }

                const response = await axios.post('/api/pos/sale', {
                    customer_id: selectedCustomer.value?.id || null,
                    items,
                    tax_rate: taxRate.value,
                    discount: discount.value,
                    payment_method: paymentMethod.value
                });

                // Store sale data
                lastSale.value = response.data.sale;
                
                // Clear cart first
                clearCart();
                selectedCustomer.value = null;
                
                // Show success message briefly, then auto-print receipt
                handleApiError('Sale completed successfully! Invoice: ' + response.data.sale.invoice_number + '\n\nPrinting receipt...');
                
                // Automatically trigger thermal receipt print
                setTimeout(() => {
                    printThermalReceipt(response.data.sale);
                }, 300);
            } catch (error) {
                const message = error.response?.data?.message || 'Error processing sale';
                const errors = error.response?.data?.errors;
                if (errors) {
                    handleApiError(message + ': ' + JSON.stringify(errors));
                } else {
                    handleApiError(message);
                }
            } finally {
                processing.value = false;
            }
        };

        onMounted(() => {
            loadInitial();
            setTimeout(() => setupScrollObserver(), 100);
            fetchPosConfig();        // <–– fetch config on mount
        });

        watch([loadMoreTrigger, gridContainer], () => {
            if (loadMoreTrigger.value && gridContainer.value) setupScrollObserver();
        });

        return {
            filteredProducts,
            cart,
            posConfig,
            searchQuery,
            loading,
            hasMore,
            handleScroll,
            gridContainer,
            loadMoreTrigger,
            onSearchInput,
            taxRate,
            discount,
            paymentMethod,
            selectedCustomer,
            showCustomerModal,
            showAddCustomerForm,
            newCustomer,
            processing,
            lastSale,
            showReceipt,
            subtotal,
            taxAmount,
            cgstRate,
            sgstRate,
            cgstAmount,
            sgstAmount,
            total,
            addToCart,
            updateQuantity,
            removeFromCart,
            validateCartQty,
            clearCart,
            formatQty,
            formatCartQty,
            qtyStep,
            isWeightUnit,
            toggleCustomerModal,
            selectCustomer,
            handleCustomerSelect,
            addNewCustomer,
            processSale,
            printThermalReceipt,
            categoryFilter,
            brandFilter,
            client,
            validatePrice,
            recalculateCart,
            editingPriceIndex,
            startEditPrice,
        };
    }
};
</script>

<style scoped>
.pos-container {
    padding: 20px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f5f5f5;
}

.pos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.pos-header h1 {
    margin: 0;
    color: #333;
}

.pos-actions {
    display: flex;
    gap: 10px;
}

.pos-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    flex: 1;
    overflow: hidden;
}

.pos-left {
    background: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.pos-right {
    background: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

.product-search {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: nowrap;
}

.search-input {
    flex: 1;
    min-width: 300px;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.filter-dropdown {
    width: 220px;
}
.products-grid-wrapper {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
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

.product-card {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.product-card:hover {
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.product-card.low-stock {
    border-color: #e74c3c;
    position: relative;
}

.product-card.low-stock:hover .low-stock-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateY(-5px);
}

.low-stock-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-10px);
    background: #e74c3c;
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    margin-bottom: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.low-stock-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #e74c3c;
}

.product-info h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #333;
}

.product-sku {
    font-size: 12px;
    color: #666;
    margin: 4px 0;
}

.product-price {
    font-size: 18px;
    font-weight: bold;
    color: #667eea;
    margin: 8px 0;
}

.product-stock {
    font-size: 12px;
    color: #999;
}

.cart-section {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    margin-bottom: 15px;
}

.cart-section h2 {
    margin-top: 0;
    margin-bottom: 15px;
}

.empty-cart {
    text-align: center;
    padding: 40px;
    color: #999;
}

.cart-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
}

.item-info {
width: 400px;
}

.item-info h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
}

.item-info p {
    margin: 0;
    font-size: 12px;
    color: #666;
}

.price-text {
    cursor: pointer;
}

.item-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-qty {
    width: 30px;
    height: 30px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
}

.qty,
.qty-input {
    display: inline-block;
    width: 50px; 
    padding: 7px 0px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.btn-remove {
    width: 30px;
    height: 30px;
    border: 1px solid #e74c3c;
    background: #e74c3c;
    color: white;
    border-radius: 4px;
    cursor: pointer;
}

.cart-summary {
    flex-shrink: 0;
    border-top: 2px solid #e0e0e0;
    padding-top: 15px;
    margin-bottom: 15px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    align-items: center;
}

.summary-row.total {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
    border-top: 2px solid #e0e0e0;
    padding-top: 10px;
    margin-top: 10px;
}

.input-small {
    width: 100px;
    padding: 6px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-large {
    flex-shrink: 0;
    width: 100%;
    padding: 15px;
    font-size: 18px;
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
    max-height: 80vh;
    overflow-y: auto;
}

.customer-list {
    margin-top: 20px;
}

.customer-item {
    padding: 12px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.customer-item:hover {
    background: #f8f9fa;
    border-color: #667eea;
}

.customer-item strong {
    display: block;
    margin-bottom: 4px;
}

.customer-item span {
    font-size: 12px;
    color: #666;
}

.add-customer-form {
    margin-top: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.add-customer-form h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.add-customer-form .form-group {
    margin-bottom: 15px;
}

.add-customer-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #555;
}

.add-customer-form input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.add-customer-form .form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.no-customer-found {
    text-align: center;
    padding: 20px;
    color: #666;
}

.no-customer-found p {
    margin-bottom: 15px;
}

.customer-item.walk-in {
    margin-bottom: 15px;
    padding: 12px;
    border: 2px solid #667eea;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    background: #f0f4ff;
}

.customer-item.walk-in:hover {
    background: #e7f0ff;
    border-color: #764ba2;
}

.add-customer-option {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
    text-align: center;
}

.btn-outline {
    background: transparent;
    border: 2px solid #667eea;
    color: #667eea;
}

.btn-outline:hover {
    background: #667eea;
    color: white;
}
</style>
