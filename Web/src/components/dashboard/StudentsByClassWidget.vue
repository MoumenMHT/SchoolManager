<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Chart, registerables } from 'chart.js';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

Chart.register(...registerables);

interface Props {
  data: {
    class_name: string;
    student_count: number;
  }[];
}

const props = defineProps<Props>();
const chartRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: any = null;

const chartData = computed(() => ({
  labels: props.data.map(item => item.class_name),
  datasets: [
    {
      label: t('dashboard.students_label'),
      data: props.data.map(item => item.student_count),
      backgroundColor: [
        'rgba(54, 162, 235, 0.6)',
        'rgba(255, 99, 132, 0.6)',
        'rgba(255, 206, 86, 0.6)',
        'rgba(75, 192, 192, 0.6)',
        'rgba(153, 102, 255, 0.6)',
        'rgba(255, 159, 64, 0.6)',
        'rgba(199, 199, 199, 0.6)',
        'rgba(83, 102, 255, 0.6)',
      ],
      borderColor: [
        'rgba(54, 162, 235, 1)',
        'rgba(255, 99, 132, 1)',
        'rgba(255, 206, 86, 1)',
        'rgba(75, 192, 192, 1)',
        'rgba(153, 102, 255, 1)',
        'rgba(255, 159, 64, 1)',
        'rgba(199, 199, 199, 1)',
        'rgba(83, 102, 255, 1)',
      ],
      borderWidth: 2
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    title: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 5
      }
    }
  }
};

const initChart = () => {
  if (chartRef.value && props.data.length > 0) {
    if (chartInstance) {
      chartInstance.destroy();
    }
    
    const ctx = chartRef.value.getContext('2d');
    if (ctx) {
      chartInstance = new Chart(ctx, {
        type: 'bar',
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
  <div class="card mb-8">
    <div class="flex items-center justify-between mb-6">
      <h5 class="text-xl font-semibold">{{ t('dashboard.students_by_class') }}</h5>
    </div>

    <div v-if="data && data.length > 0" style="height: 300px">
      <canvas ref="chartRef"></canvas>
    </div>
    
    <div v-else class="text-center py-8">
      <i class="pi pi-chart-bar text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color">{{ t('dashboard.no_class_data') }}</p>
    </div>

    <!-- Class List -->
    <div v-if="data && data.length > 0" class="mt-6">
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div v-for="item in data" :key="item.class_name" class="p-3 bg-surface-50 dark:bg-surface-800 rounded-border">
          <div class="text-sm text-muted-color mb-1">{{ item.class_name }}</div>
          <div class="text-xl font-semibold text-surface-900 dark:text-surface-0">
            {{ item.student_count }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
