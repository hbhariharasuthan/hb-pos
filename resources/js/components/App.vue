<template>
    <div id="app">
        <nav v-if="isAuthenticated && $route.path !== '/pos'" class="main-nav">
            <div class="nav-brand">
                <img src="/logo.png" alt="HB Logo" class="logo-img" />
                <span class="brand-text">HB POS System</span>
            </div>
            <div class="nav-links">
                <router-link to="/dashboard">Dashboard</router-link>
                <router-link to="/pos">POS</router-link>
                <router-link to="/products">Products</router-link>
                <router-link to="/categories">Categories</router-link>
                <router-link to="/brands">Brands</router-link>
                <router-link to="/inventory">Inventory</router-link>
                <router-link to="/purchases">Purchases</router-link>
                <router-link to="/customers">Customers</router-link>
                <router-link to="/sales">Sales</router-link>
                <router-link to="/reports">Reports</router-link>
            </div>
        </nav>
        <router-view />
        <footer class="app-footer">
            <p>&copy; {{ currentYear }} hbitpartner.com. All rights reserved.</p>
        </footer>
    </div>
</template>

<script>
import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'App',
    setup() {
        const authStore = useAuthStore();
        const currentYear = new Date().getFullYear();
        
        return {
            isAuthenticated: computed(() => authStore.isAuthenticated),
            currentYear
        };
    },
    async mounted() {
        const { useAuthStore } = await import('../stores/auth');
        const authStore = useAuthStore();
        
        // Ensure token is set on axios if it exists
        if (authStore.token) {
            const axios = (await import('axios')).default;
            axios.defaults.headers.common['Authorization'] = `Bearer ${authStore.token}`;
            if (window.axios) {
                window.axios.defaults.headers.common['Authorization'] = `Bearer ${authStore.token}`;
            }
        }
        
        await authStore.checkAuth();
    }
}
</script>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: #f5f5f5;
    min-height: 100vh;
}

#app {
    width: 100%;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.main-nav {
    background: white;
    padding: 15px 30px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
}

.logo-img {
    height: 40px;
    width: auto;
    object-fit: contain;
}

.brand-text {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
}

.nav-links {
    display: flex;
    gap: 20px;
}

.nav-links a {
    text-decoration: none;
    color: #333;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 4px;
    transition: all 0.3s;
}

.nav-links a:hover,
.nav-links a.router-link-active {
    background: #667eea;
    color: white;
}

.app-footer {
    background: white;
    padding: 20px;
    text-align: center;
    border-top: 1px solid #e0e0e0;
    margin-top: auto;
    color: #666;
    font-size: 14px;
}

.app-footer p {
    margin: 0;
}
</style>
