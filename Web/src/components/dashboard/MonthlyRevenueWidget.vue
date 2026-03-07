<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Chart, registerables } from 'chart.js';
import type { PaymentRecord } from '@/types';

Chart.register(...registerables);

interface Props {
  payments: PaymentRecord[];
}

const props = defineProps<Props>();
const chartRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

// Academic year months Sep 2025 → Jun 2026
const MONTHS = [
  { key: '2025-09', label: 'Sep' },
  { key: '2025-10', label: 'Oct' },
  { key: '2025-11', label: 'Nov' },
  { key: '2025-12', label: 'Dec' },
  { key: '2026-01', label: 'Jan' },
  { key: '2026-02', label: 'Feb' },
  { key: '2026-03', label: 'Mar' },
  { key: '2026-04', label: 'Apr' },
  { key: '2026-05', label: 'May' },
  { key: '2026-06', label: 'Jun' },
];

const buildData = () => {
  const map: Record<string, number> = {};
  MONTHS.forEach(m => (map[m.key] = 0));

  for (const p of props.payments) {
    if (p.status !== 'completed' || !p.paid_date) continue;
    const d = new Date(p.paid_date);
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    if (key in map) map[key] += Number(p.amount);
  }
  return MONTHS.map(m => Math.round(map[m.key]));
};

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'DZD', minimumFractionDigits: 0 }).format(v);

const initChart = () => {
  if (!chartRef.value) return;
  if (chartInstance) chartInstance.destroy();

  const data = buildData();
  const today = new Date();
  const currentMonthKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
  const currentIdx = MONTHS.findIndex(m => m.key === currentMonthKey);

  const bgColors = MONTHS.map((m, i) => {
    if (i < currentIdx) return 'rgba(34, 197, 94, 0.75)';   // past – green
    if (i === currentIdx) return 'rgba(59, 130, 246, 0.85)'; // current – blue
    return 'rgba(148, 163, 184, 0.3)';                       // future – gray
  });

  const ctx = chartRef.value.getContext('2d');
  if (!ctx) return;

  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: MONTHS.map(m => m.label),
      datasets: [{
        label: 'Collected (DZD)',
        data,
        backgroundColor: bgColors,
        borderRadius: 6,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + formatCurrency(ctx.parsed.y)
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: v => formatCurrency(Number(v))
          },
          grid: { color: 'rgba(148, 163, 184, 0.15)' }
        },
        x: { grid: { display: false } }
      }
    }
  });
};

const totalCollected = () =>
  props.payments
    .filter(p => p.status === 'completed')
    .reduce((s, p) => s + Number(p.amount), 0);

onMounted(() => initChart());
watch(() => props.payments, () => initChart(), { deep: true });
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h5 class="text-xl font-semibold">Monthly Revenue</h5>
        <p class="text-sm text-muted-color mt-1">Collections per month — academic year 2025-2026</p>
      </div>
      <div class="text-right">
        <div class="text-xs text-muted-color mb-1">Total Collected</div>
        <div class="text-lg font-bold text-green-600 dark:text-green-400">
          {{ new Intl.NumberFormat('en-US', { style: 'currency', currency: 'DZD', minimumFractionDigits: 0 }).format(totalCollected()) }}
        </div>
      </div>
    </div>

    <div v-if="payments.length > 0" style="height: 260px">
      <canvas ref="chartRef"></canvas>
    </div>
    <div v-else class="text-center py-10">
      <i class="pi pi-chart-bar text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color">No payment data available</p>
    </div>

    <!-- Legend -->
    <div class="flex items-center gap-6 mt-4 text-xs text-muted-color">
      <div class="flex items-center gap-1.5">
        <span class="inline-block w-3 h-3 rounded-sm" style="background:rgba(34,197,94,0.75)"></span> Past months
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block w-3 h-3 rounded-sm" style="background:rgba(59,130,246,0.85)"></span> Current month
      </div>
      <div class="flex items-center gap-1.5">
        <span class="inline-block w-3 h-3 rounded-sm" style="background:rgba(148,163,184,0.3)"></span> Upcoming
      </div>
    </div>
  </div>
</template>
