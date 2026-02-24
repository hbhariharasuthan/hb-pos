import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './components/App.vue';
import Login from './components/Login.vue';
import Register from './components/Register.vue';
import Dashboard from './components/Dashboard.vue';
import POS from './components/POS.vue';
import Products from './components/Products.vue';
import Categories from './components/Categories.vue';
import Brands from './components/Brands.vue';
import Customers from './components/Customers.vue';
import Sales from './components/Sales.vue';
import Invoice from './components/Invoice.vue';
import Inventory from './components/Inventory.vue';
import Purchases from './components/Purchases.vue';
import PurchaseBill from './components/PurchaseBill.vue';
import Reports from './components/Reports.vue';
import { useAuthStore } from './stores/auth';
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const routes = [
    { path: '/', redirect: '/login' },
    { path: '/login', component: Login, meta: { requiresGuest: true } },
    { path: '/register', component: Register, meta: { requiresGuest: true } },
    { path: '/dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/pos', component: POS, meta: { requiresAuth: true } },
    { path: '/products', component: Products, meta: { requiresAuth: true } },
    { path: '/categories', component: Categories, meta: { requiresAuth: true } },
    { path: '/brands', component: Brands, meta: { requiresAuth: true } },
    { path: '/customers', component: Customers, meta: { requiresAuth: true } },
    { path: '/sales', component: Sales, meta: { requiresAuth: true } },
    { path: '/sales/:id/invoice', component: Invoice, meta: { requiresAuth: true } },
    { path: '/inventory', component: Inventory, meta: { requiresAuth: true } },
    { path: '/purchases', component: Purchases, meta: { requiresAuth: true } },
    { path: '/purchases/:id/bill', component: PurchaseBill, meta: { requiresAuth: true } },
    { path: '/reports', component: Reports, meta: { requiresAuth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();
    
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        next('/login');
    } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
        next('/dashboard');
    } else {
        next();
    }
});

const app = createApp(App);
app.use(router);
app.use(Toast, {
    position: "top-right",
    timeout: 3000,
    closeOnClick: true,
    pauseOnHover: true,
});
app.mount('#app');
