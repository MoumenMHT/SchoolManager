<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import apiService from '@/service/ApiService';
import type { BillRecord } from '@/types';

const { t } = useI18n();
const toast = useToast();
const dt = ref();

const bills = ref<BillRecord[]>([]);
const loading = ref(false);
const filters = ref({ global: { value: null, matchMode: FilterMatchMode.CONTAINS } });

const statusFilter = ref<string | null>(null);
const contractIdFilter = ref<number | null>(null);

const fetchBills = async () => {
  loading.value = true;
  try {
    const params: any = {};
    if (statusFilter.value) params.status = statusFilter.value;
    if (contractIdFilter.value) params.contract_id = contractIdFilter.value;
    const response = await apiService.get<BillRecord[]>('/bills', params);
    bills.value = response.data || [];
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('bills.failed_load'), life: 3000 });
  } finally {
    loading.value = false;
  }
};

const statusSeverity = (status: string) => {
  switch (status) {
    case 'paid': return 'success';
    case 'partial': return 'warn';
    case 'late': return 'danger';
    case 'unpaid': return 'info';
    default: return 'info';
  }
};

const getParentName = (bill: BillRecord) => {
  const p = bill.contract?.parent;
  return p ? `${p.first_name} ${p.last_name}` : '-';
};

onMounted(fetchBills);
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <div class="col-span-12">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <i class="pi pi-file-pdf text-2xl text-primary"></i>
          <h4 class="text-2xl font-semibold">{{ t('nav.bills_management') }}</h4>
        </div>
        <div class="flex items-center gap-2">
          <Select v-model="statusFilter" :placeholder="t('common.all_statuses')" class="w-44"
            :options="[
              { label: t('common.all'), value: null },
              { label: t('common.unpaid'), value: 'unpaid' },
              { label: t('common.partial'), value: 'partial' },
              { label: t('common.paid'), value: 'paid' },
              { label: t('common.late'), value: 'late' },
            ]" optionLabel="label" optionValue="value" @change="fetchBills" />
          <Button icon="pi pi-refresh" severity="secondary" @click="fetchBills" v-tooltip.top="t('common.refresh')" />
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <DataTable
          ref="dt"
          :value="bills"
          :loading="loading"
          :filters="filters"
          :globalFilterFields="['month_year', 'contract.contract_number', 'status']"
          stripedRows
          :size="'small'"
          paginator
          :rows="20"
          :rowsPerPageOptions="[10, 20, 50]"
          sortField="due_date"
          :sortOrder="1"
        >
          <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
              <span class="p-input-icon-left">
                <i class="pi pi-search" />
                <InputText v-model="filters.global.value" :placeholder="t('common.search')" class="w-64" />
              </span>
              <div class="flex items-center gap-2 text-sm text-muted-color">
                <i class="pi pi-file"></i>
                <span>{{ bills.length }} {{ t('common.bills') }}</span>
              </div>
            </div>
          </template>

          <Column field="contract.contract_number" :header="t('common.contract')" sortable>
            <template #body="{ data }">
              <Tag :value="data.contract?.contract_number || '-'" severity="info" />
            </template>
          </Column>
          <Column :header="t('common.parent')">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i class="pi pi-user text-xs text-muted-color"></i>
                <span>{{ getParentName(data) }}</span>
              </div>
            </template>
          </Column>
          <Column field="month_year" :header="t('common.month')" sortable>
            <template #body="{ data }">
              <span class="font-medium">{{ data.month_year }}</span>
            </template>
          </Column>
          <Column field="amount_due" :header="t('common.amount_due')" sortable>
            <template #body="{ data }">
              <span class="font-semibold">{{ Number(data.amount_due).toFixed(2) }} DZD</span>
            </template>
          </Column>
          <Column field="amount_paid" :header="t('common.amount_paid')" sortable>
            <template #body="{ data }">
              <span class="text-green-600 font-medium">{{ Number(data.amount_paid).toFixed(2) }} DZD</span>
            </template>
          </Column>
          <Column :header="t('common.balance_short')" sortable>
            <template #body="{ data }">
              <span :class="(data.amount_due - data.amount_paid) > 0 ? 'text-orange-600 font-semibold' : 'text-green-600'">
                {{ (data.amount_due - data.amount_paid).toFixed(2) }} DZD
              </span>
            </template>
          </Column>
          <Column field="due_date" :header="t('common.due_date')" sortable>
            <template #body="{ data }">
              {{ data.due_date ? data.due_date.split('T')[0] : '' }}
            </template>
          </Column>
          <Column field="status" :header="t('common.status')" sortable>
            <template #body="{ data }">
              <Tag :value="data.status" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </div>
</template>
