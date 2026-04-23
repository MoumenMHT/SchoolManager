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
const isSupervisor = computed(() => userRole.value === 'supervisor');
const isSecretariat = computed(() => userRole.value === 'secretariat');
const isAccountant = computed(() => userRole.value === 'accountant');
const isDirector = computed(() => ['primary_director', 'cem_director', 'lycee_director'].includes(userRole.value));

const model = computed(() => {
    const sections = [];

    if (isAccountant.value) {
        sections.push({
            label: t('nav.finance', 'Finance'),
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

    if (isSupervisor.value) {
        sections.push({
            label: t('nav.supervision'),
            items: [
                {
                    label: t('nav.supervisor_panel'),
                    icon: 'pi pi-fw pi-eye',
                    to: '/supervisor/panel'
                }
            ]
        });
    }

    if (isAdmin.value || isSecretariat.value || isDirector.value) {
        const academicItems = [];
        const peopleItems = [];

        academicItems.push({ label: t('nav.levels'), icon: 'pi pi-fw pi-sitemap', to: '/levels' });
        academicItems.push({ label: t('nav.classes'), icon: 'pi pi-fw pi-building', to: '/classes' });
        academicItems.push({ label: t('nav.subjects'), icon: 'pi pi-fw pi-book', to: '/subjects' });

        if (isAdmin.value || isDirector.value) {
            academicItems.push({ label: t('nav.attendance'), icon: 'pi pi-fw pi-check-circle', to: '/attendance' });
            academicItems.push({ label: t('nav.grade_analytics'), icon: 'pi pi-fw pi-chart-bar', to: '/analytics/grades' });
        }

        if (isAdmin.value) {
            academicItems.push({ label: t('nav.schedule_generator'), icon: 'pi pi-fw pi-calendar-plus', to: '/schedules/generate' });
        }

        sections.push({ label: t('nav.academic', 'Academic'), items: academicItems });


        peopleItems.push({ label: t('nav.students'), icon: 'pi pi-fw pi-graduation-cap', to: '/students' });
        peopleItems.push({ label: t('nav.parents'), icon: 'pi pi-fw pi-users', to: '/parents' });
        peopleItems.push({ label: t('nav.teachers'), icon: 'pi pi-fw pi-id-card', to: '/teachers' });

        if (isAdmin.value || isDirector.value) {
            peopleItems.push({ label: t('nav.supervisors'), icon: 'pi pi-fw pi-eye', to: '/supervisors' });
        }

        sections.push({ label: t('nav.people', 'People'), items: peopleItems });


        if (isAdmin.value) {
            const adminItems = [];
            adminItems.push({ label: t('nav.directors', 'Directors'), icon: 'pi pi-fw pi-star', to: '/directors' });
            adminItems.push({ label: t('nav.secretaries', 'Secretariats'), icon: 'pi pi-fw pi-desktop', to: '/secretariats' });
            adminItems.push({ label: t('nav.accountants', 'Accountants'), icon: 'pi pi-fw pi-wallet', to: '/accountants' });
            adminItems.push({ label: t('nav.user_management', 'User Management'), icon: 'pi pi-fw pi-key', to: '/user-management' });

            sections.push({ label: t('nav.administration', 'Administration'), items: adminItems });
        }

        if (isAdmin.value) {
            sections.push({
                label: t('nav.finance', 'Finance'),
                items: [
                    { label: t('dashboard.payment_dashboard'), icon: 'pi pi-fw pi-credit-card', to: '/payments' }
                ]
            });
        }
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
