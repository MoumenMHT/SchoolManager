<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import ParentService from '@/service/ParentService';
import FeeService from '@/service/FeeService';
import ContractService from '@/service/ContractService';
import type { Parent } from '@/service/ParentService';
import type { Fee } from '@/service/FeeService';
import type { Contract } from '@/service/ContractService';
import { FilterMatchMode } from '@primevue/core/api';

const { t } = useI18n();
const toast = useToast();

const parents = ref<Parent[]>([]);
const availableFees = ref<Fee[]>([]);
const loading = ref(false);
const submitting = ref(false);
const submitted = ref(false);

const selectedParent = ref<Parent | null>(null);
const parentStudents = ref<any[]>([]);
const studentFees = ref<Record<number, number[]>>({});
const academicYear = ref('');
const startDate = ref<Date | null>(null);
const endDate = ref<Date | null>(null);
const discountType = ref<string>('');
const discountValue = ref(0);
const discountReason = ref('');
const notes = ref('');
const isActive = ref(true);
const editingContractId = ref<number | null>(null);

const currentView = ref<'create' | 'list'>('create');
const allContracts = ref<Contract[]>([]);
const loadingContracts = ref(false);
const contractsFilters = ref({ global: { value: null, matchMode: FilterMatchMode.CONTAINS } });
const totalRecords = ref(0);
const lazyParams = ref({ page: 1, per_page: 10 });

const totalFees = computed(() => {
  let sum = 0;
  for (const studentId in studentFees.value) {
    const feeIds = studentFees.value[studentId] || [];
    const fees = availableFees.value.filter(f => feeIds.includes(f.id));
    sum += fees.reduce((acc, f) => acc + Number(f.base_amount), 0);
  }
  return sum;
});

const discountAmount = computed(() => {
  if (!discountType.value) return 0;
  if (discountType.value === 'percentage') {
    return (totalFees.value * discountValue.value) / 100;
  }
  return discountValue.value;
});

const finalTotal = computed(() => {
  return Math.max(0, totalFees.value - discountAmount.value);
});

const canSubmit = computed(() => {
  return selectedParent.value && Object.values(studentFees.value).some(fees => fees.length > 0) && academicYear.value && startDate.value && endDate.value;
});

const getDefaultAcademicYear = () => {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();
  return m < 8 ? `${y - 1}-${y}` : `${y}-${y + 1}`;
};

onMounted(async () => {
  loading.value = true;
  try {
    // We no longer load parents on mount to save time.
    // AutoComplete will search on demand.
    academicYear.value = getDefaultAcademicYear();
    await loadFees();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_data', 'Failed to load data'), life: 3000 });
  } finally {
    loading.value = false;
  }
});

watch(selectedParent, async (newVal) => {
  // Guard: AutoComplete may temporarily set v-model to the typed string.
  // Only proceed when newVal is a real Parent object (has an id).
  if (newVal && typeof newVal !== 'object') return;

  if (newVal && (newVal as Parent).id && !editingContractId.value) {
    try {
      parentStudents.value = await ContractService.getParentStudentsWithFees((newVal as Parent).id);
      const newStudentFees: Record<number, number[]> = {};
      parentStudents.value.forEach(student => {
        const preassignedFees = (student.fees || []).map((f: any) => f.id);
        
        if (preassignedFees.length === 0 && student.class?.level_id) {
          availableFees.value.forEach(fee => {
            if (fee.levels && fee.levels.some((l: any) => l.id === student.class.level_id)) {
              preassignedFees.push(fee.id);
            }
          });
        }
        
        newStudentFees[student.id] = preassignedFees;
      });
      studentFees.value = newStudentFees;
    } catch (e) {
      console.error(e);
      toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_students', 'Failed to load students'), life: 3000 });
    }
  } else if (!newVal) {
    parentStudents.value = [];
    studentFees.value = {};
  }
});

const loadFees = async () => {
  try {
    const result = await FeeService.getAvailableForContract();
    availableFees.value = result.data;
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_fees', 'Failed to load fees'), life: 3000 });
  }
};

