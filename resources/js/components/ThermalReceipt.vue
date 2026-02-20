<template>
    <div id="thermal-receipt" class="thermal-receipt" v-if="sale">
        <!-- Company Header -->
        <div class="receipt-header">
            <div class="company-name">HB POS System</div>
            <div class="company-address">hbitpartner.com</div>
            <div class="company-contact">Your IT Partner</div>
        </div>

        <div class="receipt-divider">--------------------------------</div>

        <!-- Invoice Info -->
        <div class="receipt-section">
            <div class="receipt-row">
                <span class="label">Invoice:</span>
                <span class="value">{{ sale.invoice_number }}</span>
            </div>
            <div class="receipt-row">
                <span class="label">Date:</span>
                <span class="value">{{ formatReceiptDate(sale.sale_date) }}</span>
            </div>
            <div class="receipt-row" v-if="sale.customer">
                <span class="label">Customer:</span>
                <span class="value">{{ sale.customer.name }}</span>
            </div>
            <div class="receipt-row" v-if="sale.customer?.gst_number">
                <span class="label">GST:</span>
                <span class="value">{{ sale.customer.gst_number }}</span>
            </div>
        </div>

        <div class="receipt-divider">--------------------------------</div>

        <!-- Items -->
        <div class="receipt-section">
            <div class="receipt-row header-row">
                <span class="item-name">Item</span>
                <span class="item-qty">Qty</span>
                <span class="item-price">Price</span>
                <span class="item-total">Total</span>
            </div>
            <div class="receipt-divider">--------------------------------</div>
            
            <div v-for="item in (sale.items || []).filter(i => i != null && i.id != null)" :key="item.id" class="receipt-item">
                <div class="item-name">{{ truncateText(item.product?.name || 'N/A', 20) }}</div>
                <div class="item-row">
                    <span class="item-qty">{{ item.quantity }}</span>
                    <span class="item-price">₹{{ item.unit_price }}</span>
                    <span class="item-total">₹{{ item.total }}</span>
                </div>
                <div v-if="item.discount > 0" class="item-discount">
                    Disc: -₹{{ item.discount }}
                </div>
            </div>
        </div>

        <div class="receipt-divider">--------------------------------</div>

        <!-- Totals -->
        <div class="receipt-section">
            <div class="receipt-row">
                <span class="label">Subtotal:</span>
                <span class="value">₹{{ sale.subtotal }}</span>
            </div>
            <div v-if="sale.discount > 0" class="receipt-row">
                <span class="label">Discount:</span>
                <span class="value">-₹{{ sale.discount }}</span>
            </div>
            <template v-if="sale.tax_amount > 0">
                <div class="receipt-row">
                    <span class="label">CGST ({{ ((sale.tax_rate || 0) / 2).toFixed(1) }}%):</span>
                    <span class="value">₹{{ ((sale.tax_amount || 0) / 2).toFixed(2) }}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">SGST ({{ ((sale.tax_rate || 0) / 2).toFixed(1) }}%):</span>
                    <span class="value">₹{{ ((sale.tax_amount || 0) / 2).toFixed(2) }}</span>
                </div>
            </template>
            <div class="receipt-row total-row">
                <span class="label">TOTAL:</span>
                <span class="value">₹{{ sale.total }}</span>
            </div>
        </div>

        <div class="receipt-divider">================================</div>

        <!-- Payment Info -->
        <div class="receipt-section">
            <div class="receipt-row">
                <span class="label">Payment:</span>
                <span class="value">{{ sale.payment_method.toUpperCase() }}</span>
            </div>
        </div>

        <div class="receipt-divider">--------------------------------</div>

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="footer-text">Thank you for your business!</div>
            <div class="footer-text">Visit us at hbitpartner.com</div>
        </div>

        <div class="receipt-divider">================================</div>
        <div class="receipt-spacer"></div>
    </div>
</template>

<script>
export default {
    name: 'ThermalReceipt',
    props: {
        sale: {
            type: Object,
            required: true
        }
    },
    methods: {
        formatReceiptDate(date) {
            if (!date) return '';
            const d = new Date(date);
            return d.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        truncateText(text, maxLength) {
            if (!text) return '';
            return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
        }
    }
};
</script>

<style scoped>
.thermal-receipt {
    width: 80mm;
    max-width: 80mm;
    margin: 0 auto;
    padding: 10mm 5mm;
    background: white;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
}

.receipt-header {
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

.receipt-divider {
    text-align: center;
    margin: 8px 0;
    font-size: 11px;
    color: #333;
}

.receipt-section {
    margin: 8px 0;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    margin: 4px 0;
    font-size: 12px;
}

.receipt-row.header-row {
    font-weight: bold;
    border-bottom: 1px dashed #ccc;
    padding-bottom: 4px;
    margin-bottom: 4px;
}

.receipt-row.total-row {
    font-weight: bold;
    font-size: 14px;
    border-top: 1px dashed #ccc;
    padding-top: 4px;
    margin-top: 4px;
}

.label {
    flex: 1;
    text-align: left;
}

.value {
    flex: 1;
    text-align: right;
    font-weight: 500;
}

.receipt-item {
    margin: 6px 0;
    padding-bottom: 4px;
    border-bottom: 1px dotted #eee;
}

.item-name {
    font-weight: 500;
    margin-bottom: 2px;
    font-size: 11px;
}

.item-row {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    margin-left: 10px;
}

.item-discount {
    font-size: 10px;
    color: #666;
    margin-left: 10px;
    margin-top: 2px;
}

.item-qty,
.item-price,
.item-total {
    flex: 1;
    text-align: right;
}

.item-qty {
    text-align: left;
}

.receipt-footer {
    text-align: center;
    margin-top: 15px;
}

.footer-text {
    font-size: 10px;
    margin: 4px 0;
    color: #666;
}

.receipt-spacer {
    height: 20mm;
}

/* Print Styles */
@media print {
    @page {
        size: 80mm auto;
        margin: 0;
    }

    body {
        margin: 0;
        padding: 0;
    }

    .thermal-receipt {
        width: 80mm;
        max-width: 80mm;
        margin: 0;
        padding: 5mm;
        box-shadow: none;
    }

    .receipt-divider {
        border-top: 1px dashed #000;
        border-bottom: none;
    }
}
</style>
