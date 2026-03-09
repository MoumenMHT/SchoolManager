<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PaymentRecord } from '@/types';

interface Props {
  payments: PaymentRecord[];
  limit?: number;
}

const props = withDefaults(defineProps<Props>(), { limit: 20 });
const { t } = useI18n();

const recent = computed(() =>
  [...props.payments]
    .filter(p => p.status === 'completed' || p.status === 'refunded')
    .sort((a, b) => new Date(b.paid_date).getTime() - new Date(a.paid_date).getTime())
    .slice(0, props.limit)
);

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'DZD', minimumFractionDigits: 0 }).format(v);

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

const parentName = (p: PaymentRecord) =>
  p.contract?.parent
    ? `${p.contract.parent.first_name} ${p.contract.parent.last_name}`
    : '—';

const statusSeverity = (status: string) =>
  status === 'completed' ? 'success' : status === 'refunded' ? 'danger' : 'secondary';

const typeIcon = (type: string) => {
  const map: Record<string, string> = {
    cash:      'pi-wallet',
    cheque:    'pi-file',
    virement:  'pi-arrow-right-arrow-left',
    card:      'pi-credit-card',
  };
  return map[type] ?? 'pi-circle';
};
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h5 class="text-xl font-semibold flex items-center gap-2">
          <i class="pi pi-history text-green-500"></i>
          {{ t('dashboard.payment_history') }}
        </h5>
        <p class="text-sm text-muted-color mt-1">{{ t('dashboard.last_transactions', { count: recent.length }) }}</p>
      </div>
      <Tag :value="`${payments.length} total`" severity="success" />
    </div>

    <DataTable
      :value="recent"
      :rows="10"
      :paginator="recent.length > 10"
      paginator-template="PrevPageLink PageLinks NextPageLink"
      :rows-per-page-options="[10, 20]"
      size="small"
      stripedRows
    >
      <Column :header="t('dashboard.parent_col')" style="min-width:140px">
        <template #body="{ data }">
          <span class="font-medium text-sm">{{ parentName(data) }}</span>
        </template>
      </Column>

      <Column :header="t('dashboard.contract_col')" style="min-width:130px">
        <template #body="{ data }">
          <span class="text-xs font-mono text-muted-color">{{ data.contract?.contract_number ?? '—' }}</span>
        </template>
      </Column>

      <Column :header="t('dashboard.type_col')" style="min-width:110px">
        <template #body="{ data }">
          <span class="flex items-center gap-1 text-sm capitalize">
            <i :class="`pi ${typeIcon(data.payment_type)} text-xs`"></i>
            {{ data.payment_type }}
          </span>
        </template>
      </Column>

      <Column :header="t('dashboard.date_col')" style="min-width:110px">
        <template #body="{ data }">
          <span class="text-sm text-muted-color">{{ formatDate(data.paid_date) }}</span>
        </template>
      </Column>

      <Column :header="t('dashboard.amount_col')" style="min-width:110px">
        <template #body="{ data }">
          <span
            class="text-sm font-semibold"
            :class="data.status === 'refunded' ? 'text-red-500' : 'text-green-600 dark:text-green-400'"
          >
            {{ data.status === 'refunded' ? '-' : '' }}{{ formatCurrency(data.amount) }}
          </span>
        </template>
      </Column>

      <Column :header="t('common.status')" style="min-width:90px">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-inbox text-3xl text-muted-color mb-2"></i>
          <p class="text-muted-color">{{ t('dashboard.no_payment_records') }}</p>
        </div>
      </template>
    </DataTable>
  </div>
</template>