const submitContract = async () => {
  if (!canSubmit.value || !selectedParent.value) return;
  submitting.value = true;
  
  try {
    const payload = {
      parent_id: selectedParent.value.id,
      student_fees: Object.keys(studentFees.value).map(studentId => ({
        student_id: Number(studentId),
        fee_ids: studentFees.value[Number(studentId)]
      })).filter(sf => sf.fee_ids.length > 0),
      academic_year: academicYear.value,
      start_date: startDate.value ? new Date(startDate.value).toISOString().split('T')[0] : '',
      end_date: endDate.value ? new Date(endDate.value).toISOString().split('T')[0] : '',
      discount_type: discountType.value || null,
      discount_value: discountType.value ? discountValue.value : undefined,
      discount_reason: discountReason.value || undefined,
      notes: notes.value || undefined,
      is_active: isActive.value,
    };

    if (editingContractId.value) {
      const contract = await ContractService.updateContract(editingContractId.value, payload);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.contract_updated_msg', { number: contract.contract_number }, `Contract ${contract.contract_number} updated`), life: 4000 });
      submitted.value = true;
    } else {
      const contract = await ContractService.createContract(payload);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.contract_created_success_msg', { number: contract.contract_number }, `Contract ${contract.contract_number} created`), life: 4000 });
      submitted.value = true;
    }
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err.response?.data?.message || t('common.failed_to_save_contract', 'Failed to save contract'), life: 3000 });
  } finally {
    submitting.value = false;
  }
};

const resetForm = async () => {
  selectedParent.value = null;
  parentStudents.value = [];
  studentFees.value = {};
  startDate.value = null;
  endDate.value = null;
  discountType.value = '';
  discountValue.value = 0;
  discountReason.value = '';
  notes.value = '';
  isActive.value = true;
  editingContractId.value = null;
  submitted.value = false;
  // Reload only parents without an active contract for a fresh create session
  // Deferred to AutoComplete search.
};

const searchParents = async (event: any) => {
  try {
    const params: any = { search: event.query, per_page: 20 };
    // If we are in 'create' mode, only suggest parents without active contract
    if (!editingContractId.value) {
      params.without_active_contract = true;
    }
    const res = await ParentService.getParents(params);
    const fetchedParents = res.data || [];
    parents.value = fetchedParents.map((p: any) => ({ ...p, full_name: `${p.first_name} ${p.last_name}` }));
  } catch (err) {
    console.error(err);
  }
};

const openContractsList = () => {
  currentView.value = 'list';
  loadContractsLazy();
};

const loadContractsLazy = async () => {
  loadingContracts.value = true;
  try {
    const response = await ContractService.getContracts({
      page: lazyParams.value.page,
      per_page: lazyParams.value.per_page,
      search: contractsFilters.value.global.value || ''
    });
    if (response.data) {
      allContracts.value = response.data;
      totalRecords.value = response.total;
    } else {
      allContracts.value = response;
      totalRecords.value = response.length;
    }
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_contracts', 'Failed to load contracts'), life: 3000 });
  } finally {
    loadingContracts.value = false;
  }
};

let searchTimeout: ReturnType<typeof setTimeout>;
const onSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    lazyParams.value.page = 1;
    loadContractsLazy();
  }, 300);
};

const onPage = (event: any) => {
  lazyParams.value.page = event.page + 1;
  lazyParams.value.per_page = event.rows;
  loadContractsLazy();
};

const onRowClick = (event: any) => {
  editContract(event.data);
};

