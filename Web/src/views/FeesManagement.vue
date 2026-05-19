<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import FeeService from '@/service/FeeService';
import LevelService from '@/service/LevelService';
import type { Fee, CreateFeeDTO } from '@/service/FeeService';
import type { Level } from '@/service/LevelService';

const { t } = useI18n();
const toast = useToast();
const dt = ref();

const fees = ref<Fee[]>([]);
const levels = ref<Level[]>([]);
const loading = ref(false);
const filters = ref({ global: { value: null, matchMode: FilterMatchMode.CONTAINS } });

const feeDialog = ref(false);
const deleteDialog = ref(false);
const fee = ref<Partial<Fee>>({});
const submitted = ref(false);

const levelDialog = ref(false);
const selectedFee = ref<Fee | null>(null);
const selectedLevelIds = ref<number[]>([]);
const savingLevels = ref(false);

const copyDialog = ref(false);
const fromYear = ref('');
const toYear = ref('');
const increasePct = ref(0);
const copying = ref(false);

const academicYears = computed(() => {
  const years = new Set(fees.value.map(f => f.academic_year));
  return Array.from(years).sort().reverse();
});

const loadFees = async () => {
  loading.value = true;
  try {
    fees.value = await FeeService.getFees();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_fees', 'Failed to load fees'), life: 3000 });
  } finally {
    loading.value = false;
  }
};

const loadLevels = async () => {
  try {
    levels.value = await LevelService.getLevels();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_load_levels', 'Failed to load levels'), life: 3000 });
  }
};

onMounted(() => {
  loadFees();
  loadLevels();
});

const openNew = () => {
  fee.value = { is_active: true };
  submitted.value = false;
  feeDialog.value = true;
};

const editFee = (f: Fee) => {
  fee.value = { ...f };
  submitted.value = false;
  feeDialog.value = true;
};

const hideDialog = () => {
  feeDialog.value = false;
  submitted.value = false;
};

const saveFee = async () => {
  submitted.value = true;
  if (!fee.value.name || !fee.value.base_amount || !fee.value.academic_year) return;
  try {
    if (fee.value.id) {
      const updated = await FeeService.updateFee(fee.value.id, fee.value as CreateFeeDTO);
      const idx = fees.value.findIndex(f => f.id === fee.value!.id);
      if (idx !== -1) fees.value[idx] = updated;
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.fee_updated', 'Fee updated'), life: 3000 });
    } else {
      const created = await FeeService.createFee(fee.value as CreateFeeDTO);
      fees.value.unshift(created);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.fee_created', 'Fee created'), life: 3000 });
    }
    feeDialog.value = false;
    fee.value = {};
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err.response?.data?.message || t('common.failed_to_save_fee', 'Failed to save fee'), life: 3000 });
  }
};

const confirmDelete = (f: Fee) => {
  fee.value = f;
  deleteDialog.value = true;
};

const deleteFee = async () => {
  try {
    await FeeService.deleteFee(fee.value.id!);
    fees.value = fees.value.filter(f => f.id !== fee.value!.id);
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.fee_deleted', 'Fee deleted'), life: 3000 });
    deleteDialog.value = false;
    fee.value = {};
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err.response?.data?.message || t('common.failed_to_delete_fee', 'Failed to delete fee'), life: 3000 });
  }
};

const toggleStatus = async (f: Fee) => {
  try {
    const updated = await FeeService.toggleStatus(f.id);
    const idx = fees.value.findIndex(x => x.id === f.id);
    if (idx !== -1) fees.value[idx] = updated;
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_toggle_status', 'Failed to toggle status'), life: 3000 });
  }
};

const openLevelDialog = async (f: Fee) => {
  selectedFee.value = f;
  try {
    const assignedLevels = await FeeService.getFeeLevels(f.id);
    selectedLevelIds.value = assignedLevels.map(l => l.id!);
  } catch {
    selectedLevelIds.value = [];
  }
  levelDialog.value = true;
};

const saveLevels = async () => {
  if (!selectedFee.value) return;
  savingLevels.value = true;
  try {
    const updated = await FeeService.syncLevels(selectedFee.value.id, selectedLevelIds.value);
    const idx = fees.value.findIndex(f => f.id === selectedFee.value!.id);
    if (idx !== -1) fees.value[idx] = updated;
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.levels_updated', 'Levels updated'), life: 3000 });
    levelDialog.value = false;
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.failed_to_save_levels', 'Failed to save levels'), life: 3000 });
  } finally {
    savingLevels.value = false;
  }
};

const openCopyDialog = () => {
  copyDialog.value = true;
  fromYear.value = academicYears.value[0] || '';
  toYear.value = '';
  increasePct.value = 0;
};

