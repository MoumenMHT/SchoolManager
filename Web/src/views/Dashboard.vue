<script setup lang="ts">
import { ref, onMounted } from 'vue';
import StatsWidget from '@/components/dashboard/StatsWidget.vue';
import RecentPaymentsWidget from '@/components/dashboard/RecentPaymentsWidget.vue';
import StudentsByClassWidget from '@/components/dashboard/StudentsByClassWidget.vue';
import FinancialWidget from '@/components/dashboard/FinancialWidget.vue';
import AttendanceWidget from '@/components/dashboard/AttendanceWidget.vue';
import dashboardService from '@/service/DashboardService';
import type { DashboardStats } from '@/types';

const dashboardStats = ref<DashboardStats | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const loadDashboardData = async () => {
  try {
    loading.value = true;
    error.value = null;
    
    // Generate academic year in format "2025-2026"
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth(); // 0-11 (0 = January)
    
    // Academic year typically starts in September (month 8)
    // If we're before September, use previous year, otherwise use current year
    const startYear = currentMonth < 8 ? currentYear - 1 : currentYear;
    const endYear = startYear + 1;
    const academicYear = `${startYear}-${endYear}`;
    
    dashboardStats.value = await dashboardService.getStats(academicYear);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load dashboard data';
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
      <p class="mt-4 text-muted-color">Loading dashboard data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="col-span-12">
      <div class="card bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <div class="flex items-center gap-3">
          <i class="pi pi-exclamation-triangle text-red-600 text-2xl"></i>
          <div>
            <h3 class="text-red-900 dark:text-red-100 font-semibold">Error Loading Dashboard</h3>
            <p class="text-red-700 dark:text-red-300">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Dashboard Content -->
    <template v-else-if="dashboardStats">
      <StatsWidget :stats="dashboardStats.overview" />

      <div class="col-span-12 xl:col-span-6">
        <RecentPaymentsWidget :payments="dashboardStats.recent_payments" />
        <StudentsByClassWidget :data="dashboardStats.students_by_class" />
      </div>
      
      <div class="col-span-12 xl:col-span-6">
        <FinancialWidget :financial="dashboardStats.financial" />
        <AttendanceWidget 
          :attendance-rate="dashboardStats.academic.attendance_rate"
          :breakdown="dashboardStats.attendance_breakdown"
        />
      </div>
    </template>
  </div>
</template>
