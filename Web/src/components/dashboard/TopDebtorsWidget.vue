<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ContractSummary } from '@/types';

interface Props {
  contracts: ContractSummary[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const topDebtors = computed(() =>
  [...props.contracts]
    .filter(c => c.remaining_amount > 0)
    .sort((a, b) => b.remaining_amount - a.remaining_amount)
    .slice(0, 10)
);

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'DZD', minimumFractionDigits: 0 }).format(v);

const debtSeverity = (pct: number) => {
  if (pct < 30) return 'danger';
  if (pct < 60) return 'warn';
  return 'secondary';
};
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h5 class="text-xl font-semibold">{{ t('dashboard.top_debtors') }}</h5>
        <p class="text-sm text-muted-color mt-1">{{ t('dashboard.top_debtors_subtitle') }}</p>
      </div>
      <i class="pi pi-sort-amount-down text-2xl text-primary"></i>
    </div>

    <div v-if="topDebtors.length > 0" class="space-y-3">
      <div
        v-for="(c, i) in topDebtors"
        :key="c.contract_number"
        class="flex items-center gap-4 p-3 bg-surface-50 dark:bg-surface-800 rounded-border"
      >
        <!-- Rank badge -->
        <div
          class="flex items-center justify-center rounded-full font-bold text-sm flex-shrink-0"
          :class="i === 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' :
                  i === 1 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' :
                  'bg-surface-200 text-surface-600 dark:bg-surface-700 dark:text-surface-300'"
          style="width:32px;height:32px"
        >
          {{ i + 1 }}
        </div>

        <!-- Name + contract -->
        <div class="flex-1 min-w-0">
          <div class="font-medium text-sm truncate">{{ c.parent_name }}</div>
          <div class="text-xs text-muted-color font-mono">{{ c.contract_number }}</div>
        </div>

        <!-- Progress + amount -->
        <div class="text-right flex-shrink-0" style="min-width:140px">
          <div class="flex items-center gap-2 justify-end mb-1">
            <Tag
              :value="`${c.payment_completion.toFixed(0)}%`"
              :severity="debtSeverity(c.payment_completion)"
            />
            <span class="text-sm font-semibold text-red-600 dark:text-red-400">
              {{ formatCurrency(c.remaining_amount) }}
            </span>
          </div>
          <ProgressBar
            :value="Math.min(c.payment_completion, 100)"
            :showValue="false"
            style="height:6px"
          />
        </div>
      </div>
    </div>

    <div v-else class="text-center py-10">
      <i class="pi pi-check-circle text-4xl text-green-500 mb-3"></i>
      <p class="font-semibold text-green-700 dark:text-green-400">{{ t('dashboard.all_paid') }}</p>
    </div>
  </div>
</template>
