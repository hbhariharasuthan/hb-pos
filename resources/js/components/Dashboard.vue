<template>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Dashboard</h1>
            <button @click="handleLogout" class="btn btn-secondary">Logout</button>
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
        </div>
    </div>
</template>

<script>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'Dashboard',
    setup() {
        const router = useRouter();
        const authStore = useAuthStore();
        
        const user = computed(() => authStore.user);
        
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
        
        return {
            user,
            handleLogout,
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

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
    width: 100%;
    padding: 0 20px;
}

.dashboard-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(102, 126, 234, 0.3);
}

.card-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.dashboard-card h2 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 24px;
}

.dashboard-card p {
    margin: 0;
    color: #666;
    font-size: 14px;
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
