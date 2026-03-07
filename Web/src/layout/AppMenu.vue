<script setup>
import { ref, computed } from 'vue';
import AppMenuItem from './AppMenuItem.vue';
import apiService from '@/service/ApiService';

const currentUser = computed(() => apiService.getUser());
const userRole = computed(() => currentUser.value?.role || 'admin');
const isTeacher = computed(() => userRole.value === 'teacher');
const isAdmin = computed(() => userRole.value === 'admin');

const model = computed(() => {
    const homeSection = {
        label: 'Home',
        items: [
            {
                label: 'Dashboard',
                icon: 'pi pi-fw pi-home',
                to: '/'
            }
        ]
    };

    const teacherSection = {
        label: 'Academic',
        items: [
            {
                label: 'My Classes',
                icon: 'pi pi-fw pi-building',
                to: '/teacher/portal'
            }
        ]
    };

    const adminSection = {
        label: 'Management',
        items: [
            {
                label: 'Parents',
                icon: 'pi pi-fw pi-users',
                to: '/parents'
            },
            {
                label: 'Teachers',
                icon: 'pi pi-fw pi-id-card',
                to: '/teachers'
            },
            {
                label: 'Students',
                icon: 'pi pi-fw pi-graduation-cap',
                to: '/students'
            },
            {
                label: 'Classes',
                icon: 'pi pi-fw pi-building',
                to: '/classes'
            },
            {
                label: 'Attendance',
                icon: 'pi pi-fw pi-check-circle',
                to: '/attendance'
            }
        ]
    };

    const sections = [homeSection];

    if (isTeacher.value) {
        sections.push(teacherSection);
    }

    if (isAdmin.value) {
        sections.push(adminSection);
    }

    return sections;
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
