import { reactive } from 'vue';
import axios from 'axios';

const authStore = reactive({
    user: null,
    token: localStorage.getItem('auth_token') || null,
    
    get isAuthenticated() {
        return !!this.token;
    },
    
    setAuth(token, user) {
        this.token = token;
        this.user = user;
        localStorage.setItem('auth_token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    },
    
    clearAuth() {
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        delete axios.defaults.headers.common['Authorization'];
    },
    
    async checkAuth() {
        if (!this.token) return false;
        
        try {
            const response = await axios.get('/api/user');
            this.user = response.data.user;
            return true;
        } catch (error) {
            this.clearAuth();
            return false;
        }
    }
});

export function useAuthStore() {
    return authStore;
}
