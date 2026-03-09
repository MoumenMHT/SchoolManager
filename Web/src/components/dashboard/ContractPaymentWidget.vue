<script setup lang="ts">
import { computed } from 'vue';
import type { FinancialReport } from '@/types';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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

const completionSeverity = (pct: number) => {
  if (pct >= 80) return 'success';
  if (pct >= 50) return 'warn';
  return 'danger';
};

const completionColor = (pct: number) => {
  if (pct >= 80) return 'text-green-600';
  if (pct >= 50) return 'text-orange-500';
  return 'text-red-500';
};

const sortedContracts = computed(() =>
  [...props.report.contracts_summary].sort((a, b) => a.payment_completion - b.payment_completion)
);
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h5 class="text-xl font-semibold">{{ t('dashboard.contract_payment_status') }}</h5>
      <i class="pi pi-file-edit text-2xl text-primary"></i>
    </div>

    <DataTable
      v-if="sortedContracts.length > 0"
      :value="sortedContracts"
      :rows="8"
      :paginator="sortedContracts.length > 8"
      responsiveLayout="scroll"
      :rowHover="true"
    >
      <Column field="contract_number" :header="t('dashboard.contract_number')" style="min-width: 130px">
        <template #body="{ data }">
          <span class="font-mono text-sm font-semibold text-primary">{{ data.contract_number }}</span>
        </template>
      </Column>

      <Column field="parent_name" :header="t('dashboard.parent_col')" style="min-width: 150px">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <div class="flex items-center justify-center bg-surface-200 dark:bg-surface-700 rounded-full" style="width: 2rem; height: 2rem; flex-shrink: 0">
              <i class="pi pi-user text-xs text-muted-color"></i>
            </div>
            <span class="text-sm">{{ data.parent_name }}</span>
          </div>
        </template>
      </Column>

      <Column field="paid_amount" :header="t('dashboard.paid_col')" style="min-width: 120px">
        <template #body="{ data }">
          <span class="font-semibold text-green-600 dark:text-green-400">{{ formatCurrency(data.paid_amount) }}</span>
        </template>
      </Column>

      <Column field="remaining_amount" :header="t('dashboard.remaining_col')" style="min-width: 130px">
        <template #body="{ data }">
          <span
            class="font-semibold"
            :class="data.remaining_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
          >
            {{ data.remaining_amount > 0 ? formatCurrency(data.remaining_amount) : t('dashboard.settled') }}
          </span>
        </template>
      </Column>

      <Column field="payment_completion" :header="t('dashboard.progress')" style="min-width: 180px">
        <template #body="{ data }">
          <div class="flex items-center gap-3">
            <div class="flex-1">
              <ProgressBar
                :value="Math.min(data.payment_completion, 100)"
                :showValue="false"
                style="height: 8px"
              />
            </div>
            <Tag
              :value="`${data.payment_completion.toFixed(0)}%`"
              :severity="completionSeverity(data.payment_completion)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <div v-else class="text-center py-8">
      <i class="pi pi-file text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color">{{ t('dashboard.no_contracts') }}</p>
    </div>
  </div>
</template>
