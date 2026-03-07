<script setup lang="ts">
import type { FinancialReport } from '@/types';

interface Props {
  report: FinancialReport;
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
</script>

<template>
  <div class="col-span-12">
    <div class="grid grid-cols-12 gap-6">
      <!-- Total Payments Count -->
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-muted-color">Total Transactions</span>
            <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-receipt text-blue-600 dark:text-blue-400"></i>
            </div>
          </div>
          <div class="text-3xl font-bold text-surface-900 dark:text-surface-0">
            {{ report.total_payments }}
          </div>
          <div class="text-sm text-muted-color mt-2">Payments recorded</div>
        </div>
      </div>

      <!-- Total Collected -->
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-muted-color">Total Collected</span>
            <div class="flex items-center justify-center bg-green-100 dark:bg-green-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-arrow-down-right text-green-600 dark:text-green-400"></i>
            </div>
          </div>
          <div class="text-3xl font-bold text-green-700 dark:text-green-400">
            {{ formatCurrency(report.total_amount_collected) }}
          </div>
          <div class="text-sm text-muted-color mt-2">Completed payments</div>
        </div>
      </div>

      <!-- Total Refunds -->
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-muted-color">Total Refunds</span>
            <div class="flex items-center justify-center bg-red-100 dark:bg-red-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-arrow-up-left text-red-600 dark:text-red-400"></i>
            </div>
          </div>
          <div class="text-3xl font-bold text-red-700 dark:text-red-400">
            {{ formatCurrency(Math.abs(report.total_refunds)) }}
          </div>
          <div class="text-sm text-muted-color mt-2">Refunded to parents</div>
        </div>
      </div>

      <!-- Net Amount -->
      <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-medium text-muted-color">Net Revenue</span>
            <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-800/30 rounded-full" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-wallet text-purple-600 dark:text-purple-400"></i>
            </div>
          </div>
          <div class="text-3xl font-bold text-purple-700 dark:text-purple-400">
            {{ formatCurrency(report.net_amount) }}
          </div>
          <div class="text-sm text-muted-color mt-2">After refunds</div>
        </div>
      </div>
    </div>
  </div>
</template>
