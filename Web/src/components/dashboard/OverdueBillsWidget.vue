<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BillRecord } from '@/types';

interface Props {
  bills: BillRecord[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const overdueBills = computed(() =>
  props.bills
    .filter(b => b.status === 'late')
    .map(b => {
      const daysOverdue = Math.floor(
        (Date.now() - new Date(b.due_date).getTime()) / 86_400_000
      );
      return { ...b, daysOverdue };
    })
    .sort((a, b) => b.daysOverdue - a.daysOverdue)
);

const totalAtRisk = computed(() =>
  overdueBills.value.reduce((s, b) => s + Number(b.balance), 0)
);

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'DZD', minimumFractionDigits: 0 }).format(v);

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

const urgencySeverity = (days: number) => {
  if (days > 60) return 'danger';
  if (days > 30) return 'warn';
  return 'secondary';
};

const parentName = (b: BillRecord) =>
  b.contract?.parent
    ? `${b.contract.parent.first_name} ${b.contract.parent.last_name}`
    : '—';
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h5 class="text-xl font-semibold flex items-center gap-2">
          <i class="pi pi-exclamation-triangle text-red-500"></i>
          {{ t('dashboard.overdue_bills') }}
        </h5>
        <p class="text-sm text-muted-color mt-1">
          {{ overdueBills.length }} {{ overdueBills.length === 1 ? t('dashboard.bill_singular') : t('dashboard.bills_plural') }} ·
          <span class="font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(totalAtRisk) }}</span> {{ t('dashboard.at_risk') }}
        </p>
      </div>
      <Tag
        :value="`${overdueBills.length} overdue`"
        :severity="overdueBills.length > 0 ? 'danger' : 'success'"
      />
    </div>

    <DataTable
      v-if="overdueBills.length > 0"
      :value="overdueBills"
      :rows="8"
      :paginator="overdueBills.length > 8"
      responsiveLayout="scroll"
      :rowHover="true"
      size="small"
    >
      <Column :header="t('dashboard.parent_col')" style="min-width:140px">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <div class="flex items-center justify-center bg-red-100 dark:bg-red-900/30 rounded-full" style="width:28px;height:28px;flex-shrink:0">
              <i class="pi pi-user text-xs text-red-600 dark:text-red-400"></i>
            </div>
            <div>
              <div class="text-sm font-medium">{{ parentName(data) }}</div>
              <div class="text-xs text-muted-color font-mono">{{ data.contract?.contract_number }}</div>
            </div>
          </div>
        </template>
      </Column>

      <Column field="month_year" :header="t('dashboard.month_col')" style="min-width:100px">
        <template #body="{ data }">
          <span class="text-sm">{{ data.month_year }}</span>
        </template>
      </Column>

      <Column field="due_date" :header="t('dashboard.due_date_col')" style="min-width:110px">
        <template #body="{ data }">
          <span class="text-sm text-red-600 dark:text-red-400">{{ formatDate(data.due_date) }}</span>
        </template>
      </Column>

      <Column field="balance" :header="t('dashboard.outstanding_col')" style="min-width:120px">
        <template #body="{ data }">
          <span class="font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(data.balance) }}</span>
        </template>
      </Column>

      <Column :header="t('dashboard.days_late_col')" style="min-width:110px">
        <template #body="{ data }">
          <Tag
            :value="`${data.daysOverdue}d`"
            :severity="urgencySeverity(data.daysOverdue)"
          />
        </template>
      </Column>
    </DataTable>

    <div v-else class="text-center py-10">
      <i class="pi pi-check-circle text-4xl text-green-500 mb-3"></i>
      <p class="font-semibold text-green-700 dark:text-green-400">{{ t('dashboard.no_overdue') }}</p>
      <p class="text-sm text-muted-color mt-1">{{ t('dashboard.all_up_to_date') }}</p>
    </div>
  </div>
</template>
