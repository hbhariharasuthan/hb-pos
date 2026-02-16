<template>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Dashboard</h1>
            <button @click="handleLogout" class="btn btn-secondary">Logout</button>
        </div>

        <!-- Report Widgets -->
        <div v-if="stats && stats.sales && stats.products && stats.inventory" class="report-widgets">
            <div class="report-widget">
                <div class="widget-icon">💰</div>
                <div class="widget-content">
                    <h3>Today's Sales</h3>
                    <p class="widget-value">₹{{ formatCurrency(stats.sales.today_revenue) }}</p>
                    <p class="widget-label">{{ stats.sales.today_count }} transactions</p>
                </div>
            </div>

            <div class="report-widget">
                <div class="widget-icon">📊</div>
                <div class="widget-content">
                    <h3>This Month</h3>
                    <p class="widget-value">₹{{ formatCurrency(stats.sales.month_revenue) }}</p>
                    <p class="widget-label">{{ stats.sales.month_count }} transactions</p>
                </div>
            </div>

            <div class="report-widget">
                <div class="widget-icon">📦</div>
                <div class="widget-content">
                    <h3>Products</h3>
                    <p class="widget-value">{{ stats.products.total || 0 }}</p>
                    <p class="widget-label">{{ stats.products.low_stock || 0 }} low stock</p>
                </div>
            </div>

            <div class="report-widget">
                <div class="widget-icon">💵</div>
                <div class="widget-content">
                    <h3>Inventory Value</h3>
                    <p class="widget-value">₹{{ formatCurrency(stats.inventory.total_value) }}</p>
                    <p class="widget-label">Total stock value</p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <router-link to="/pos" class="dashboard-card">
                <div class="card-icon">💰</div>
                <h2>Point of Sale</h2>
                <p>Process sales transactions</p>
            </router-link>

            <router-link to="/products" class="dashboard-card">
                <div class="card-icon">📦</div>
                <h2>Products</h2>
                <p>Manage inventory</p>
            </router-link>

            <router-link to="/categories" class="dashboard-card">
                <div class="card-icon">🏷️</div>
                <h2>Categories</h2>
                <p>Manage categories</p>
            </router-link>

            <router-link to="/customers" class="dashboard-card">
                <div class="card-icon">👥</div>
                <h2>Customers</h2>
                <p>Manage customer database</p>
            </router-link>

            <router-link to="/sales" class="dashboard-card">
                <div class="card-icon">📊</div>
                <h2>Sales</h2>
                <p>View sales history</p>
            </router-link>

            <router-link to="/inventory" class="dashboard-card">
                <div class="card-icon">📋</div>
                <h2>Inventory</h2>
                <p>Manage stock & movements</p>
            </router-link>

            <router-link to="/reports" class="dashboard-card">
                <div class="card-icon">📈</div>
                <h2>Reports</h2>
                <p>View analytics & reports</p>
            </router-link>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'Dashboard',
    setup() {
        const router = useRouter();
        const authStore = useAuthStore();
        const stats = ref(null);
        
        const formatCurrency = (value) => {
            if (value === null || value === undefined || isNaN(value)) {
                return '0.00';
            }
            return parseFloat(value).toFixed(2);
        };

        const loadStats = async () => {
            try {
                const response = await axios.get('/api/reports/dashboard-stats');
                stats.value = response.data;
            } catch (error) {
                console.error('Error loading stats:', error);
                // Set default values to prevent errors
                stats.value = {
                    sales: {
                        today_revenue: 0,
                        today_count: 0,
                        month_revenue: 0,
                        month_count: 0
                    },
                    products: {
                        total: 0,
                        low_stock: 0
                    },
                    inventory: {
                        total_value: 0
                    }
                };
            }
        };
        
        const handleLogout = async () => {
            try {
                await axios.post('/api/logout');
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                authStore.clearAuth();
                router.push('/login');
            }
        };
        
        onMounted(() => {
            loadStats();
        });
        
        return {
            stats,
            handleLogout,
            formatCurrency,
        };
    },
};
</script>

<style scoped>
.dashboard-container {
    padding: 30px;
    width: 100%;
    max-width: 100%;
    margin: 0;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 0 20px;
}

.dashboard-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #333;
    margin: 0;
}

.report-widgets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
    padding: 0 20px;
}

.report-widget {
    background: white;
    border-radius: 10px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    transition: all 0.3s;
}

.report-widget:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
}

.widget-icon {
    font-size: 36px;
}

.widget-content h3 {
    margin: 0 0 6px 0;
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.widget-value {
    margin: 0 0 4px 0;
    font-size: 22px;
    font-weight: bold;
    color: #333;
}

.widget-label {
    margin: 0;
    font-size: 11px;
    color: #999;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
    width: 100%;
    padding: 0 20px;
}

.dashboard-card {
    background: white;
    border-radius: 10px;
    padding: 18px 16px;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.25);
}

.card-icon {
    font-size: 36px;
    margin-bottom: 10px;
}

.dashboard-card h2 {
    margin: 0 0 6px 0;
    color: #333;
    font-size: 18px;
}

.dashboard-card p {
    margin: 0;
    color: #666;
    font-size: 12px;
    line-height: 1.3;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary {
    background: #e74c3c;
    color: white;
}

.btn-secondary:hover {
    background: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
}
</style>
