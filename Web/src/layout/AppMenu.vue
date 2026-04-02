<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppMenuItem from './AppMenuItem.vue';
import apiService from '@/service/ApiService';

const { t } = useI18n();
const currentUser = computed(() => apiService.getUser());
const userRole = computed(() => currentUser.value?.role || 'admin');
const isTeacher = computed(() => userRole.value === 'teacher');
const isAdmin = computed(() => userRole.value === 'admin');

const model = computed(() => {
    const homeSection = {
        label: t('nav.home'),
        items: [
            {
                label: t('nav.dashboard'),
                icon: 'pi pi-fw pi-home',
                to: '/'
            }
        ]
    };

    const teacherSection = {
        label: t('nav.academic'),
        items: [
            {
                label: t('nav.my_classes'),
                icon: 'pi pi-fw pi-building',
                to: '/teacher/portal'
            }
        ]
    };

    const adminSection = {
        label: t('nav.management'),
        items: [
            {
                label: t('nav.parents'),
                icon: 'pi pi-fw pi-users',
                to: '/parents'
            },
            {
                label: t('nav.teachers'),
                icon: 'pi pi-fw pi-id-card',
                to: '/teachers'
            },
            {
                label: t('nav.students'),
                icon: 'pi pi-fw pi-graduation-cap',
                to: '/students'
            },
            {
                label: t('nav.classes'),
                icon: 'pi pi-fw pi-building',
                to: '/classes'
            },
            {
                label: t('nav.attendance'),
                icon: 'pi pi-fw pi-check-circle',
                to: '/attendance'
            },
            {
                label: t('nav.grade_analytics'),
                icon: 'pi pi-fw pi-chart-bar',
                to: '/analytics/grades'
            },
            {
                label: 'Schedule Generator',
                icon: 'pi pi-fw pi-calendar-plus',
                to: '/schedules/generate'
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
