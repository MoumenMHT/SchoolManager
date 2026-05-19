<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import ParentService from '@/service/ParentService';
import PaymentService from '@/service/PaymentService';
import type { Parent } from '@/service/ParentService';

const { t } = useI18n();
const toast = useToast();

const parents = ref<Parent[]>([]);
const selectedParent = ref<Parent | null>(null);
const loadingParents = ref(false);
const loadingDetails = ref(false);
const parentDetails = ref<any>(null);
const contracts = ref<any[]>([]);
const unpaidBills = ref<any[]>([]);
const selectedContractId = ref<number | null>(null);
const paymentAmount = ref<number | null>(null);
const paymentType = ref('cash');
const paymentDate = ref<Date | null>(new Date());
const paymentNote = ref('');
const keepBalance = ref(true);
const calculationResult = ref<any>(null);
const calculating = ref(false);
const submitting = ref(false);
const submitted = ref(false);

const selectedContract = computed(() => {
  if (!selectedContractId.value) return null;
  return contracts.value.find(c => c.id === selectedContractId.value) || null;
});

const contractBills = computed(() => {
  if (!selectedContractId.value) return [];
  return unpaidBills.value.filter(b => b.contract_id === selectedContractId.value);
});

const totalUnpaidForSelected = computed(() => {
  return contractBills.value.reduce((sum: number, b: any) => sum + (b.amount_due - b.amount_paid), 0);
});

const totalContractAmount = computed(() => {
  if (!selectedContract.value) return 0;
  return (selectedContract.value.total_fees || 0) - (selectedContract.value.discount_value || 0);
});

const activeContracts = computed(() => {
  return contracts.value.filter((c: any) => c.status === 'active');
});

const canCalculate = computed(() => {
  return selectedContractId.value && paymentAmount.value && paymentAmount.value > 0;
});

const canSubmit = computed(() => {
  return calculationResult.value && selectedContractId.value && paymentAmount.value && paymentAmount.value > 0 && !calculating.value;
});

const effectiveAmount = computed(() => {
  if (!keepBalance.value && calculationResult.value) {
    return Math.min(paymentAmount.value || 0, calculationResult.value.will_be_allocated);
  }
  return paymentAmount.value;
});

const loadParents = async () => {
  loadingParents.value = true;
  try {
    parents.value = await ParentService.getParents();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_parents', 'Failed to load parents'), life: 3000 });
  } finally {
    loadingParents.value = false;
  }
};

// Normalize API numeric fields from strings to actual numbers to prevent
// "toFixed is not a function" crashes when the template renders them.
const normalizeContract = (c: any) => ({
  ...c,
  total_fees: Number(c.total_fees ?? 0),
  discount_value: Number(c.discount_value ?? 0),
  paid_amount: Number(c.paid_amount ?? 0),
  remaining_amount: Number(c.remaining_amount ?? 0),
  balance: Number(c.balance ?? 0),
  monthly_amount: Number(c.monthly_amount ?? 0),
});

const normalizeBill = (b: any) => ({
  ...b,
  amount_due: Number(b.amount_due ?? 0),
  amount_paid: Number(b.amount_paid ?? 0),
});

const onParentSelect = async () => {
  if (!selectedParent.value) return;
  // Defer state mutation until after PrimeVue Select overlay fully closes.
  // Using setTimeout(0) instead of nextTick because the overlay uses internal
  // animations that outlast a single Vue tick, causing the vnode null crash.
  await new Promise<void>(resolve => setTimeout(resolve, 0));
  loadingDetails.value = true;
  submitted.value = false;
  calculationResult.value = null;
  paymentAmount.value = null;
  paymentNote.value = '';
  selectedContractId.value = null;
  unpaidBills.value = [];
  contracts.value = [];
  parentDetails.value = null;
  try {
    parentDetails.value = await ParentService.getParent(selectedParent.value.id);
    const contractsData = await PaymentService.getContractsByParent(selectedParent.value.id);
    contracts.value = contractsData.map(normalizeContract);
    if (activeContracts.value.length > 0) {
      selectedContractId.value = activeContracts.value[0].id;
      await loadUnpaidBills();
    }
  } catch (err: any) {
    console.error('[PaymentForm] onParentSelect error:', err);
    toast.add({ severity: 'error', summary: t('common.error'), detail: err?.response?.data?.message || t('common.failed_to_load_parent_details', 'Failed to load parent details'), life: 3000 });
  } finally {
    loadingDetails.value = false;
  }
};

