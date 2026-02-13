<template>
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Register</h1>
            
            <form @submit.prevent="handleRegister" class="auth-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-input"
                        :class="{ 'error': errors.name }"
                        placeholder="Enter your full name"
                        @blur="validateField('name')"
                    />
                    <span v-if="errors.name" class="error-message">{{ errors.name }}</span>
                </div>

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

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="form-input"
                        :class="{ 'error': errors.password_confirmation }"
                        placeholder="Confirm your password"
                        @blur="validateField('password_confirmation')"
                    />
                    <span v-if="errors.password_confirmation" class="error-message">{{ errors.password_confirmation }}</span>
                </div>

                <div v-if="serverError" class="server-error">
                    {{ serverError }}
                </div>

                <button type="submit" class="btn btn-primary" :disabled="loading">
                    {{ loading ? 'Registering...' : 'Register' }}
                </button>
            </form>

            <p class="auth-link">
                Already have an account?
                <router-link to="/login">Login here</router-link>
            </p>
        </div>
    </div>
</template>

<script>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'Register',
    setup() {
        const router = useRouter();
        const authStore = useAuthStore();
        
        const form = reactive({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        });
        
        const errors = reactive({});
        const serverError = ref('');
        const loading = ref(false);
        
        const validateField = (field) => {
            delete errors[field];
            
            if (field === 'name') {
                if (!form.name.trim()) {
                    errors.name = 'Name is required';
                } else if (form.name.trim().length < 2) {
                    errors.name = 'Name must be at least 2 characters';
                }
            }
            
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
            
            if (field === 'password_confirmation') {
                if (!form.password_confirmation) {
                    errors.password_confirmation = 'Please confirm your password';
                } else if (form.password !== form.password_confirmation) {
                    errors.password_confirmation = 'Passwords do not match';
                }
            }
        };
        
        const validateForm = () => {
            errors.name = '';
            errors.email = '';
            errors.password = '';
            errors.password_confirmation = '';
            
            if (!form.name.trim()) {
                errors.name = 'Name is required';
            } else if (form.name.trim().length < 2) {
                errors.name = 'Name must be at least 2 characters';
            }
            
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
            
            if (!form.password_confirmation) {
                errors.password_confirmation = 'Please confirm your password';
            } else if (form.password !== form.password_confirmation) {
                errors.password_confirmation = 'Passwords do not match';
            }
            
            return !errors.name && !errors.email && !errors.password && !errors.password_confirmation;
        };
        
        const handleRegister = async () => {
            serverError.value = '';
            
            if (!validateForm()) {
                return;
            }
            
            loading.value = true;
            
            try {
                const response = await axios.post('/api/register', form);
                authStore.setAuth(response.data.token, response.data.user);
                router.push('/dashboard');
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    const validationErrors = error.response.data.errors;
                    Object.keys(validationErrors).forEach(key => {
                        errors[key] = validationErrors[key][0];
                    });
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
            handleRegister,
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
