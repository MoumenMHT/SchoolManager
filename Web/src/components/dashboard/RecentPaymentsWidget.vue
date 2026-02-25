<script setup lang="ts">
import type { Payment } from '@/types';

interface Props {
  payments: Payment[];
}

defineProps<Props>();

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'DZD',
    minimumFractionDigits: 2
  }).format(amount);
};

const getPaymentTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    tuition: 'Tuition',
    transport: 'Transport',
    lunch: 'Lunch',
    other: 'Other'
  };
  return labels[type] || type;
};

const getPaymentTypeSeverity = (type: string) => {
  const severities: Record<string, string> = {
    tuition: 'success',
    transport: 'info',
    lunch: 'warning',
    other: 'secondary'
  };
  return severities[type] || 'secondary';
};
</script>

<template>
  <div class="card mb-8">
    <div class="flex items-center justify-between mb-6">
      <h5 class="text-xl font-semibold">Recent Payments</h5>
      <Button label="View All" icon="pi pi-arrow-right" iconPos="right" text />
    </div>

    <DataTable :value="payments" :rows="5" :paginator="payments.length > 5" responsiveLayout="scroll">
      <Column field="student.first_name" header="Student">
        <template #body="{ data }">
          <div v-if="data.student">
            {{ data.student.first_name }} {{ data.student.last_name }}
          </div>
          <div v-else class="text-muted-color">N/A</div>
        </template>
      </Column>
      
      <Column field="payment_type" header="Type">
        <template #body="{ data }">
          <Tag 
            :value="getPaymentTypeLabel(data.payment_type)" 
            :severity="getPaymentTypeSeverity(data.payment_type)"
          />
        </template>
      </Column>
      
      <Column field="amount" header="Amount">
        <template #body="{ data }">
          <span class="font-semibold">{{ formatCurrency(data.amount) }}</span>
        </template>
      </Column>
      
      <Column field="paid_date" header="Date">
        <template #body="{ data }">
          {{ formatDate(data.paid_date) }}
        </template>
      </Column>
      
      <Column field="status" header="Status">
        <template #body="{ data }">
          <Tag value="Paid" severity="success" />
        </template>
      </Column>
    </DataTable>

    <div v-if="!payments || payments.length === 0" class="text-center py-8">
      <i class="pi pi-inbox text-4xl text-muted-color mb-3"></i>
      <p class="text-muted-color">No recent payments found</p>
    </div>
  </div>
</template>
