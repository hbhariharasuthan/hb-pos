<template>
    <div class="purchase-bill-container">
        <div class="bill-actions">
            <button @click="goBack" class="btn btn-secondary">Back</button>
            <button @click="printBill" class="btn btn-primary">Print Bill</button>
        </div>

        <div id="purchase-bill-content" class="bill-content">
            <div class="bill-header">
                <div class="company-info">
                    <h1>POS & Inventory System</h1>
                    <p>123 Business Street</p>
                    <p>City, State 12345</p>
                </div>
                <div class="bill-info">
                    <h2>PURCHASE BILL</h2>
                    <p><strong>Bill #:</strong> {{ purchase?.bill_number }}</p>
                    <p><strong>Date:</strong> {{ formatDate(purchase?.purchase_date) }}</p>
                </div>
            </div>

            <div class="bill-body">
                <div class="supplier-info">
                    <h3>Supplier Details</h3>
                    <p><strong>{{ purchase?.supplier?.name || '—' }}</strong></p>
                    <p v-if="purchase?.supplier?.phone">{{ purchase.supplier.phone }}</p>
                    <p v-if="purchase?.supplier?.email">{{ purchase.supplier.email }}</p>
                    <p v-if="purchase?.supplier?.gst_number">GST: {{ purchase.supplier.gst_number }}</p>
                    <p v-if="purchase?.supplier?.address">{{ purchase.supplier.address }}</p>
                    <p v-if="purchase?.supplier?.city">{{ purchase.supplier.city }}{{ purchase?.supplier?.state ? ', ' + purchase.supplier.state : '' }}</p>
                </div>

                <table class="bill-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Unit Cost</th>
                            <th>Discount</th>
                            <th>Tax</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in (purchase?.items || []).filter(i => i != null && i.id != null)" :key="item.id">
                            <td>{{ item.product?.name }}</td>
                            <td>{{ formatQty(item.quantity, item.product?.unit) }} {{ item.product?.unit || 'pcs' }}</td>
                            <td>₹{{ item.unit_cost }}</td>
                            <td>₹{{ item.discount }}</td>
                            <td>₹{{ item.tax_amount }}</td>
                            <td>₹{{ item.total }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="bill-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>₹{{ purchase?.subtotal }}</span>
                    </div>
                    <template v-if="purchase?.tax_amount > 0">
                        <div class="summary-row">
                            <span>CGST ({{ ((purchase?.tax_rate || 0) / 2).toFixed(1) }}%):</span>
                            <span>₹{{ ((purchase?.tax_amount || 0) / 2).toFixed(2) }}</span>
                        </div>
                        <div class="summary-row">
                            <span>SGST ({{ ((purchase?.tax_rate || 0) / 2).toFixed(1) }}%):</span>
                            <span>₹{{ ((purchase?.tax_amount || 0) / 2).toFixed(2) }}</span>
                        </div>
                    </template>
                    <div class="summary-row" v-if="purchase?.discount > 0">
                        <span>Discount:</span>
                        <span>-₹{{ purchase?.discount }}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>₹{{ purchase?.total }}</span>
                    </div>
                </div>

                <div v-if="purchase?.notes" class="bill-notes">
                    <p><strong>Notes:</strong> {{ purchase.notes }}</p>
                </div>
            </div>

            <div class="bill-footer">
                <p>Thank you!</p>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

export default {
    name: 'PurchaseBill',
    setup() {
        const route = useRoute();
        const router = useRouter();
        const purchase = ref(null);

        const loadBill = async () => {
            try {
                const r = await axios.get(`/api/purchases/${route.params.id}/bill`);
                purchase.value = r.data;
            } catch (e) {
                console.error(e);
                alert('Error loading purchase bill');
            }
        };

        const formatDate = (d) => {
            if (!d) return '';
            return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        };

        const formatQty = (qty, unit) => {
            if (qty == null) return '0';
            const n = parseFloat(qty);
            const u = (unit || 'pcs').toLowerCase();
            const isWeight = ['kg', 'g', 'gm', 'ltr'].includes(u);
            if (isWeight) return Number(n) === parseInt(n, 10) ? n : parseFloat(n).toFixed(2);
            return parseInt(n, 10);
        };

        const printBill = () => {
            const content = document.getElementById('purchase-bill-content').innerHTML;
            const win = window.open('', '_blank');
            if (win) {
                win.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head><title>Purchase Bill - ${purchase.value?.bill_number}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .bill-header { display: flex; justify-content: space-between; margin-bottom: 24px; border-bottom: 2px solid #333; padding-bottom: 16px; }
                        .bill-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .bill-table th, .bill-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .bill-table th { background: #f5f5f5; }
                        .bill-summary { margin-top: 20px; max-width: 320px; margin-left: auto; }
                        .summary-row { display: flex; justify-content: space-between; margin: 6px 0; }
                        .summary-row.total { font-weight: bold; font-size: 18px; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }
                        .bill-footer { margin-top: 30px; text-align: center; color: #666; }
                    </style>
                    </head>
                    <body>${content}</body>
                    </html>
                `);
                win.document.close();
                win.focus();
                setTimeout(() => { win.print(); win.close(); }, 250);
            }
        };

        const goBack = () => router.push('/purchases');

        onMounted(() => loadBill());

        return {
            purchase,
            formatDate,
            formatQty,
            printBill,
            goBack
        };
    }
};
</script>

<style scoped>
.purchase-bill-container { padding: 20px; max-width: 900px; margin: 0 auto; }
.bill-actions { display: flex; gap: 10px; margin-bottom: 20px; }
.bill-content { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.bill-header { display: flex; justify-content: space-between; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e0e0e0; }
.company-info h1 { margin: 0 0 10px 0; }
.bill-info h2 { margin: 0 0 10px 0; }
.supplier-info { margin-bottom: 24px; padding: 16px; background: #f8f9fa; border-radius: 8px; }
.supplier-info h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; }
.bill-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.bill-table th, .bill-table td { border: 1px solid #e0e0e0; padding: 12px; text-align: left; }
.bill-table th { background: #f8f9fa; font-weight: 600; }
.bill-summary { margin-top: 24px; max-width: 320px; margin-left: auto; }
.summary-row { display: flex; justify-content: space-between; margin: 8px 0; }
.summary-row.total { font-weight: bold; font-size: 18px; color: #333; border-top: 2px solid #333; padding-top: 12px; margin-top: 12px; }
.bill-notes { margin-top: 20px; padding: 12px; background: #f8f9fa; border-radius: 8px; }
.bill-footer { margin-top: 30px; text-align: center; color: #666; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #667eea; color: white; }
.btn-secondary { background: #6c757d; color: white; }
</style>
