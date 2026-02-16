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
                        @input="searchProducts"
                    />
                </div>

                <div class="products-grid">
                    <div
                        v-for="product in products"
                        :key="product.id"
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
                                <p>₹{{ item.price }} × {{ formatCartQty(item.quantity, item.unit) }} {{ item.unit || 'pcs' }}</p>
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

        <!-- Thermal Receipt (Hidden, for printing) -->
        <div v-if="showReceipt && lastSale" style="position: absolute; left: -9999px;">
            <div id="thermal-receipt-styles">
                <style>
                    .thermal-receipt {
                        width: 80mm;
                        max-width: 80mm;
                        margin: 0 auto;
                        padding: 5mm;
                        background: white;
                        font-family: 'Courier New', monospace;
                        font-size: 12px;
                        line-height: 1.4;
                        color: #000;
                    }
                    .receipt-header { text-align: center; margin-bottom: 10px; }
                    .company-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
                    .company-address { font-size: 11px; margin-bottom: 3px; }
                    .company-contact { font-size: 10px; color: #666; }
                    .receipt-divider { text-align: center; margin: 8px 0; font-size: 11px; }
                    .receipt-section { margin: 8px 0; }
                    .receipt-row { display: flex; justify-content: space-between; margin: 4px 0; font-size: 12px; }
                    .receipt-row.total-row { font-weight: bold; font-size: 14px; border-top: 1px dashed #ccc; padding-top: 4px; }
                    .label { flex: 1; text-align: left; }
                    .value { flex: 1; text-align: right; font-weight: 500; }
                    .receipt-item { margin: 6px 0; padding-bottom: 4px; }
                    .item-name { font-weight: 500; margin-bottom: 2px; font-size: 11px; }
                    .item-row { display: flex; justify-content: space-between; font-size: 11px; margin-left: 10px; }
                    .item-discount { font-size: 10px; color: #666; margin-left: 10px; }
                    .item-qty, .item-price, .item-total { flex: 1; text-align: right; }
                    .item-qty { text-align: left; }
                    .receipt-footer { text-align: center; margin-top: 15px; }
                    .footer-text { font-size: 10px; margin: 4px 0; color: #666; }
                </style>
            </div>
            <div id="thermal-receipt-print" class="thermal-receipt">
                <div class="receipt-header">
                    <div class="company-name">HB POS System</div>
                    <div class="company-address">hbitpartner.com</div>
                    <div class="company-contact">Your IT Partner</div>
                </div>
                <div class="receipt-divider">--------------------------------</div>
                <div class="receipt-section">
                    <div class="receipt-row">
                        <span class="label">Invoice:</span>
                        <span class="value">{{ lastSale.invoice_number }}</span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Date:</span>
                        <span class="value">{{ new Date(lastSale.sale_date).toLocaleString('en-IN') }}</span>
                    </div>
                    <div class="receipt-row" v-if="lastSale.customer">
                        <span class="label">Customer:</span>
                        <span class="value">{{ lastSale.customer.name }}</span>
                    </div>
                </div>
                <div class="receipt-divider">--------------------------------</div>
                <div class="receipt-section">
                    <div v-for="item in lastSale.items" :key="item.id" class="receipt-item">
                        <div class="item-name">{{ item.product?.name || 'N/A' }}</div>
                        <div class="item-row">
                            <span class="item-qty">{{ formatCartQty(item.quantity, item.product?.unit) }} {{ item.product?.unit || 'pcs' }}</span>
                            <span class="item-price">₹{{ item.unit_price }}</span>
                            <span class="item-total">₹{{ item.total }}</span>
                        </div>
                    </div>
                </div>
                <div class="receipt-divider">--------------------------------</div>
                <div class="receipt-section">
                    <div class="receipt-row">
                        <span class="label">Subtotal:</span>
                        <span class="value">₹{{ lastSale.subtotal }}</span>
                    </div>
                    <div v-if="lastSale.discount > 0" class="receipt-row">
                        <span class="label">Discount:</span>
                        <span class="value">-₹{{ lastSale.discount }}</span>
                    </div>
                    <div v-if="lastSale.tax_amount > 0" class="receipt-row">
                        <span class="label">Tax:</span>
                        <span class="value">₹{{ lastSale.tax_amount }}</span>
                    </div>
                    <div class="receipt-row total-row">
                        <span class="label">TOTAL:</span>
                        <span class="value">₹{{ lastSale.total }}</span>
                    </div>
                </div>
                <div class="receipt-divider">================================</div>
                <div class="receipt-section">
                    <div class="receipt-row">
                        <span class="label">Payment:</span>
                        <span class="value">{{ lastSale.payment_method.toUpperCase() }}</span>
                    </div>
                </div>
                <div class="receipt-divider">--------------------------------</div>
                <div class="receipt-footer">
                    <div class="footer-text">Thank you for your business!</div>
                    <div class="footer-text">Visit us at hbitpartner.com</div>
                </div>
            </div>
        </div>

        <!-- Customer Modal -->
        <div v-if="showCustomerModal" class="modal-overlay" @click="toggleCustomerModal">
            <div class="modal-content" @click.stop>
                <h2>Select Customer</h2>
                <input v-model="customerSearch" type="text" placeholder="Search customers..." class="search-input" @keyup.enter="searchAndAddCustomer" />
                
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

                <div v-else class="customer-list">
                    <div class="customer-item" @click="selectCustomer(null)">
                        <strong>Walk-in Customer</strong>
                    </div>
                    <div
                        v-for="customer in filteredCustomers"
                        :key="customer.id"
                        class="customer-item"
                        @click="selectCustomer(customer)"
                    >
                        <strong>{{ customer.name }}</strong>
                        <span>{{ customer.phone || customer.email }}</span>
                    </div>
                    <div v-if="customerSearch && filteredCustomers.length === 0" class="no-customer-found">
                        <p>No customer found</p>
                        <button @click="showAddCustomerForm = true" class="btn btn-primary">Add New Customer</button>
                    </div>
                    <div v-else-if="!customerSearch" class="add-customer-option">
                        <button @click="showAddCustomerForm = true" class="btn btn-outline">+ Add New Customer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'POS',
    setup() {
        const products = ref([]);
        const customers = ref([]);
        const cart = ref([]);
        const searchQuery = ref('');
        const customerSearch = ref('');
        const taxRate = ref(0);
        const discount = ref(0);
        const paymentMethod = ref('cash');
        const selectedCustomer = ref(null);
        const showCustomerModal = ref(false);
        const showAddCustomerForm = ref(false);
        const processing = ref(false);
        const lastSale = ref(null);
        const showReceipt = ref(false);
        const newCustomer = ref({
            name: '',
            phone: '',
            email: ''
        });

        const subtotal = computed(() => {
            return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        });

        const taxAmount = computed(() => {
            return (subtotal.value - discount.value) * (taxRate.value / 100);
        });

        const total = computed(() => {
            return subtotal.value - discount.value + taxAmount.value;
        });

        const filteredCustomers = computed(() => {
            if (!customerSearch.value) return customers.value;
            const search = customerSearch.value.toLowerCase();
            return customers.value.filter(c => 
                c.name.toLowerCase().includes(search) ||
                (c.email && c.email.toLowerCase().includes(search)) ||
                (c.phone && c.phone.includes(search))
            );
        });

        const loadProducts = async () => {
            try {
                const response = await axios.get('/api/pos/products');
                products.value = response.data;
            } catch (error) {
                console.error('Error loading products:', error);
            }
        };

        const loadCustomers = async () => {
            try {
                const response = await axios.get('/api/customers', { params: { per_page: 100 } });
                customers.value = response.data.data || response.data;
            } catch (error) {
                console.error('Error loading customers:', error);
            }
        };

        const searchProducts = async () => {
            try {
                const response = await axios.get('/api/pos/products', {
                    params: { search: searchQuery.value }
                });
                products.value = response.data;
            } catch (error) {
                console.error('Error searching products:', error);
            }
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
                alert('Product out of stock');
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
                    alert('Insufficient stock. Available: ' + formatQty(stock, unit) + ' ' + unit);
                }
            } else {
                const startQty = isWeightUnit(unit) ? 0.5 : 1;
                cart.value.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.selling_price),
                    quantity: startQty,
                    unit: unit,
                    product: product
                });
            }
        };

        const validateCartQty = (index) => {
            const item = cart.value[index];
            const stock = parseFloat(item.product.stock_quantity);
            let qty = parseFloat(item.quantity);
            if (isNaN(qty) || qty < 0) qty = qtyStep(item.unit);
            if (qty > stock) qty = stock;
            item.quantity = isWeightUnit(item.unit) ? parseFloat(qty.toFixed(3)) : Math.floor(qty);
        };

        const updateQuantity = (index, newQuantity) => {
            const item = cart.value[index];
            const step = qtyStep(item.unit);
            if (newQuantity < step) {
                removeFromCart(index);
                return;
            }
            const stock = parseFloat(item.product.stock_quantity);
            if (newQuantity > stock) {
                alert('Insufficient stock. Available: ' + formatQty(stock, item.unit) + ' ' + item.unit);
                return;
            }
            item.quantity = isWeightUnit(item.unit) ? parseFloat(Number(newQuantity).toFixed(3)) : Math.floor(newQuantity);
        };

        const removeFromCart = (index) => {
            cart.value.splice(index, 1);
        };

        const clearCart = () => {
            cart.value = [];
            discount.value = 0;
            taxRate.value = 0;
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
                        <div class="company-name">HB POS System</div>
                        <div class="company-address">hbitpartner.com</div>
                        <div class="company-contact">Your IT Partner</div>
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
                    <div class="divider"></div>
                    ${itemsHTML}
                    <div class="divider"></div>
                    <div class="row">
                        <span>Subtotal:</span>
                        <span>₹${parseFloat(saleData.subtotal).toFixed(2)}</span>
                    </div>
                    ${saleData.discount > 0 ? `<div class="row"><span>Discount:</span><span>-₹${parseFloat(saleData.discount).toFixed(2)}</span></div>` : ''}
                    ${saleData.tax_amount > 0 ? `<div class="row"><span>Tax (${saleData.tax_rate}%):</span><span>₹${parseFloat(saleData.tax_amount).toFixed(2)}</span></div>` : ''}
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
                customerSearch.value = '';
                newCustomer.value = { name: '', phone: '', email: '' };
            }
        };

        const selectCustomer = (customer) => {
            selectedCustomer.value = customer;
            showCustomerModal.value = false;
            showAddCustomerForm.value = false;
            customerSearch.value = '';
            newCustomer.value = { name: '', phone: '', email: '' };
        };

        const searchAndAddCustomer = () => {
            if (customerSearch.value && filteredCustomers.value.length === 0) {
                // If search has value but no results, show add form
                newCustomer.value.name = customerSearch.value;
                showAddCustomerForm.value = true;
            }
        };

        const addNewCustomer = async () => {
            if (!newCustomer.value.name || !newCustomer.value.phone) {
                alert('Please enter customer name and phone number');
                return;
            }

            try {
                const response = await axios.post('/api/customers', {
                    name: newCustomer.value.name,
                    phone: newCustomer.value.phone,
                    email: newCustomer.value.email || null
                });

                const createdCustomer = response.data;
                customers.value.push(createdCustomer);
                selectCustomer(createdCustomer);
                alert('Customer added successfully!');
            } catch (error) {
                const message = error.response?.data?.message || 'Error adding customer';
                const errors = error.response?.data?.errors;
                if (errors) {
                    alert(message + ': ' + JSON.stringify(errors));
                } else {
                    alert(message);
                }
            }
        };

        const processSale = async () => {
            if (cart.value.length === 0) return;

            processing.value = true;

            try {
                const items = cart.value.map(item => ({
                    product_id: item.id,
                    quantity: item.quantity,
                    unit_price: item.price,
                    discount: 0
                }));

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
                alert('Sale completed successfully! Invoice: ' + response.data.sale.invoice_number + '\n\nPrinting receipt...');
                
                // Automatically trigger thermal receipt print
                setTimeout(() => {
                    printThermalReceipt(response.data.sale);
                }, 300);
            } catch (error) {
                const message = error.response?.data?.message || 'Error processing sale';
                const errors = error.response?.data?.errors;
                if (errors) {
                    alert(message + ': ' + JSON.stringify(errors));
                } else {
                    alert(message);
                }
            } finally {
                processing.value = false;
            }
        };

        onMounted(() => {
            loadProducts();
            loadCustomers();
        });

        return {
            products,
            customers,
            cart,
            searchQuery,
            customerSearch,
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
            total,
            filteredCustomers,
            searchProducts,
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
            searchAndAddCustomer,
            addNewCustomer,
            processSale,
            printThermalReceipt,
            lastSale,
            showReceipt
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
    overflow-y: auto;
}

.pos-right {
    background: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
}

.product-search {
    margin-bottom: 20px;
}

.search-input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
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
    overflow-y: auto;
    margin-bottom: 20px;
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

.item-info h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
}

.item-info p {
    margin: 0;
    font-size: 12px;
    color: #666;
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
    min-width: 44px;
    width: 50px;
    text-align: center;
    padding: 4px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
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
    border-top: 2px solid #e0e0e0;
    padding-top: 15px;
    margin-bottom: 20px;
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
