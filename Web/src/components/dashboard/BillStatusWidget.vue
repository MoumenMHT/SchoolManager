<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Chart, registerables } from 'chart.js';
import type { BillRecord } from '@/types';

Chart.register(...registerables);

interface Props {
  bills: BillRecord[];
}

const props = defineProps<Props>();
const chartRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

const STATUSES = [
  { key: 'paid',    label: 'Paid',    color: 'rgba(34,197,94,0.8)',   text: 'text-green-600 dark:text-green-400'  },
  { key: 'partial', label: 'Partial', color: 'rgba(245,158,11,0.8)',  text: 'text-orange-500 dark:text-orange-400' },
  { key: 'late',    label: 'Late',    color: 'rgba(239,68,68,0.8)',   text: 'text-red-600 dark:text-red-400'      },
  { key: 'unpaid',  label: 'Unpaid',  color: 'rgba(148,163,184,0.6)', text: 'text-slate-500 dark:text-slate-400'  },
];

const stats = computed(() =>
  STATUSES.map(s => {
    const filtered = props.bills.filter(b => b.status === s.key);
    return {
      ...s,
      count:  filtered.length,
      amount: filtered.reduce((sum, b) => sum + Number(b.amount_due), 0),
      balance: filtered.reduce((sum, b) => sum + Number(b.balance), 0),
    };
  })
);

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'DZD', minimumFractionDigits: 0 }).format(v);

const initChart = () => {
  if (!chartRef.value || props.bills.length === 0) return;
  if (chartInstance) chartInstance.destroy();

  const ctx = chartRef.value.getContext('2d');
  if (!ctx) return;

  chartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: stats.value.map(s => s.label),
      datasets: [{
        data:            stats.value.map(s => s.count),
        backgroundColor: stats.value.map(s => s.color),
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.parsed} bills`
          }
        }
      }
    }
  });
};

onMounted(() => initChart());
watch(() => props.bills, () => initChart(), { deep: true });
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h5 class="text-xl font-semibold">Bill Status Overview</h5>
        <p class="text-sm text-muted-color mt-1">{{ bills.length }} total bills this year</p>
      </div>
      <i class="pi pi-file-check text-2xl text-primary"></i>
    </div>

    <div v-if="bills.length > 0">
      <div style="height: 200px">
        <canvas ref="chartRef"></canvas>
      </div>

      <div class="mt-5 grid gap-3">
        <div
          v-for="s in stats"
          :key="s.key"
          class="flex items-center justify-between p-3 bg-surface-50 dark:bg-surface-800 rounded-border"
        >
          <div class="flex items-center gap-3">
            <div class="rounded-full" :style="`background:${s.color};width:12px;height:12px;flex-shrink:0`"></div>
            <span class="font-medium text-sm">{{ s.label }}</span>
            <Tag :value="`${s.count} bills`" severity="secondary" />
          </div>
          <div class="text-right">
            <div class="font-semibold text-sm" :class="s.text">
              {{ formatCurrency(s.key === 'paid' ? s.amount : s.balance) }}
            </div>
            <div class="text-xs text-muted-color">
              {{ s.key === 'paid' ? 'collected' : 'outstanding' }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-10">
      <i class="pi pi-file text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color">No bill data available</p>
    </div>
  </div>
</template>