const loadUnpaidBills = async () => {
  if (!selectedContractId.value) return;
  try {
    const data = await PaymentService.getUnpaidBills(selectedContractId.value);
    const existingIds = new Set(unpaidBills.value.map((b: any) => b.id));
    for (const bill of data) {
      if (!existingIds.has(bill.id)) {
        unpaidBills.value.push(normalizeBill(bill));
      }
    }
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_bills', 'Failed to load bills'), life: 3000 });
  }
};

const onContractChange = async () => {
  calculationResult.value = null;
  paymentAmount.value = null;
  if (selectedContractId.value) {
    const alreadyLoaded = unpaidBills.value.some((b: any) => b.contract_id === selectedContractId.value);
    if (!alreadyLoaded) {
      await loadUnpaidBills();
    }
  }
};

const calculatePayment = async () => {
  if (!selectedContractId.value || !paymentAmount.value || paymentAmount.value <= 0) return;
  calculating.value = true;
  calculationResult.value = null;
  try {
    const result = await PaymentService.calculatePayment(selectedContractId.value, paymentAmount.value);
    calculationResult.value = result;
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_calculate_payment', 'Failed to calculate payment'), life: 3000 });
  } finally {
    calculating.value = false;
  }
};

const submitPayment = async () => {
  if (!selectedContractId.value || !paymentAmount.value || paymentAmount.value <= 0 || !calculationResult.value) return;
  submitting.value = true;
  try {
    let amount = paymentAmount.value;
    if (!keepBalance.value) {
      amount = Math.min(amount, calculationResult.value.will_be_allocated);
    }
    await PaymentService.processPayment({
      contract_id: selectedContractId.value,
      amount,
      payment_type: paymentType.value,
      paid_date: paymentDate.value ? new Date(paymentDate.value).toISOString().split('T')[0] : '',
      note: paymentNote.value || undefined,
    });
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.payment_processed_successfully', 'Payment processed successfully'), life: 3000 });
    submitted.value = true;
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err.response?.data?.message || t('common.failed_to_process_payment', 'Failed to process payment'), life: 3000 });
  } finally {
    submitting.value = false;
  }
};

const resetForm = () => {
  selectedParent.value = null;
  parentDetails.value = null;
  contracts.value = [];
  unpaidBills.value = [];
  selectedContractId.value = null;
  paymentAmount.value = null;
  paymentType.value = 'cash';
  paymentDate.value = new Date();
  paymentNote.value = '';
  keepBalance.value = true;
  calculationResult.value = null;
  submitted.value = false;
};

let calculateTimeout: ReturnType<typeof setTimeout> | null = null;

watch([paymentAmount, selectedContractId], () => {
  if (calculateTimeout) clearTimeout(calculateTimeout);
  
  if (!selectedContractId.value || !paymentAmount.value || paymentAmount.value <= 0) {
    calculationResult.value = null;
    calculating.value = false;
    return;
  }
  
  calculating.value = true;
  calculateTimeout = setTimeout(() => {
    calculatePayment();
  }, 500);
});

