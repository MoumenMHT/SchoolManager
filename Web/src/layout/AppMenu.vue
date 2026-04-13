<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppMenuItem from './AppMenuItem.vue';
import apiService from '@/service/ApiService';

const { t } = useI18n();
const currentUser = computed(() => apiService.getUser());
const userRole = computed(() => currentUser.value?.role || 'admin');
const isTeacher = computed(() => userRole.value === 'teacher');
const isAdmin = computed(() => userRole.value === 'admin' || userRole.value === 'supervisor');
const isSecretariat = computed(() => userRole.value === 'secretariat');
const isAccountant = computed(() => userRole.value === 'accountant');
const isDirector = computed(() => ['primary_director', 'cem_director', 'lycee_director'].includes(userRole.value));

const model = computed(() => {
    const sections = [];

    if (isAccountant.value) {
        sections.push({
            label: t('nav.management'),
            items: [
                {
                    label: t('dashboard.payment_dashboard'),
                    icon: 'pi pi-fw pi-credit-card',
                    to: '/payments'
                }
            ]
        });
        return sections;
    }

    sections.push({
        label: t('nav.home'),
        items: [
            {
                label: t('nav.dashboard'),
                icon: 'pi pi-fw pi-home',
                to: '/'
            }
        ]
    });

    if (isTeacher.value) {
        sections.push({
            label: t('nav.academic'),
            items: [
                {
                    label: t('nav.my_classes'),
                    icon: 'pi pi-fw pi-building',
                    to: '/teacher/portal'
                }
            ]
        });
    }

    if (isAdmin.value || isSecretariat.value || isDirector.value) {
        const managementItems = [];

        managementItems.push({
            label: t('nav.parents'),
            icon: 'pi pi-fw pi-users',
            to: '/parents'
        });
        managementItems.push({
            label: t('nav.teachers'),
            icon: 'pi pi-fw pi-id-card',
            to: '/teachers'
        });
        managementItems.push({
            label: t('nav.students'),
            icon: 'pi pi-fw pi-graduation-cap',
            to: '/students'
        });
        managementItems.push({
            label: t('nav.classes'),
            icon: 'pi pi-fw pi-building',
            to: '/classes'
        });
        managementItems.push({
            label: t('nav.subjects'),
            icon: 'pi pi-fw pi-book',
            to: '/subjects'
        });

        if (isAdmin.value || isDirector.value) {
            managementItems.push({
                label: t('nav.attendance'),
                icon: 'pi pi-fw pi-check-circle',
                to: '/attendance'
            });
            managementItems.push({
                label: t('nav.grade_analytics'),
                icon: 'pi pi-fw pi-chart-bar',
                to: '/analytics/grades'
            });
        }

        if (isAdmin.value) {
            managementItems.push({
                label: t('dashboard.payment_dashboard'),
                icon: 'pi pi-fw pi-credit-card',
                to: '/payments'
            });
            managementItems.push({
                label: t('nav.schedule_generator'),
                icon: 'pi pi-fw pi-calendar-plus',
                to: '/schedules/generate'
            });
        }

        sections.push({
            label: t('nav.management'),
            items: managementItems
        });
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
