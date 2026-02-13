<template>
    <div class="invoice-container">
        <div class="invoice-actions">
            <button @click="goBack" class="btn btn-secondary">Back</button>
            <button @click="printInvoice" class="btn btn-primary">Print</button>
        </div>

        <div id="invoice-content" class="invoice-content">
            <div class="invoice-header">
                <div class="company-info">
                    <h1>POS & Inventory System</h1>
                    <p>123 Business Street</p>
                    <p>City, State 12345</p>
                    <p>Phone: (123) 456-7890</p>
                </div>
                <div class="invoice-info">
                    <h2>INVOICE</h2>
                    <p><strong>Invoice #:</strong> {{ sale?.invoice_number }}</p>
                    <p><strong>Date:</strong> {{ formatDate(sale?.sale_date) }}</p>
                </div>
            </div>

            <div class="invoice-body">
                <div class="customer-info">
                    <h3>Bill To:</h3>
                    <p><strong>{{ sale?.customer?.name || 'Walk-in Customer' }}</strong></p>
                    <p v-if="sale?.customer?.email">{{ sale.customer.email }}</p>
                    <p v-if="sale?.customer?.phone">{{ sale.customer.phone }}</p>
                    <p v-if="sale?.customer?.address">{{ sale.customer.address }}</p>
                </div>

                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Discount</th>
                            <th>Tax</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in sale?.items" :key="item.id">
                            <td>{{ item.product?.name }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>₹{{ item.unit_price }}</td>
                            <td>₹{{ item.discount }}</td>
                            <td>₹{{ item.tax_amount }}</td>
                            <td>₹{{ item.total }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="invoice-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>₹{{ sale?.subtotal }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax ({{ sale?.tax_rate }}%):</span>
                        <span>₹{{ sale?.tax_amount }}</span>
                    </div>
                    <div class="summary-row" v-if="sale?.discount > 0">
                        <span>Discount:</span>
                        <span>-₹{{ sale?.discount }}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>₹{{ sale?.total }}</span>
                    </div>
                </div>

                <div class="payment-info">
                    <p><strong>Payment Method:</strong> {{ sale?.payment_method }}</p>
                    <p v-if="sale?.notes"><strong>Notes:</strong> {{ sale.notes }}</p>
                </div>
            </div>

            <div class="invoice-footer">
                <p>Thank you for your business!</p>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

export default {
    name: 'Invoice',
    setup() {
        const route = useRoute();
        const router = useRouter();
        const sale = ref(null);

        const loadInvoice = async () => {
            try {
                const response = await axios.get(`/api/sales/${route.params.id}/invoice`);
                sale.value = response.data;
            } catch (error) {
                console.error('Error loading invoice:', error);
                alert('Error loading invoice');
            }
        };

        const formatDate = (date) => {
            if (!date) return '';
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        const printInvoice = () => {
            const printContent = document.getElementById('invoice-content').innerHTML;
            const originalContent = document.body.innerHTML;
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            window.location.reload();
        };

        const goBack = () => {
            router.push('/sales');
        };

        onMounted(() => {
            loadInvoice();
        });

        return {
            sale,
            formatDate,
            printInvoice,
            goBack
        };
    }
};
</script>

<style scoped>
.invoice-container {
    padding: 20px;
    max-width: 900px;
    margin: 0 auto;
}

.invoice-actions {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.invoice-content {
    background: white;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.company-info h1 {
    margin: 0 0 10px 0;
    color: #333;
}

.company-info p {
    margin: 5px 0;
    color: #666;
}

.invoice-info h2 {
    margin: 0 0 10px 0;
    color: #667eea;
    font-size: 32px;
}

.invoice-info p {
    margin: 5px 0;
    color: #666;
}

.invoice-body {
    margin-bottom: 30px;
}

.customer-info {
    margin-bottom: 20px;
}

.customer-info h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.customer-info p {
    margin: 5px 0;
    color: #666;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.invoice-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    border-bottom: 2px solid #e0e0e0;
}

.invoice-table td {
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
}

.invoice-summary {
    margin-left: auto;
    width: 300px;
    margin-top: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
}

.summary-row.total {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
    border-top: 2px solid #e0e0e0;
    padding-top: 10px;
    margin-top: 10px;
}

.payment-info {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.payment-info p {
    margin: 5px 0;
    color: #666;
}

.invoice-footer {
    text-align: center;
    padding-top: 20px;
    border-top: 2px solid #e0e0e0;
    color: #666;
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

@media print {
    .invoice-actions {
        display: none;
    }
    .invoice-content {
        box-shadow: none;
    }
}
</style>
