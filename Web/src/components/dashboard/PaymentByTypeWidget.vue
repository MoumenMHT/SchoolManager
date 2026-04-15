<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Chart, registerables } from 'chart.js';
import type { FinancialReport } from '@/types';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

Chart.register(...registerables);

interface Props {
  report: FinancialReport;
}

const props = defineProps<Props>();
const chartRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: any = null;

const typeLabels = computed(() => ({
  cash: t('dashboard.payment_type_cash'),
  bank_transfer: t('dashboard.payment_type_transfer'),
  cheque: t('dashboard.payment_type_cheque'),
  online: t('dashboard.payment_type_online'),
  card: t('dashboard.payment_type_card'),
  other: t('dashboard.payment_type_other'),
  refund: t('dashboard.payment_type_refund'),
}));

const typeColors: Record<string, string> = {
  cash: 'rgba(34, 197, 94, 0.8)',
  bank_transfer: 'rgba(59, 130, 246, 0.8)',
  cheque: 'rgba(168, 85, 247, 0.8)',
  online: 'rgba(14, 165, 233, 0.8)',
  card: 'rgba(245, 158, 11, 0.8)',
  other: 'rgba(148, 163, 184, 0.8)',
  refund: 'rgba(239, 68, 68, 0.8)'
};

const typeEntries = computed(() =>
  Object.entries(props.report.payment_by_type).filter(([key]) => key !== 'refund')
);

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'DZD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount);
};

const getLabel = (key: string) => typeLabels.value[key as keyof typeof typeLabels.value] ?? key.replace(/_/g, ' ');
const getColor = (key: string) => typeColors[key] ?? 'rgba(148, 163, 184, 0.8)';

const totalCollected = computed(() =>
  typeEntries.value.reduce((sum, [, v]) => sum + v.total, 0)
);

const chartData = computed(() => ({
  labels: typeEntries.value.map(([key]) => getLabel(key)),
  datasets: [
    {
      data: typeEntries.value.map(([, v]) => v.total),
      backgroundColor: typeEntries.value.map(([key]) => getColor(key)),
      borderWidth: 2,
      borderColor: '#fff'
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  cutout: '65%'
};

const initChart = () => {
  if (chartRef.value && typeEntries.value.length > 0) {
    if (chartInstance) {
      chartInstance.destroy();
    }
    const ctx = chartRef.value.getContext('2d');
    if (ctx) {
      chartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: chartData.value,
        options: chartOptions
      });
    }
  }
};

onMounted(() => {
  initChart();
});
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h5 class="text-xl font-semibold">{{ t('dashboard.payment_methods') }}</h5>
      <i class="pi pi-chart-pie text-2xl text-primary"></i>
    </div>

    <div v-if="typeEntries.length > 0">
      <!-- Doughnut Chart -->
      <div style="height: 220px; position: relative;">
        <canvas ref="chartRef"></canvas>
      </div>

      <!-- Legend & Breakdown -->
      <div class="mt-6 grid gap-3">
        <div
          v-for="([key, val]) in typeEntries"
          :key="key"
          class="flex items-center justify-between p-3 bg-surface-50 dark:bg-surface-800 rounded-border"
        >
          <div class="flex items-center gap-3">
            <div class="rounded-full" :style="`background: ${getColor(key)}; width: 12px; height: 12px; flex-shrink: 0`"></div>
            <span class="font-medium text-sm capitalize">{{ getLabel(key) }}</span>
            <Tag :value="`${val.count} ${t('dashboard.txn')}`" severity="secondary" />
          </div>
          <div class="text-right">
            <div class="font-semibold text-sm">{{ formatCurrency(val.total) }}</div>
            <div class="text-xs text-muted-color">
              {{ totalCollected > 0 ? ((val.total / totalCollected) * 100).toFixed(1) : '0' }}%
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-8">
      <i class="pi pi-chart-pie text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color">{{ t('dashboard.no_payment_data') }}</p>
    </div>
  </div>
</template>
