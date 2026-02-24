<template>
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Login</h1>
            
            <form @submit.prevent="handleLogin" class="auth-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="form-input"
                        :class="{ 'error': errors.email }"
                        placeholder="Enter your email"
                        @blur="validateField('email')"
                    />
                    <span v-if="errors.email" class="error-message">{{ errors.email }}</span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="form-input"
                        :class="{ 'error': errors.password }"
                        placeholder="Enter your password"
                        @blur="validateField('password')"
                    />
                    <span v-if="errors.password" class="error-message">{{ errors.password }}</span>
                </div>

                <div v-if="serverError" class="server-error">
                    {{ serverError }}
                </div>

                <button type="submit" class="btn btn-primary" :disabled="loading">
                    {{ loading ? 'Logging in...' : 'Login' }}
                </button>
            </form>

            <p class="auth-link">
                Don't have an account?
                <router-link to="/register">Register here</router-link>
            </p>
        </div>
    </div>
</template>

<script>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { handleApiError } from '@/utils/errorHandler';

export default {
    name: 'Login',
    setup() {
        const router = useRouter();
        const authStore = useAuthStore();
        
        const form = reactive({
            email: '',
            password: '',
        });
        
        const errors = reactive({});
        const serverError = ref('');
        const loading = ref(false);
        
        const validateField = (field) => {
            delete errors[field];
            
            if (field === 'email') {
                if (!form.email) {
                    errors.email = 'Email is required';
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
                    errors.email = 'Please enter a valid email address';
                }
            }
            
            if (field === 'password') {
                if (!form.password) {
                    errors.password = 'Password is required';
                } else if (form.password.length < 8) {
                    errors.password = 'Password must be at least 8 characters';
                }
            }
        };
        
        const validateForm = () => {
            errors.email = '';
            errors.password = '';
            
            if (!form.email) {
                errors.email = 'Email is required';
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
                errors.email = 'Please enter a valid email address';
            }
            
            if (!form.password) {
                errors.password = 'Password is required';
            } else if (form.password.length < 8) {
                errors.password = 'Password must be at least 8 characters';
            }
            
            return !errors.email && !errors.password;
        };
        
        const handleLogin = async () => {
            serverError.value = '';
            
            if (!validateForm()) {
                return;
            }
            
            loading.value = true;
            
            try {
                const response = await axios.post('/api/login', form);
                authStore.setAuth(response.data.token, response.data.user);
                router.push('/dashboard');
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    const validationErrors = error.response.data.errors;
                    if (validationErrors.email) {
                        errors.email = validationErrors.email[0];
                    }
                    if (validationErrors.password) {
                        errors.password = validationErrors.password[0];
                    }
                } else if (error.response && error.response.status === 401) {
                    serverError.value = error.response.data.message || 'Invalid credentials';
                } else {
                    serverError.value = error.response?.data?.message || 'An error occurred. Please try again.';
                }
            } finally {
                loading.value = false;
            }
        };
        
        return {
            form,
            errors,
            serverError,
            loading,
            validateField,
            handleLogin,
        };
    },
};
</script>

<style scoped>
.auth-container {
    width: 100%;
    max-width: 400px;
    padding: 20px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
}

.auth-card {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.auth-title {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin-bottom: 30px;
    text-align: center;
}

.auth-form {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 500;
    font-size: 14px;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s;
}

.form-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-input.error {
    border-color: #e74c3c;
}

.error-message {
    display: block;
    color: #e74c3c;
    font-size: 12px;
    margin-top: 5px;
}

.server-error {
    background: #fee;
    color: #c33;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid #fcc;
}

.btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.auth-link {
    text-align: center;
    color: #666;
    font-size: 14px;
}

.auth-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
}

.auth-link a:hover {
    text-decoration: underline;
}
</style>
