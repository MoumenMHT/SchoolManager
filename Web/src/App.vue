<script setup>
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();

watch(
    locale,
    (newLocale) => {
        const dir = newLocale === 'ar' ? 'rtl' : 'ltr';
        document.documentElement.dir = dir;
        document.documentElement.lang = newLocale;
        localStorage.setItem('locale', newLocale);
    },
    { immediate: true }
);
</script>

<template>
    <Toast />
    <router-view />
</template>

<style scoped></style>
