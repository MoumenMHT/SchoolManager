<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import StatsWidget from '@/components/dashboard/StatsWidget.vue';
import StudentsByClassWidget from '@/components/dashboard/StudentsByClassWidget.vue';
import AttendanceWidget from '@/components/dashboard/AttendanceWidget.vue';

import dashboardService from '@/service/DashboardService';
import type { DashboardStats } from '@/types';

const { t } = useI18n();

const dashboardStats   = ref<DashboardStats | null>(null);


const loading         = ref(true);

const error           = ref<string | null>(null);

const getAcademicYear = () => {
  const now = new Date();
  const currentYear = now.getFullYear();
  const startYear = now.getMonth() < 8 ? currentYear - 1 : currentYear;
  return `${startYear}-${startYear + 1}`;
};

const loadDashboardData = async () => {
  try {
    loading.value = true;
    error.value = null;
    dashboardStats.value = await dashboardService.getStats(getAcademicYear());
  } catch (err: any) {
    error.value = err.response?.data?.message || t('dashboard.failed_load');
    console.error('Dashboard error:', err);
  } finally {
    loading.value = false;
  }
};



onMounted(() => {
  loadDashboardData();
});
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <!-- Loading State -->
    <div v-if="loading" class="col-span-12 text-center py-8">
      <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      <p class="mt-4 text-muted-color">{{ t('dashboard.loading') }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="col-span-12">
      <div class="card bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <div class="flex items-center gap-3">
          <i class="pi pi-exclamation-triangle text-red-600 text-2xl"></i>
          <div>
            <h3 class="text-red-900 dark:text-red-100 font-semibold">{{ t('dashboard.error_title') }}</h3>
            <p class="text-red-700 dark:text-red-300">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Dashboard Content -->
    <template v-else-if="dashboardStats">
      <StatsWidget :stats="dashboardStats.overview" />

      <div class="col-span-12 xl:col-span-6">
        <StudentsByClassWidget :data="dashboardStats.students_by_class" />
      </div>

      <div class="col-span-12 xl:col-span-6">
        <AttendanceWidget
          :attendance-rate="dashboardStats.academic.attendance_rate"
          :breakdown="dashboardStats.attendance_breakdown"
        />
      </div>
    </template>

  </div>
</template>