const copyFees = async () => {
  if (!fromYear.value || !toYear.value) return;
  copying.value = true;
  try {
    const copied = await FeeService.copyToNewYear(fromYear.value, toYear.value, increasePct.value || undefined);
    for (const f of copied) {
      fees.value.unshift(f);
    }
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.fees_copied', { count: copied.length }, `${copied.length} fees copied`), life: 3000 });
    copyDialog.value = false;
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err.response?.data?.message || t('common.failed_to_copy_fees', 'Failed to copy fees'), life: 3000 });
  } finally {
    copying.value = false;
  }
};

const levelsByFee = (f: Fee): string => {
  if (!f.levels || f.levels.length === 0) return '-';
  return f.levels.map(l => l.name).join(', ');
};

const getAcademicYear = () => {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();
  return m < 8 ? `${y - 1}-${y}` : `${y}-${y + 1}`;
};
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <div class="col-span-12">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <i class="pi pi-dollar text-2xl text-primary"></i>
          <h4 class="text-2xl font-semibold">{{ t('nav.fees_management', 'Fees Management') }}</h4>
        </div>
        <div class="flex items-center gap-2">
          <Button :label="t('common.copy_fees', 'Copy to New Year')" icon="pi pi-copy" severity="secondary" @click="openCopyDialog" />
          <Button :label="t('common.new_fee', 'New Fee')" icon="pi pi-plus" @click="openNew" />
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <DataTable
          ref="dt"
          :value="fees"
          :loading="loading"
          :filters="filters"
          :globalFilterFields="['name', 'description', 'academic_year']"
          stripedRows
          :size="'small'"
          paginator
          :rows="15"
          :rowsPerPageOptions="[10, 15, 25, 50]"
          sortField="created_at"
          :sortOrder="-1"
        >
          <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
              <div class="flex items-center gap-2">
                <span class="p-input-icon-left">
                  <i class="pi pi-search" />
                  <InputText v-model="filters.global.value" :placeholder="t('common.search', 'Search')" class="w-64" />
                </span>
              </div>
            </div>
          </template>

          <Column field="name" :header="t('common.name', 'Name')" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <i class="pi pi-tag text-primary"></i>
                <span class="font-medium">{{ data.name }}</span>
              </div>
            </template>
          </Column>
          <Column field="description" :header="t('common.description', 'Description')">
            <template #body="{ data }">
              <span class="text-sm text-muted-color">{{ data.description || '-' }}</span>
            </template>
          </Column>
          <Column field="base_amount" :header="t('common.amount', 'Amount')" sortable>
            <template #body="{ data }">
              <span class="font-semibold">{{ Number(data.base_amount).toFixed(2) }} {{ t('common.currency_dzd', 'DZD') }}</span>
            </template>
          </Column>
          <Column field="academic_year" :header="t('common.academic_year', 'Academic Year')" sortable>
            <template #body="{ data }">
              <Tag :value="data.academic_year" severity="info" />
            </template>
          </Column>
          <Column :header="t('common.levels', 'Levels')">
            <template #body="{ data }">
              <span class="text-sm">{{ levelsByFee(data) }}</span>
            </template>
          </Column>
          <Column field="is_active" :header="t('common.status', 'Status')" sortable>
            <template #body="{ data }">
              <ToggleSwitch :modelValue="data.is_active" @update:modelValue="toggleStatus(data)" />
            </template>
          </Column>
          <Column :header="t('common.actions', 'Actions')" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <Button icon="pi pi-sitemap" severity="info" rounded text
                  v-tooltip.left="t('common.assign_levels', 'Assign Levels')"
                  @click="openLevelDialog(data)" />
                <Button icon="pi pi-pencil" severity="secondary" rounded text
                  @click="editFee(data)" />
                <Button icon="pi pi-trash" severity="danger" rounded text
                  @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </div>

  <Dialog v-model:visible="feeDialog" :header="fee.id ? t('common.edit_fee', 'Edit Fee') : t('common.new_fee', 'New Fee')"
    :modal="true" class="w-full md:w-3/4 lg:w-1/2">
    <div class="flex flex-col gap-4">
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-6">
          <label class="block font-medium mb-2">{{ t('common.name', 'Name') }} *</label>
          <InputText v-model="fee.name" class="w-full" :class="{ 'p-invalid': submitted && !fee.name }" />
          <small v-if="submitted && !fee.name" class="text-red-500">{{ t('common.required', 'Required') }}</small>
        </div>
        <div class="col-span-12 md:col-span-6">
          <label class="block font-medium mb-2">{{ t('common.academic_year', 'Academic Year') }} *</label>
          <InputText v-model="fee.academic_year" class="w-full" :class="{ 'p-invalid': submitted && !fee.academic_year }"
            :placeholder="getAcademicYear()" />
          <small v-if="submitted && !fee.academic_year" class="text-red-500">{{ t('common.required', 'Required') }}</small>
        </div>
        <div class="col-span-12">
          <label class="block font-medium mb-2">{{ t('common.description', 'Description') }}</label>
          <Textarea v-model="fee.description" class="w-full" :autoResize="true" rows="2" />
        </div>
        <div class="col-span-12 md:col-span-6">
          <label class="block font-medium mb-2">{{ t('common.base_amount', 'Base Amount') }} *</label>
          <InputNumber v-model="fee.base_amount" :min="0" mode="decimal" :minFractionDigits="2" :maxFractionDigits="2"
            class="w-full" :class="{ 'p-invalid': submitted && !fee.base_amount }" />
          <small v-if="submitted && !fee.base_amount" class="text-red-500">{{ t('common.required', 'Required') }}</small>
        </div>
        <div class="col-span-12 md:col-span-6">
          <label class="block font-medium mb-2">{{ t('common.active', 'Active') }}</label>
          <ToggleSwitch v-model="fee.is_active" />
        </div>
      </div>
    </div>
    <template #footer>
      <Button :label="t('common.cancel', 'Cancel')" icon="pi pi-times" severity="secondary" @click="hideDialog" />
      <Button :label="t('common.save', 'Save')" icon="pi pi-check" @click="saveFee" />
    </template>
  </Dialog>

  <Dialog v-model:visible="deleteDialog" :header="t('common.confirm_delete', 'Confirm Delete')" :modal="true" class="w-full md:w-1/3">
    <p>{{ t('common.delete_fee_confirm', 'Are you sure you want to delete this fee? If used in contracts, it will be deactivated instead.') }}</p>
    <template #footer>
      <Button :label="t('common.no', 'No')" icon="pi pi-times" severity="secondary" @click="deleteDialog = false" />
      <Button :label="t('common.yes', 'Yes')" icon="pi pi-check" severity="danger" @click="deleteFee" />
    </template>
  </Dialog>

  <Dialog v-model:visible="levelDialog" :header="t('common.assign_levels', 'Assign Levels to Fee')" :modal="true" class="w-full md:w-1/2">
    <p class="mb-4 text-muted-color">
      {{ t('common.select_levels_for_fee', 'Select the levels this fee applies to') }}:
      <strong>{{ selectedFee?.name }}</strong>
    </p>
    <div class="flex flex-col gap-2">
      <div v-for="level in levels" :key="level.id" class="flex items-center gap-3 p-2 rounded-lg border border-surface-200 dark:border-surface-700">
        <Checkbox :inputId="'level-' + level.id" :binary="true"
          :modelValue="selectedLevelIds.includes(level.id!)"
          @update:modelValue="(checked) => {
            if (checked) selectedLevelIds.push(level.id!);
            else selectedLevelIds = selectedLevelIds.filter(id => id !== level.id);
          }" />
        <label :for="'level-' + level.id" class="cursor-pointer font-medium">{{ level.name }}</label>
        <Tag :value="level.cycle" severity="info" class="ml-auto" />
      </div>
      <div v-if="levels.length === 0" class="text-center py-4 text-muted-color">
        {{ t('common.no_levels', 'No levels available') }}
      </div>
    </div>
    <template #footer>
      <Button :label="t('common.cancel', 'Cancel')" icon="pi pi-times" severity="secondary" @click="levelDialog = false" />
      <Button :label="t('common.save', 'Save')" icon="pi pi-check" :loading="savingLevels" @click="saveLevels" />
    </template>
  </Dialog>

  <Dialog v-model:visible="copyDialog" :header="t('common.copy_fees', 'Copy Fees to New Year')" :modal="true" class="w-full md:w-1/2">
    <div class="flex flex-col gap-4">
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-6">
          <label class="block font-medium mb-2">{{ t('common.from_year', 'From Year') }} *</label>
          <Select v-model="fromYear" :options="academicYears" class="w-full" />
        </div>
        <div class="col-span-12 md:col-span-6">
          <label class="block font-medium mb-2">{{ t('common.to_year', 'To Year') }} *</label>
          <InputText v-model="toYear" class="w-full" :placeholder="t('common.enter_year', 'e.g. 2025-2026')" />
        </div>
        <div class="col-span-12 md:col-span-6">
          <label class="block font-medium mb-2">{{ t('common.increase_pct', 'Increase %') }}</label>
          <InputNumber v-model="increasePct" :min="0" :max="100" suffix="%" class="w-full" />
        </div>
      </div>
    </div>
    <template #footer>
      <Button :label="t('common.cancel', 'Cancel')" icon="pi pi-times" severity="secondary" @click="copyDialog = false" />
      <Button :label="t('common.copy', 'Copy')" icon="pi pi-copy" :loading="copying" @click="copyFees" />
    </template>
  </Dialog>
</template>
