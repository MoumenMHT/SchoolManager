<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import PaymentStatsWidget from '@/components/dashboard/PaymentStatsWidget.vue';
import PaymentByTypeWidget from '@/components/dashboard/PaymentByTypeWidget.vue';
import ContractPaymentWidget from '@/components/dashboard/ContractPaymentWidget.vue';
import MonthlyRevenueWidget from '@/components/dashboard/MonthlyRevenueWidget.vue';
import BillStatusWidget from '@/components/dashboard/BillStatusWidget.vue';
import OverdueBillsWidget from '@/components/dashboard/OverdueBillsWidget.vue';
import TopDebtorsWidget from '@/components/dashboard/TopDebtorsWidget.vue';
import UpcomingDuesWidget from '@/components/dashboard/UpcomingDuesWidget.vue';
import PaymentHistoryWidget from '@/components/dashboard/PaymentHistoryWidget.vue';
import dashboardService from '@/service/DashboardService';
import type { FinancialReport, BillRecord, PaymentRecord } from '@/types';

const { t } = useI18n();

const financialReport  = ref<FinancialReport | null>(null);
const allBills         = ref<BillRecord[]>([]);
const allPayments      = ref<PaymentRecord[]>([]);

const paymentLoading  = ref(true);
const billsLoading    = ref(true);

const paymentError    = ref<string | null>(null);
const billsError      = ref<string | null>(null);

const getAcademicYear = () => {
  const now = new Date();
  const currentYear = now.getFullYear();
  const startYear = now.getMonth() < 8 ? currentYear - 1 : currentYear;
  return `${startYear}-${startYear + 1}`;
};

const loadPaymentReport = async () => {
  try {
    paymentLoading.value = true;
    paymentError.value = null;
    financialReport.value = await dashboardService.getFinancialReports({ academic_year: getAcademicYear() });
  } catch (err: any) {
    paymentError.value = err.response?.data?.message || t('dashboard.failed_payment');
    console.error('Payment report error:', err);
  } finally {
    paymentLoading.value = false;
  }
};

const loadBillsAndPayments = async () => {
  try {
    billsLoading.value = true;
    billsError.value = null;
    const [bills, payments] = await Promise.all([
      dashboardService.getAllBills(),
      dashboardService.getAllPayments(),
    ]);
    allBills.value    = bills;
    allPayments.value = payments;
  } catch (err: any) {
    billsError.value = err.response?.data?.message || t('dashboard.failed_bills');
    console.error('Bills/payments error:', err);
  } finally {
    billsLoading.value = false;
  }
};

onMounted(() => {
  loadPaymentReport();
  loadBillsAndPayments();
});
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <div class="col-span-12">
      <div class="flex items-center gap-3 mb-2">
        <i class="pi pi-credit-card text-2xl text-primary"></i>
        <h4 class="text-2xl font-semibold">{{ t('dashboard.payment_dashboard') }}</h4>
      </div>
      <Divider />
    </div>

    <!-- Payment Loading -->
    <div v-if="paymentLoading" class="col-span-12 text-center py-6">
      <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
      <p class="mt-3 text-muted-color">{{ t('dashboard.loading_payment') }}</p>
    </div>

    <!-- Payment Error -->
    <div v-else-if="paymentError" class="col-span-12">
      <div class="card bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
        <div class="flex items-center gap-3">
          <i class="pi pi-exclamation-circle text-orange-600 text-2xl"></i>
          <div>
            <h3 class="text-orange-900 dark:text-orange-100 font-semibold">{{ t('dashboard.payment_unavailable') }}</h3>
            <p class="text-orange-700 dark:text-orange-300">{{ paymentError }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 1: Summary stats -->
    <template v-else-if="financialReport">
      <PaymentStatsWidget :report="financialReport" />

      <!-- Row 2: Payment method breakdown + contract table -->
      <div class="col-span-12 xl:col-span-5">
        <PaymentByTypeWidget :report="financialReport" />
      </div>
      <div class="col-span-12 xl:col-span-7">
        <ContractPaymentWidget :report="financialReport" />
      </div>
    </template>

    <!-- Bills & Payments loading -->
    <div v-if="billsLoading" class="col-span-12 flex items-center justify-center gap-3 py-4">
      <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
      <span class="text-muted-color">{{ t('dashboard.loading_bills') }}</span>
    </div>

    <!-- Bills & Payments error -->
    <div v-else-if="billsError" class="col-span-12">
      <div class="card bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
        <div class="flex items-center gap-3">
          <i class="pi pi-exclamation-circle text-orange-600 text-2xl"></i>
          <p class="text-orange-700 dark:text-orange-300">{{ billsError }}</p>
        </div>
      </div>
    </div>

    <template v-else>
      <!-- Row 3: Monthly revenue chart (full width) -->
      <div class="col-span-12">
        <MonthlyRevenueWidget :payments="allPayments" />
      </div>

      <!-- Row 4: Bill status donut + overdue bills table -->
      <div class="col-span-12 xl:col-span-5">
        <BillStatusWidget :bills="allBills" />
      </div>
      <div class="col-span-12 xl:col-span-7">
        <OverdueBillsWidget :bills="allBills" />
      </div>

      <!-- Row 5: Top debtors + upcoming dues -->
      <div class="col-span-12 xl:col-span-6">
        <TopDebtorsWidget
          v-if="financialReport"
          :contracts="financialReport.contracts_summary"
        />
      </div>
      <div class="col-span-12 xl:col-span-6">
        <UpcomingDuesWidget :bills="allBills" />
      </div>

      <!-- Row 6: Payment history table (full width) -->
      <div class="col-span-12">
        <PaymentHistoryWidget :payments="allPayments" />
      </div>
    </template>
  </div>
</template>
