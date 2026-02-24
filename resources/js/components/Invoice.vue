<template>
    <div class="invoice-container">
        <div class="invoice-actions">
            <button @click="goBack" class="btn btn-secondary">Back</button>
            <button @click="printThermalReceipt" class="btn btn-primary">Print Thermal Receipt</button>
            <button @click="printInvoice" class="btn btn-secondary">Print Invoice</button>
        </div>

        <div id="invoice-content" class="invoice-content">
            <div class="invoice-header">
                <div class="company-info">
                    <h1>{{ client?.name || '' }}</h1>
                    <p>{{ client?.location || ''  }}</p>
                    <p>{{ client?.pin || ''  }}</p>
                    <p>Phone: {{ client?.phone || '' }}</p>
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
                    <p v-if="sale?.customer?.gst_number">GST: {{ sale.customer.gst_number }}</p>
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
                        <tr v-for="item in (sale?.items || []).filter(i => i != null && i.id != null)" :key="item.id">
                            <td>{{ item.product?.name }}</td>
                            <td>{{ formatItemQty(item.quantity, item.product?.unit) }} {{ item.product?.unit || 'pcs' }}</td>
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
                    <template v-if="sale?.tax_amount > 0">
                        <div class="summary-row">
                            <span>CGST ({{ ((sale?.tax_rate || 0) / 2).toFixed(1) }}%):</span>
                            <span>₹{{ ((sale?.tax_amount || 0) / 2).toFixed(2) }}</span>
                        </div>
                        <div class="summary-row">
                            <span>SGST ({{ ((sale?.tax_rate || 0) / 2).toFixed(1) }}%):</span>
                            <span>₹{{ ((sale?.tax_amount || 0) / 2).toFixed(2) }}</span>
                        </div>
                    </template>
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
import { useClientInfo } from '@/composables/useClientInfo.js'

export default {
    name: 'Invoice',
    setup() {
        const route = useRoute();
        const router = useRouter();
        const sale = ref(null);
        const client = useClientInfo();
        
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

        const formatItemQty = (qty, unit) => {
            if (qty === null || qty === undefined) return '0';
            const n = parseFloat(qty);
            const u = (unit || 'pcs').toLowerCase();
            const isWeight = ['kg', 'g', 'gm', 'ltr'].includes(u);
            if (isWeight) return Number(n) === parseInt(n, 10) ? n : parseFloat(n).toFixed(2);
            return parseInt(n, 10);
        };

        const printThermalReceipt = () => {
            if (!sale.value) return;
            
            const printWindow = window.open('', '_blank');
            const receiptHTML = generateThermalReceiptHTML(sale.value);
            
            if (printWindow) {
                printWindow.document.write(receiptHTML);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            }
        };

        const generateThermalReceiptHTML = (saleData) => {
            const date = new Date(saleData.sale_date).toLocaleString('en-IN');
            let itemsHTML = '';
            saleData.items.forEach(item => {
                const u = (item.product?.unit || 'pcs').toLowerCase();
                const isWeight = ['kg', 'g', 'gm', 'ltr'].includes(u);
                const qtyStr = isWeight && Number(item.quantity) !== parseInt(item.quantity, 10)
                    ? parseFloat(item.quantity).toFixed(2) + ' ' + u
                    : item.quantity + ' ' + u;
                itemsHTML += `
                    <div style="margin: 6px 0;">
                        <div style="font-weight: 500; margin-bottom: 2px;">${item.product?.name || 'N/A'}</div>
                        <div style="display: flex; justify-content: space-between; margin-left: 10px; font-size: 11px;">
                            <span>Qty: ${qtyStr}</span>
                            <span>₹${item.unit_price}</span>
                            <span>₹${item.total}</span>
                        </div>
                    </div>
                `;
            });

            return `
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
                        }
                        .header { text-align: center; margin-bottom: 10px; }
                        .company-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
                        .divider { text-align: center; margin: 8px 0; border-top: 1px dashed #000; }
                        .row { display: flex; justify-content: space-between; margin: 4px 0; }
                        .total-row { font-weight: bold; font-size: 14px; border-top: 1px dashed #000; padding-top: 4px; }
                        .footer { text-align: center; margin-top: 15px; font-size: 10px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="company-name">${ client.name }</div>
                        <div class="company-name">${ client.location }</div>
                        <div style="font-size: 11px;">${ client?.phone}</div>
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
                        <span>₹${saleData.subtotal}</span>
                    </div>
                    ${saleData.discount > 0 ? `<div class="row"><span>Discount:</span><span>-₹${saleData.discount}</span></div>` : ''}
                    ${saleData.tax_amount > 0 ? `
                    <div class="row"><span>CGST (${(parseFloat(saleData.tax_rate || 0) / 2).toFixed(1)}%):</span><span>₹${(parseFloat(saleData.tax_amount) / 2).toFixed(2)}</span></div>
                    <div class="row"><span>SGST (${(parseFloat(saleData.tax_rate || 0) / 2).toFixed(1)}%):</span><span>₹${(parseFloat(saleData.tax_amount) / 2).toFixed(2)}</span></div>
                    ` : ''}
                    <div class="row total-row">
                        <span>TOTAL:</span>
                        <span>₹${saleData.total}</span>
                    </div>
                    <div class="divider" style="border-top: 2px solid #000;"></div>
                    <div class="row">
                        <span>Payment:</span>
                        <span>${saleData.payment_method.toUpperCase()}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="footer">
                        <div>Thank you for your business!</div>
                        <div>Visit us at hbitpartner.com</div>
                        <div>Your IT Partner</div>
                    </div>
                </body>
                </html>
            `;
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
            formatItemQty,
            printInvoice,
            printThermalReceipt,
            goBack,
            client
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
