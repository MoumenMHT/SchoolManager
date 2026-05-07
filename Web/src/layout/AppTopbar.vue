<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useLayout } from '@/layout/composables/layout';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import apiService from '@/service/ApiService';
import AppConfigurator from './AppConfigurator.vue';
import { SUPPORTED_LOCALES } from '@/i18n/index.js';

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const router = useRouter();
const toast = useToast();
const { t, locale } = useI18n();

const currentUser = ref(null);

onMounted(() => {
    currentUser.value = apiService.getUser();
});

const userDisplayName = computed(() => {
    if (!currentUser.value) return 'User';
    return currentUser.value.username || currentUser.value.email || 'User';
});

const handleLogout = async () => {
    try {
        await apiService.logout();
        toast.add({
            severity: 'success',
            summary: t('topbar.logged_out'),
            detail: t('topbar.logged_out_message'),
            life: 3000
        });
        router.push('/auth/login');
    } catch (error) {
        console.error('Logout error:', error);
        apiService.removeToken();
        router.push('/auth/login');
    }
};

const changeLocale = (code) => {
    locale.value = code;
};

const currentLocale = computed(() => SUPPORTED_LOCALES.find((l) => l.code === locale.value) || SUPPORTED_LOCALES[0]);
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/" class="layout-topbar-logo">
                <i class="pi pi-graduation-cap text-3xl text-primary"></i>
                <span class="ml-2">SchoolHub</span>
            </router-link>
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
                <div class="relative">
                    <button
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
                        type="button"
                        class="layout-topbar-action layout-topbar-action-highlight"
                    >
                        <i class="pi pi-palette"></i>
                    </button>
                    <AppConfigurator />
                </div>

                <!-- Language Switcher -->
                <div class="relative">
                    <button
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
                        type="button"
                        class="layout-topbar-action"
                        :title="t('topbar.language')"
                    >
                        <i class="pi pi-globe"></i>
                    </button>
                    <div class="hidden absolute end-0 top-full mt-1 z-50 bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl shadow-lg min-w-36 p-1">
                        <button
                            v-for="loc in SUPPORTED_LOCALES"
                            :key="loc.code"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                            :class="{ 'font-semibold text-primary-600 dark:text-primary-400': locale === loc.code }"
                            @click="changeLocale(loc.code)"
                        >
                            <span class="text-xs font-mono uppercase opacity-60">{{ loc.code }}</span>
                            <span>{{ loc.label }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <button
                class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
            >
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-user"></i>
                        <span>{{ userDisplayName }}</span>
                    </button>
                    <button type="button" class="layout-topbar-action" @click="handleLogout">
                        <i class="pi pi-sign-out"></i>
                        <span>{{ t('topbar.logout') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