const editContract = async (contract: Contract) => {
  editingContractId.value = contract.id;
  currentView.value = 'create';
  submitted.value = false;

  // For editing, we don't load all parents.
  // We just set the selected parent object directly from the contract relation if available.
  const parent = contract.parent as unknown as Parent;
  selectedParent.value = parent ? { ...parent, full_name: `${parent.first_name} ${parent.last_name}` } : null;

  academicYear.value = contract.academic_year || getDefaultAcademicYear();

  if (contract.start_date) {
    startDate.value = new Date(contract.start_date) as any;
  }
  if (contract.end_date) {
    endDate.value = new Date(contract.end_date) as any;
  }

  discountType.value = contract.discount_type || '';
  discountValue.value = Number(contract.discount_value) || 0;
  discountReason.value = contract.discount_reason || '';
  notes.value = contract.notes || '';
  isActive.value = contract.is_active !== undefined ? Boolean(contract.is_active) : true;
  
  if (selectedParent.value) {
    try {
      parentStudents.value = await ContractService.getParentStudentsWithFees(selectedParent.value.id);
      
      const contractFees = contract.parent?.student_fees || [];
      const newStudentFees: Record<number, number[]> = {};
      
      parentStudents.value.forEach(student => {
        newStudentFees[student.id] = contractFees
          .filter((cf: any) => cf.student_id === student.id)
          .map((cf: any) => cf.fee_id);
      });
      studentFees.value = newStudentFees;
    } catch (e) {
      console.error(e);
    }
  } else {
    parentStudents.value = [];
    studentFees.value = {};
  }
};
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <div class="col-span-12">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <i class="pi text-2xl text-primary" :class="currentView === 'list' ? 'pi-list' : 'pi-file-edit'"></i>
          <h4 class="text-2xl font-semibold">
            {{ 
              currentView === 'list' 
                ? t('common.all_contracts', 'All Contracts') 
                : (editingContractId ? t('common.edit_contract', 'Edit Contract') : t('nav.create_contract', 'Create Contract')) 
            }}
          </h4>
        </div>
        <Button v-if="currentView === 'create'" :label="t('common.view_all_contracts', 'View All Contracts')" icon="pi pi-list" @click="openContractsList" />
        <Button v-else :label="t('nav.create_contract', 'Create Contract')" icon="pi pi-plus" @click="currentView = 'create'; resetForm()" />
      </div>
    </div>

    <!-- LIST VIEW -->
    <div v-if="currentView === 'list'" class="col-span-12">
      <div class="card">
        <DataTable
          :value="allContracts"
          lazy
          :totalRecords="totalRecords"
          @page="onPage($event)"
          :loading="loadingContracts"
          stripedRows
          :size="'small'"
          paginator
          :rows="lazyParams.per_page"
          :rowsPerPageOptions="[10, 20, 50]"
          @row-click="onRowClick"
          :rowClass="() => 'cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors'"
        >
          <template #header>
            <div class="flex items-center justify-end">
              <span class="p-input-icon-left">
                <i class="pi pi-search" />
                <InputText v-model="contractsFilters.global.value" @input="onSearch" :placeholder="t('common.search', 'Search...')" />
              </span>
            </div>
          </template>

          <Column field="contract_number" :header="t('common.number', 'Number')" sortable></Column>
          <Column :header="t('common.parent', 'Parent')" sortable field="parent.first_name">
            <template #body="{ data }">
              <div v-if="data.parent" class="flex items-center gap-2">
                <i class="pi pi-user text-xs text-muted-color"></i>
                <span>{{ data.parent.first_name }} {{ data.parent.last_name }}</span>
              </div>
              <span v-else>-</span>
            </template>
          </Column>
          <Column field="academic_year" :header="t('common.academic_year', 'Year')" sortable></Column>
          <Column field="total_fees" :header="t('common.total', 'Total Fees')" sortable>
            <template #body="{ data }">
              <span class="font-semibold">{{ Number(data.total_fees).toFixed(2) }} DZD</span>
            </template>
          </Column>
          <Column field="discount_value" :header="t('common.discount', 'Discount')">
            <template #body="{ data }">
              <span v-if="data.discount_value > 0" class="text-green-600">
                {{ data.discount_type === 'percentage' ? data.discount_value + '%' : Number(data.discount_value).toFixed(2) + ' DZD' }}
              </span>
              <span v-else>-</span>
            </template>
          </Column>
          <Column field="balance" :header="t('common.balance', 'Balance')" sortable>
            <template #body="{ data }">
              <span class="font-semibold" :class="data.balance > 0 ? 'text-green-600' : ''">
                {{ Number(data.balance || 0).toFixed(2) }} DZD
              </span>
            </template>
          </Column>
          <Column field="payment_status" :header="t('common.payment', 'Payment')">
            <template #body="{ data }">
              <Tag 
                :value="Number(data.remaining_amount) <= 0 ? t('common.paid', 'Paid') : (Number(data.paid_amount) > 0 ? t('common.partial', 'Partial') : t('common.unpaid', 'Unpaid'))" 
                :severity="Number(data.remaining_amount) <= 0 ? 'success' : (Number(data.paid_amount) > 0 ? 'info' : 'danger')" 
              />
            </template>
          </Column>
          <Column field="status" :header="t('common.status', 'Status')" sortable>
            <template #body="{ data }">
              <Tag :value="data.status" :severity="data.status === 'active' ? 'success' : data.status === 'completed' ? 'info' : 'warn'" />
            </template>
          </Column>
          <Column field="is_active" :header="t('common.active', 'Active')" sortable>
            <template #body="{ data }">
              <Tag
                :value="data.is_active ? t('common.active', 'Active') : t('common.inactive', 'Inactive')"
                :severity="data.is_active ? 'success' : 'secondary'"
                :icon="data.is_active ? 'pi pi-check-circle' : 'pi pi-times-circle'"
              />
            </template>
          </Column>
          <Column field="start_date" :header="t('common.start_date', 'Start Date')" sortable>
            <template #body="{ data }">
              <span v-if="data.start_date">{{ new Date(data.start_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
              <span v-else>-</span>
            </template>
          </Column>
          <Column field="end_date" :header="t('common.end_date', 'End Date')" sortable>
            <template #body="{ data }">
              <span v-if="data.end_date">{{ new Date(data.end_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
              <span v-else>-</span>
            </template>
          </Column>
          <Column :header="t('common.actions', 'Actions')" style="min-width: 8rem">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <Button icon="pi pi-pencil" severity="secondary" rounded text v-tooltip.top="t('common.edit', 'Edit')" @click.stop="editContract(data)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- CREATE VIEW -->
    <template v-else-if="currentView === 'create'">
      <div v-if="submitted" class="col-span-12">
      <div class="card text-center py-8">
        <i class="pi pi-check-circle text-6xl text-green-500 mb-4"></i>
        <h3 class="text-2xl font-semibold mb-2">{{ t('common.contract_created', 'Contract Created Successfully!') }}</h3>
        <p class="text-muted-color mb-6">{{ t('common.contract_created_msg', 'Monthly bills have been auto-generated for the contract duration.') }}</p>
        <Button :label="t('common.create_another', 'Create Another')" icon="pi pi-plus" @click="resetForm" />
      </div>
    </div>

    <template v-else>
      <div class="col-span-12 xl:col-span-5">
        <Panel :header="t('common.parent_info', 'Parent Information')" class="mb-6">
          <div class="flex flex-col gap-4">
            <label class="block font-medium">{{ t('common.select_parent', 'Select Parent') }} *</label>
            <AutoComplete
              v-model="selectedParent"
              :suggestions="parents"
              @complete="searchParents"
              :placeholder="t('common.search_parent', 'Search parent by name...')"
              class="w-full"
              :inputClass="'w-full'"
              optionLabel="full_name"
            >
              <template #option="slotProps">
                <div class="flex items-center gap-2">
                  <i class="pi pi-user text-lg"></i>
                  <div>
                    <div class="font-medium">{{ slotProps.option.first_name }} {{ slotProps.option.last_name }}</div>
                    <div class="text-xs text-muted-color">{{ slotProps.option.students_count || 0 }} {{ t('common.students', 'students') }}</div>
                  </div>
                </div>
              </template>
            </AutoComplete>
          </div>
        </Panel>

        <Panel :header="t('common.contract_period', 'Contract Period')" class="mb-6">
          <div class="flex flex-col gap-4">
            <div>
              <label class="block font-medium mb-2">{{ t('common.academic_year', 'Academic Year') }} *</label>
              <InputText v-model="academicYear" class="w-full" :placeholder="getDefaultAcademicYear()" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block font-medium mb-2">{{ t('common.start_date', 'Start Date') }} *</label>
                <DatePicker v-model="startDate" class="w-full" dateFormat="yy-mm-dd" />
              </div>
              <div>
                <label class="block font-medium mb-2">{{ t('common.end_date', 'End Date') }} *</label>
                <DatePicker v-model="endDate" class="w-full" dateFormat="yy-mm-dd" />
              </div>
            </div>
          </div>
        </Panel>

        <Panel :header="t('common.discount', 'Discount')" class="mb-6">
          <div class="flex flex-col gap-4">
            <div class="flex items-center gap-4">
              <div class="flex items-center gap-2">
                <RadioButton v-model="discountType" inputId="disc-none" value="" />
                <label for="disc-none">{{ t('common.none', 'None') }}</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="discountType" inputId="disc-fixed" value="fixed" />
                <label for="disc-fixed">{{ t('common.fixed', 'Fixed') }}</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton v-model="discountType" inputId="disc-pct" value="percentage" />
                <label for="disc-pct">{{ t('common.percentage', 'Percentage') }}</label>
              </div>
            </div>
            <div v-if="discountType" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block font-medium mb-2">{{ t('common.value', 'Value') }}</label>
                <InputNumber v-model="discountValue" :min="0" :suffix="discountType === 'percentage' ? '%' : ' DZD'" class="w-full" />
              </div>
              <div>
                <label class="block font-medium mb-2">{{ t('common.reason', 'Reason') }}</label>
                <InputText v-model="discountReason" class="w-full" :placeholder="t('common.optional', 'Optional')" />
              </div>
            </div>
          </div>
        </Panel>

        <Panel :header="t('common.notes', 'Notes')" class="mb-6">
          <Textarea v-model="notes" class="w-full" :autoResize="true" rows="3" :placeholder="t('common.optional_note', 'Optional note...')" />
        </Panel>

        <Panel :header="t('common.contract_status', 'Contract Status')">
          <div class="flex items-center justify-between">
            <div class="flex flex-col gap-1">
              <span class="font-medium">
                {{ isActive ? t('common.active', 'Active') : t('common.inactive', 'Inactive') }}
              </span>
              <span class="text-sm text-muted-color">
                {{ isActive
                  ? t('common.contract_active_hint', 'This contract is active and will be used for billing.')
                  : t('common.contract_inactive_hint', 'This contract is inactive and will be ignored in billing.')
                }}
              </span>
            </div>
            <ToggleSwitch v-model="isActive" />
          </div>
        </Panel>
      </div>

      <div class="col-span-12 xl:col-span-7">
        <Panel :header="t('common.select_fees', 'Select Fees')" class="mb-6">
          <div v-if="loading" class="flex items-center justify-center py-4">
            <i class="pi pi-spin pi-spinner text-xl text-primary"></i>
          </div>
          <div v-else-if="!selectedParent" class="text-center py-6 text-muted-color">
            <i class="pi pi-user text-3xl mb-2"></i>
            <p>{{ t('common.select_parent_first', 'Please select a parent first to view their students and assign fees.') }}</p>
          </div>
          <div v-else-if="parentStudents.length === 0" class="text-center py-6 text-muted-color">
            <i class="pi pi-info-circle text-3xl mb-2"></i>
            <p>{{ t('common.no_students_found', 'No students found for this parent.') }}</p>
          </div>
          <div v-else class="flex flex-col gap-6">
            <div v-for="student in parentStudents" :key="student.id" class="border dark:border-surface-700 rounded-lg p-4">
              <div class="flex items-center gap-2 mb-4">
                <i class="pi pi-user text-primary"></i>
                <h5 class="font-semibold text-lg m-0">{{ student.first_name }} {{ student.last_name }}</h5>
                <Tag v-if="student.class" :value="student.class.name" severity="info" class="ml-auto" />
              </div>

              <div v-if="availableFees.length === 0" class="text-sm text-muted-color">
                {{ t('common.no_fees_available', 'No active fees available.') }}
              </div>
              <div v-else class="flex flex-col gap-2">
                <div v-for="fee in availableFees" :key="fee.id"
                  class="flex items-center gap-3 p-2 rounded-lg border cursor-pointer transition-colors text-sm"
                  :class="studentFees[student.id]?.includes(fee.id)
                    ? 'border-primary bg-primary-50 dark:bg-primary-900/20'
                    : 'border-surface-200 dark:border-surface-700 hover:border-primary'"
                  @click="() => {
                    const fees = studentFees[student.id] || [];
                    if (fees.includes(fee.id)) {
                      studentFees[student.id] = fees.filter(id => id !== fee.id);
                    } else {
                      studentFees[student.id] = [...fees, fee.id];
                    }
                  }"
                >
                  <Checkbox :binary="true" :modelValue="studentFees[student.id]?.includes(fee.id)" class="pointer-events-none" />
                  <div class="flex-1">
                    <div class="font-medium">{{ fee.name }}</div>
                  </div>
                  <div class="text-right">
                    <div class="font-semibold text-primary">{{ Number(fee.base_amount).toFixed(2) }} DZD</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Panel>

        <Panel :header="t('common.summary', 'Summary')">
          <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between py-2">
              <span>{{ t('common.selected_fees', 'Selected Fees') }}</span>
              <span class="font-medium">{{ Object.values(studentFees).flat().length }}</span>
            </div>
            <Divider class="my-1" />
            <div class="flex items-center justify-between py-1">
              <span>{{ t('common.subtotal', 'Subtotal') }}</span>
              <span class="font-semibold">{{ totalFees.toFixed(2) }} DZD</span>
            </div>
            <div v-if="discountType" class="flex items-center justify-between py-1 text-green-600">
              <span>{{ t('common.discount', 'Discount') }}
                <span v-if="discountType === 'percentage'">({{ discountValue }}%)</span>
              </span>
              <span class="font-semibold">-{{ discountAmount.toFixed(2) }} DZD</span>
            </div>
            <Divider class="my-1" />
            <div class="flex items-center justify-between py-2 text-lg">
              <span class="font-bold">{{ t('common.total', 'Total') }}</span>
              <span class="font-bold text-primary">{{ finalTotal.toFixed(2) }} DZD</span>
            </div>
          </div>

          <Divider />

          <div class="flex items-center gap-3">
            <Button
              :label="editingContractId ? t('common.update_contract', 'Update Contract') : t('common.create_contract', 'Create Contract')"
              icon="pi pi-check"
              :loading="submitting"
              :disabled="!canSubmit"
              severity="success"
              class="w-full"
              @click="submitContract"
            />
          </div>
        </Panel>
      </div>
    </template>
    </template>
  </div>
</template>