onMounted(loadParents);
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <div class="col-span-12">
      <div class="flex items-center gap-3 mb-6">
        <i class="pi pi-plus-circle text-2xl text-primary"></i>
        <h4 class="text-2xl font-semibold">{{ t('nav.process_payment', 'Process Payment') }}</h4>
      </div>
    </div>

    <div class="col-span-12 xl:col-span-4">
      <Panel :header="t('nav.select_parent', 'Select Parent')" class="mb-6">
        <div class="flex flex-col gap-4">
          <Select
            v-model="selectedParent"
            :options="parents"
            :filter="true"
            :filterFields="['first_name', 'last_name']"
            :placeholder="t('common.search_parent', 'Search parent by name...')"
            class="w-full"
            :loading="loadingParents"
            @change="onParentSelect"
          >
            <template #value="slotProps">
              <div v-if="slotProps.value" class="flex items-center gap-2">
                <i class="pi pi-user"></i>
                <div>{{ slotProps.value.first_name }} {{ slotProps.value.last_name }}</div>
              </div>
              <span v-else>{{ slotProps.placeholder }}</span>
            </template>
            <template #option="slotProps">
              <div class="flex items-center gap-2">
                <i class="pi pi-user text-lg"></i>
                <div>
                  <div class="font-medium">{{ slotProps.option.first_name }} {{ slotProps.option.last_name }}</div>
                  <div class="text-xs text-muted-color">{{ slotProps.option.students_count }} {{ t('common.students', 'students') }}</div>
                </div>
              </div>
            </template>
          </Select>

          <div v-if="loadingDetails" class="flex items-center justify-center gap-2 py-4">
            <i class="pi pi-spin pi-spinner text-xl text-primary"></i>
            <span class="text-muted-color">{{ t('common.loading', 'Loading...') }}</span>
          </div>

          <template v-if="parentDetails && !loadingDetails">
            <Divider />
            <div class="flex items-center gap-3">
              <i class="pi pi-user text-3xl text-primary bg-primary-100 dark:bg-primary-900/30 p-3 rounded-full"></i>
              <div>
                <h5 class="font-semibold text-lg">{{ parentDetails.first_name }} {{ parentDetails.last_name }}</h5>
                <p v-if="parentDetails.email" class="text-sm text-muted-color">{{ parentDetails.email }}</p>
                <p v-if="parentDetails.phone" class="text-sm text-muted-color">{{ parentDetails.phone }}</p>
              </div>
            </div>

            <div v-if="parentDetails.students?.length" class="mt-4">
              <h6 class="font-medium mb-2 text-sm text-muted-color uppercase tracking-wider">{{ t('nav.students', 'Students') }}</h6>
              <div v-for="student in parentDetails.students" :key="student.id" class="flex items-center gap-2 py-1">
                <i class="pi pi-graduation-cap text-primary"></i>
                <span>{{ student.first_name }} {{ student.last_name }}</span>
                <span v-if="student.class" class="text-xs bg-primary-100 dark:bg-primary-900/30 text-primary px-2 py-0.5 rounded-full">{{ student.class.name }}</span>
              </div>
            </div>
          </template>
        </div>
      </Panel>

      <Panel v-if="contracts.length > 0 && !loadingDetails" :header="t('nav.contracts', 'Contracts')" class="mb-6">
        <div class="flex flex-col gap-3">
          <div v-for="contract in contracts" :key="contract.id"
            class="p-3 rounded-lg border cursor-pointer transition-colors"
            :class="contract.id === selectedContractId
              ? 'border-primary bg-primary-50 dark:bg-primary-900/20'
              : 'border-surface-200 dark:border-surface-700 hover:border-primary'"
            @click="selectedContractId = contract.id; onContractChange()"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="font-medium">{{ contract.contract_number }}</span>
              <Tag :value="contract.status" :severity="contract.status === 'active' ? 'success' : contract.status === 'completed' ? 'info' : 'warn'" />
            </div>
            <div class="text-sm text-muted-color">{{ contract.academic_year }}</div>
            <div class="flex items-center justify-between mt-2 text-sm">
              <span>{{ t('common.total', 'Total') }}: {{ (contract.total_fees - (contract.discount_value || 0)).toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}</span>
              <span>{{ t('common.remaining', 'Remaining') }}: <span class="font-semibold">{{ contract.remaining_amount.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}</span></span>
            </div>
            <div v-if="contract.balance > 0" class="text-xs text-green-600 mt-1">
              <i class="pi pi-info-circle"></i> {{ t('common.balance', 'Balance') }}: {{ contract.balance.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}
            </div>
          </div>
        </div>
      </Panel>
    </div>

    <div class="col-span-12 xl:col-span-8">
      <Panel v-if="selectedContract" :header="t('nav.payment_details', 'Payment Details')">
        <template v-if="submitted">
          <Message severity="success" class="mb-4">
            <div class="flex flex-col gap-1">
              <span class="font-semibold">{{ t('common.payment_successful', 'Payment processed successfully!') }}</span>
              <span>{{ t('common.payment_success_message', 'The payment has been recorded and allocated to the unpaid bills.') }}</span>
            </div>
          </Message>
          <Button :label="t('common.process_another', 'Process Another Payment')" icon="pi pi-refresh" @click="resetForm" />
        </template>

        <template v-else>
          <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-4">
              <div class="p-4 rounded-lg bg-surface-50 dark:bg-surface-800 text-center">
                <div class="text-sm text-muted-color mb-1">{{ t('common.contract_total', 'Contract Total') }}</div>
                <div class="text-2xl font-bold text-primary">{{ totalContractAmount.toFixed(2) }}</div>
                <div class="text-xs text-muted-color">{{ t('common.currency_dzd', 'DZD') }}</div>
              </div>
            </div>
            <div class="col-span-12 md:col-span-4">
              <div class="p-4 rounded-lg bg-surface-50 dark:bg-surface-800 text-center">
                <div class="text-sm text-muted-color mb-1">{{ t('common.paid', 'Paid') }}</div>
                <div class="text-2xl font-bold text-green-600">{{ (selectedContract.paid_amount || 0).toFixed(2) }}</div>
                <div class="text-xs text-muted-color">{{ t('common.currency_dzd', 'DZD') }}</div>
              </div>
            </div>
            <div class="col-span-12 md:col-span-4">
              <div class="p-4 rounded-lg bg-surface-50 dark:bg-surface-800 text-center">
                <div class="text-sm text-muted-color mb-1">{{ t('common.remaining_short', 'Remaining') }}</div>
                <div class="text-2xl font-bold text-orange-600">{{ (selectedContract.remaining_amount || 0).toFixed(2) }}</div>
                <div class="text-xs text-muted-color">{{ t('common.currency_dzd', 'DZD') }}</div>
              </div>
            </div>
          </div>

          <Divider />

          <h6 class="font-medium mb-3 text-sm text-muted-color uppercase tracking-wider">{{ t('common.unpaid_bills', 'Unpaid / Late Bills') }}</h6>
          <DataTable :value="contractBills" class="mb-4" stripedRows :size="'small'">
            <Column field="month_year" :header="t('common.month', 'Month')" />
            <Column field="amount_due" :header="t('common.amount_due', 'Due')">
              <template #body="{ data }">
                {{ data.amount_due.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}
              </template>
            </Column>
            <Column field="amount_paid" :header="t('common.paid', 'Paid')">
              <template #body="{ data }">
                {{ data.amount_paid.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}
              </template>
            </Column>
            <Column field="balance" :header="t('common.balance_short', 'Balance')">
              <template #body="{ data }">
                {{ (data.amount_due - data.amount_paid).toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}
              </template>
            </Column>
            <Column field="status" :header="t('common.status', 'Status')">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="data.status === 'unpaid' ? 'warn' : data.status === 'late' ? 'danger' : 'info'" />
              </template>
            </Column>
            <Column field="due_date" :header="t('common.due_date', 'Due Date')">
              <template #body="{ data }">
                {{ data.due_date ? data.due_date.split('T')[0] : '' }}
              </template>
            </Column>
          </DataTable>

          <div v-if="contractBills.length === 0" class="text-center py-4 text-muted-color">
            <i class="pi pi-check-circle text-green-500 text-xl"></i>
            <p class="mt-1">{{ t('common.no_unpaid_bills', 'No unpaid bills for this contract') }}</p>
          </div>

          <Divider />

          <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-6">
              <label class="block font-medium mb-2">{{ t('common.payment_amount', 'Payment Amount') }} *</label>
              <InputNumber
                v-model="paymentAmount"
                :min="0"
                mode="decimal"
                :minFractionDigits="2"
                :maxFractionDigits="2"
                class="w-full"
                :placeholder="t('common.enter_amount', 'Enter amount...')"
              />
            </div>
            <div class="col-span-12 md:col-span-3">
              <label class="block font-medium mb-2">{{ t('common.payment_type', 'Payment Type') }} *</label>
              <Select
                v-model="paymentType"
                :options="[
                  { label: t('common.cash', 'Cash'), value: 'cash' },
                  { label: t('common.bank_transfer', 'Bank Transfer'), value: 'bank_transfer' },
                  { label: t('common.cheque', 'Cheque'), value: 'cheque' },
                  { label: t('common.online', 'Online'), value: 'online' }
                ]"
                optionLabel="label"
                optionValue="value"
                class="w-full"
              />
            </div>
            <div class="col-span-12 md:col-span-3">
              <label class="block font-medium mb-2">{{ t('common.payment_date', 'Payment Date') }} *</label>
              <DatePicker v-model="paymentDate" class="w-full" dateFormat="yy-mm-dd" />
            </div>
            <div class="col-span-12">
              <label class="block font-medium mb-2">{{ t('common.note', 'Note') }}</label>
              <Textarea v-model="paymentNote" :autoResize="true" rows="2" class="w-full" :placeholder="t('common.optional_note', 'Optional note...')" />
            </div>
          </div>

          <div v-if="calculating" class="flex items-center gap-2 mt-4 text-primary">
            <i class="pi pi-spin pi-spinner text-xl"></i>
            <span class="font-medium">{{ t('common.calculating', 'Calculating...') }}</span>
          </div>

          <template v-if="calculationResult">
            <Divider />
            <Message severity="info" class="mb-4">
              <div class="flex flex-col gap-1">
                <span class="font-semibold">{{ t('common.allocation_preview', 'Payment Allocation Preview') }}</span>
                <span>{{ t('common.will_be_allocated', 'Amount to allocate') }}: <strong>{{ calculationResult.will_be_allocated.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}</strong></span>
                <span v-if="calculationResult.overpayment > 0">
                  {{ t('common.overpayment', 'Overpayment / Balance') }}: <strong class="text-green-600">{{ calculationResult.overpayment.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}</strong>
                </span>
              </div>
            </Message>

            <DataTable :value="calculationResult.allocations" class="mb-4" stripedRows :size="'small'">
              <Column field="month_year" :header="t('common.month', 'Month')" />
              <Column field="current_balance" :header="t('common.bill_balance', 'Bill Balance')">
                <template #body="{ data }">
                  {{ data.current_balance.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}
                </template>
              </Column>
              <Column field="amount_to_allocate" :header="t('common.to_pay', 'To Pay')">
                <template #body="{ data }">
                  <span class="font-semibold text-primary">{{ data.amount_to_allocate.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}</span>
                </template>
              </Column>
              <Column field="remaining_balance" :header="t('common.after_payment', 'After Payment')">
                <template #body="{ data }">
                  {{ data.remaining_balance.toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}
                </template>
              </Column>
              <Column field="new_status" :header="t('common.new_status', 'New Status')">
                <template #body="{ data }">
                  <Tag :value="data.new_status" :severity="data.new_status === 'paid' ? 'success' : 'warn'" />
                </template>
              </Column>
            </DataTable>

            <div v-if="calculationResult.overpayment > 0" class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-700 mb-4">
              <Checkbox v-model="keepBalance" :binary="true" inputId="keepBalance" :disabled="calculationResult.overpayment <= 0" />
              <label for="keepBalance" class="cursor-pointer font-medium">
                <i class="pi pi-save mr-1"></i>
                {{ t('common.keep_balance', 'Keep the balance on the contract') }}
              </label>
            </div>

            <div class="flex items-center gap-4 mt-4">
              <Button
                :label="t('common.process_payment', 'Process Payment')"
                icon="pi pi-credit-card"
                :loading="submitting"
                :disabled="!canSubmit"
                severity="success"
                @click="submitPayment"
              />
              <Button
                :label="t('common.cancel', 'Cancel')"
                icon="pi pi-times"
                severity="secondary"
                @click="resetForm"
              />
            </div>
          </template>
        </template>
      </Panel>

      <Panel v-else-if="!selectedParent" :header="t('common.instructions', 'Instructions')">
        <div class="flex flex-col items-center gap-3 py-8 text-center">
          <i class="pi pi-info-circle text-4xl text-muted-color"></i>
          <p class="text-muted-color">{{ t('common.select_parent_first', 'Select a parent from the left panel to start processing a payment.') }}</p>
        </div>
      </Panel>

      <Panel v-else :header="t('common.no_active_contract', 'No Active Contract')">
        <div class="flex flex-col items-center gap-3 py-8 text-center">
          <i class="pi pi-exclamation-triangle text-4xl text-warn"></i>
          <p class="text-muted-color">{{ t('common.no_active_contract_msg', 'This parent does not have any active contract.') }}</p>
        </div>
      </Panel>
    </div>
  </div>
</template>
