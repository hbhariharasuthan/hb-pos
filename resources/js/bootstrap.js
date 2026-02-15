import axios from 'axios';
window.axios = axios;

// Set default headers
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;

// Set token from localStorage if available
const token = localStorage.getItem('auth_token');
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

// Add response interceptor to handle 401 errors
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Clear auth and redirect to login
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common['Authorization'];
            if (window.axios) {
                delete window.axios.defaults.headers.common['Authorization'];
            }
            // Only redirect if not already on login page
            if (window.location.pathname !== '/login' && window.location.pathname !== '/register') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);
