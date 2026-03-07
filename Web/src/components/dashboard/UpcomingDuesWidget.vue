<script setup lang="ts">
import { computed } from 'vue';
import type { BillRecord } from '@/types';

interface Props {
  bills: BillRecord[];
  daysAhead?: number;
}

const props = withDefaults(defineProps<Props>(), { daysAhead: 30 });

const upcomingBills = computed(() => {
  const now  = Date.now();
  const cap  = now + props.daysAhead * 86_400_000;

  return props.bills
    .filter(b => {
      if (b.status === 'paid') return false;
      const due = new Date(b.due_date).getTime();
      return due >= now && due <= cap;
    })
    .map(b => {
      const daysUntil = Math.ceil((new Date(b.due_date).getTime() - now) / 86_400_000);
      return { ...b, daysUntil };
    })
    .sort((a, b) => a.daysUntil - b.daysUntil);
});

const totalDue = computed(() =>
  upcomingBills.value.reduce((s, b) => s + Number(b.balance), 0)
);

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'DZD', minimumFractionDigits: 0 }).format(v);

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });

const urgencySeverity = (days: number) => {
  if (days <= 5)  return 'danger';
  if (days <= 15) return 'warn';
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
          <i class="pi pi-calendar-clock text-orange-500"></i>
          Upcoming Dues
        </h5>
        <p class="text-sm text-muted-color mt-1">
          Bills due in the next {{ daysAhead }} days ·
          <span class="font-semibold text-orange-500">{{ formatCurrency(totalDue) }}</span> expected
        </p>
      </div>
      <Tag :value="`${upcomingBills.length} due`" severity="warn" />
    </div>

    <div v-if="upcomingBills.length > 0" class="space-y-3 max-h-80 overflow-y-auto pr-1">
      <div
        v-for="b in upcomingBills"
        :key="b.id"
        class="flex items-center justify-between p-3 bg-surface-50 dark:bg-surface-800 rounded-border"
      >
        <div class="flex items-center gap-3 min-w-0">
          <div
            class="flex items-center justify-center rounded-full flex-shrink-0"
            :class="b.daysUntil <= 5
              ? 'bg-red-100 dark:bg-red-900/30'
              : b.daysUntil <= 15
                ? 'bg-orange-100 dark:bg-orange-900/30'
                : 'bg-surface-200 dark:bg-surface-700'"
            style="width:32px;height:32px"
          >
            <i
              class="pi pi-calendar text-xs"
              :class="b.daysUntil <= 5
                ? 'text-red-600 dark:text-red-400'
                : b.daysUntil <= 15
                  ? 'text-orange-500'
                  : 'text-surface-500'"
            ></i>
          </div>
          <div class="min-w-0">
            <div class="text-sm font-medium truncate">{{ parentName(b) }}</div>
            <div class="text-xs text-muted-color">{{ b.month_year }} · due {{ formatDate(b.due_date) }}</div>
          </div>
        </div>

        <div class="flex items-center gap-3 flex-shrink-0">
          <div class="text-right">
            <div class="text-sm font-semibold">{{ formatCurrency(b.balance) }}</div>
            <div class="text-xs text-muted-color">{{ b.status }}</div>
          </div>
          <Tag :value="`${b.daysUntil}d`" :severity="urgencySeverity(b.daysUntil)" />
        </div>
      </div>
    </div>

    <div v-else class="text-center py-10">
      <i class="pi pi-calendar-times text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color">No bills due in the next {{ daysAhead }} days</p>
    </div>
  </div>
</template>
