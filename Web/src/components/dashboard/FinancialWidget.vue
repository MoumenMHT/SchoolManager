<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  financial: {
    total_revenue: number;
    pending_payments: number;
    late_payments: number;
    payment_rate: number;
  };
}

const props = defineProps<Props>();

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'DZD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount);
};

const paymentRateColor = computed(() => {
  if (props.financial.payment_rate >= 80) return 'text-green-600';
  if (props.financial.payment_rate >= 60) return 'text-orange-600';
  return 'text-red-600';
});

const paymentRateSeverity = computed(() => {
  if (props.financial.payment_rate >= 80) return 'success';
  if (props.financial.payment_rate >= 60) return 'warning';
  return 'danger';
});
</script>

<template>
  <div class="card mb-8">
    <div class="flex items-center justify-between mb-6">
      <h5 class="text-xl font-semibold">Financial Overview</h5>
      <i class="pi pi-dollar text-2xl text-primary"></i>
    </div>

    <div class="grid gap-6">
      <!-- Total Revenue -->
      <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-border border border-green-200 dark:border-green-800">
        <div>
          <div class="text-sm text-green-700 dark:text-green-300 mb-2">Total Revenue</div>
          <div class="text-2xl font-bold text-green-900 dark:text-green-100">
            {{ formatCurrency(financial.total_revenue) }}
          </div>
        </div>
        <div class="flex items-center justify-center bg-green-100 dark:bg-green-800/30 rounded-full" style="width: 3.5rem; height: 3.5rem">
          <i class="pi pi-check-circle text-green-600 dark:text-green-400 text-2xl"></i>
        </div>
      </div>

      <!-- Pending Payments -->
      <div class="flex items-center justify-between p-4 bg-orange-50 dark:bg-orange-900/20 rounded-border border border-orange-200 dark:border-orange-800">
        <div>
          <div class="text-sm text-orange-700 dark:text-orange-300 mb-2">Pending Payments</div>
          <div class="text-2xl font-bold text-orange-900 dark:text-orange-100">
            {{ formatCurrency(financial.pending_payments) }}
          </div>
        </div>
        <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-800/30 rounded-full" style="width: 3.5rem; height: 3.5rem">
          <i class="pi pi-clock text-orange-600 dark:text-orange-400 text-2xl"></i>
        </div>
      </div>

      <!-- Late Payments -->
      <div class="flex items-center justify-between p-4 bg-red-50 dark:bg-red-900/20 rounded-border border border-red-200 dark:border-red-800">
        <div>
          <div class="text-sm text-red-700 dark:text-red-300 mb-2">Late Payments</div>
          <div class="text-2xl font-bold text-red-900 dark:text-red-100">
            {{ formatCurrency(financial.late_payments) }}
          </div>
        </div>
        <div class="flex items-center justify-center bg-red-100 dark:bg-red-800/30 rounded-full" style="width: 3.5rem; height: 3.5rem">
          <i class="pi pi-exclamation-triangle text-red-600 dark:text-red-400 text-2xl"></i>
        </div>
      </div>

      <!-- Payment Rate -->
      <div class="p-4 bg-surface-50 dark:bg-surface-800 rounded-border">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium">Payment Collection Rate</span>
          <Tag 
            :value="`${financial.payment_rate.toFixed(1)}%`" 
            :severity="paymentRateSeverity"
          />
        </div>
        <ProgressBar 
          :value="financial.payment_rate" 
          :showValue="false"
          :class="paymentRateColor"
        />
        <div class="flex justify-between mt-2 text-xs text-muted-color">
          <span>0%</span>
          <span>100%</span>
        </div>
      </div>
    </div>
  </div>
</template>
