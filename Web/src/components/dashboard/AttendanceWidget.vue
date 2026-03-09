<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
  attendanceRate: number;
  breakdown: {
    present?: number;
    absent?: number;
    late?: number;
    excused?: number;
  };
}

const props = defineProps<Props>();

const attendanceColor = computed(() => {
  if (props.attendanceRate >= 90) return 'text-green-600';
  if (props.attendanceRate >= 75) return 'text-orange-600';
  return 'text-red-600';
});

const attendanceSeverity = computed(() => {
  if (props.attendanceRate >= 90) return 'success';
  if (props.attendanceRate >= 75) return 'warning';
  return 'danger';
});

const totalRecords = computed(() => {
  return (
    (props.breakdown.present || 0) +
    (props.breakdown.absent || 0) +
    (props.breakdown.late || 0) +
    (props.breakdown.excused || 0)
  );
});

const getPercentage = (value: number) => {
  if (totalRecords.value === 0) return 0;
  return ((value / totalRecords.value) * 100).toFixed(1);
};
</script>

<template>
  <div class="card mb-8">
    <div class="flex items-center justify-between mb-6">
      <h5 class="text-xl font-semibold">{{ t('dashboard.attendance_overview') }}</h5>
      <i class="pi pi-calendar text-2xl text-primary"></i>
    </div>

    <!-- Overall Attendance Rate -->
    <div class="p-4 bg-surface-50 dark:bg-surface-800 rounded-border mb-6">
      <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-medium">{{ t('dashboard.attendance_rate_subtitle') }}</span>
        <Tag 
          :value="`${attendanceRate.toFixed(1)}%`" 
          :severity="attendanceSeverity"
        />
      </div>
      <ProgressBar 
        :value="attendanceRate" 
        :showValue="false"
        :class="attendanceColor"
      />
    </div>

    <!-- Breakdown -->
    <div class="grid grid-cols-2 gap-4">
      <!-- Present -->
      <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-border border border-green-200 dark:border-green-800">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center bg-green-100 dark:bg-green-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
            <i class="pi pi-check text-green-600 dark:text-green-400 text-lg"></i>
          </div>
          <div>
            <div class="text-xs text-green-700 dark:text-green-300 mb-1">{{ t('common.present') }}</div>
            <div class="text-xl font-bold text-green-900 dark:text-green-100">
              {{ breakdown.present || 0 }}
            </div>
            <div class="text-xs text-green-600 dark:text-green-400">
              {{ getPercentage(breakdown.present || 0) }}%
            </div>
          </div>
        </div>
      </div>

      <!-- Absent -->
      <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-border border border-red-200 dark:border-red-800">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center bg-red-100 dark:bg-red-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
            <i class="pi pi-times text-red-600 dark:text-red-400 text-lg"></i>
          </div>
          <div>
            <div class="text-xs text-red-700 dark:text-red-300 mb-1">{{ t('common.absent') }}</div>
            <div class="text-xl font-bold text-red-900 dark:text-red-100">
              {{ breakdown.absent || 0 }}
            </div>
            <div class="text-xs text-red-600 dark:text-red-400">
              {{ getPercentage(breakdown.absent || 0) }}%
            </div>
          </div>
        </div>
      </div>

      <!-- Late -->
      <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-border border border-orange-200 dark:border-orange-800">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
            <i class="pi pi-clock text-orange-600 dark:text-orange-400 text-lg"></i>
          </div>
          <div>
            <div class="text-xs text-orange-700 dark:text-orange-300 mb-1">{{ t('common.late') }}</div>
            <div class="text-xl font-bold text-orange-900 dark:text-orange-100">
              {{ breakdown.late || 0 }}
            </div>
            <div class="text-xs text-orange-600 dark:text-orange-400">
              {{ getPercentage(breakdown.late || 0) }}%
            </div>
          </div>
        </div>
      </div>

      <!-- Excused -->
      <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-border border border-blue-200 dark:border-blue-800">
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
            <i class="pi pi-info-circle text-blue-600 dark:text-blue-400 text-lg"></i>
          </div>
          <div>
            <div class="text-xs text-blue-700 dark:text-blue-300 mb-1">{{ t('common.excused') }}</div>
            <div class="text-xl font-bold text-blue-900 dark:text-blue-100">
              {{ breakdown.excused || 0 }}
            </div>
            <div class="text-xs text-blue-600 dark:text-blue-400">
              {{ getPercentage(breakdown.excused || 0) }}%
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="totalRecords === 0" class="text-center py-6 mt-4">
      <i class="pi pi-calendar-times text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color text-sm">{{ t('dashboard.no_attendance_records') }}</p>
    </div>
  </div>
</template>
