<script setup lang="ts">
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import apiService from '@/service/ApiService';

const router = useRouter();
const toast = useToast();

// Form fields
const email = ref('admin@schoolmanager.com');
const password = ref('password123');
const rememberMe = ref(false);

// State management
const loading = ref(false);
const errors = ref<{ email?: string; password?: string }>({});

// Validation
const isEmailValid = computed(() => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email.value);
});

const isFormValid = computed(() => {
  return email.value && password.value && isEmailValid.value;
});

// Clear error when user types
const clearErrors = () => {
  errors.value = {};
};

// Validate form
const validateForm = (): boolean => {
  errors.value = {};
  
  if (!email.value) {
    errors.value.email = 'Email is required';
    return false;
  }
  
  if (!isEmailValid.value) {
    errors.value.email = 'Please enter a valid email address';
    return false;
  }
  
  if (!password.value) {
    errors.value.password = 'Password is required';
    return false;
  }
  
  if (password.value.length < 6) {
    errors.value.password = 'Password must be at least 6 characters';
    return false;
  }
  
  return true;
};

// Handle login
const handleLogin = async () => {
  if (!validateForm()) {
    return;
  }
  
  loading.value = true;
  clearErrors();
  
  try {
    const response = await apiService.login(email.value, password.value);
    
    if (response.success) {
      toast.add({
        severity: 'success',
        summary: 'Login Successful',
        detail: `Welcome back, ${response.user.username}!`,
        life: 3000
      });
      
      // Store remember me preference
      if (rememberMe.value) {
        localStorage.setItem('remember_me', 'true');
      } else {
        localStorage.removeItem('remember_me');
      }
      
      // Redirect to dashboard
      setTimeout(() => {
        router.push('/');
      }, 500);
    }
  } catch (error: any) {
    console.error('Login error:', error);
    
    const errorMessage = error.response?.data?.message || 'Invalid email or password';
    
    toast.add({
      severity: 'error',
      summary: 'Login Failed',
      detail: errorMessage,
      life: 5000
    });
    
    // Set field errors if available
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    }
  } finally {
    loading.value = false;
  }
};

// Handle Enter key press
const handleKeyPress = (event: KeyboardEvent) => {
  if (event.key === 'Enter' && isFormValid.value) {
    handleLogin();
  }
};
</script>

<template>
    <FloatingConfigurator />
    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                    <div class="text-center mb-8">
                        <i class="pi pi-graduation-cap text-6xl text-primary mb-4"></i>
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Welcome to SchoolHub!</div>
                        <span class="text-muted-color font-medium">Sign in to continue</span>
                    </div>

                    <div>
                        <label for="email1" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Email</label>
                        <InputText 
                            id="email1" 
                            type="email" 
                            placeholder="Email address" 
                            class="w-full md:w-[30rem]" 
                            :class="{ 'p-invalid': errors.email }"
                            v-model="email"
                            @input="clearErrors"
                            @keypress="handleKeyPress"
                            :disabled="loading"
                        />
                        <small v-if="errors.email" class="p-error block mb-4 mt-1">{{ errors.email }}</small>
                        <div v-else class="mb-8"></div>

                        <label for="password1" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Password</label>
                        <Password 
                            id="password1" 
                            v-model="password" 
                            placeholder="Password" 
                            :toggleMask="true" 
                            class="mb-1" 
                            fluid 
                            :feedback="false"
                            :class="{ 'p-invalid': errors.password }"
                            @input="clearErrors"
                            @keypress="handleKeyPress"
                            :disabled="loading"
                        />
                        <small v-if="errors.password" class="p-error block mb-4 mt-1">{{ errors.password }}</small>
                        <div v-else class="mb-4"></div>

                        <div class="flex items-center justify-between mt-2 mb-8 gap-8">
                            <div class="flex items-center">
                                <Checkbox v-model="rememberMe" id="rememberme1" binary class="mr-2" :disabled="loading"></Checkbox>
                                <label for="rememberme1" class="cursor-pointer">Remember me</label>
                            </div>
                            <span class="font-medium no-underline ml-2 text-right cursor-pointer text-primary hover:underline">Forgot password?</span>
                        </div>
                        
                        <Button 
                            label="Sign In" 
                            class="w-full" 
                            @click="handleLogin"
                            :loading="loading"
                            :disabled="!isFormValid || loading"
                            icon="pi pi-sign-in"
                        />
                        
                        <!-- Demo Credentials -->
                        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-border border border-blue-200 dark:border-blue-800">
                            <div class="text-sm text-blue-900 dark:text-blue-100 font-semibold mb-2">Demo Credentials</div>
                            <div class="text-xs text-blue-700 dark:text-blue-300">
                                <div class="mb-1"><strong>Admin:</strong> admin@schoolmanager.com / password123</div>
                                <div class="mb-1"><strong>Teacher:</strong> teacher@schoolmanager.com / password123</div>
                                <div><strong>Parent:</strong> parent@schoolmanager.com / password123</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pi-eye {
    transform: scale(1.6);
    margin-right: 1rem;
}

.pi-eye-slash {
    transform: scale(1.6);
    margin-right: 1rem;
}
</style>
