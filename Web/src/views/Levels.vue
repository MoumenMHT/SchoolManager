<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import LevelService, { type Level, type LevelSubjectMapping } from '@/service/LevelService';
import SubjectService, { type Subject } from '@/service/SubjectService';

const { t } = useI18n();
const toast = useToast();

const dt = ref();
const levels = ref<Level[]>([]);
const subjects = ref<Subject[]>([]);
const loading = ref(false);
const expandedRows = ref({});
const isCycleLocked = ref(false);

const groupedCycles = computed(() => {
  const map = new Map();
  levels.value.forEach(lvl => {
    let _cycle = lvl.cycle || 'Autre';
    if (!map.has(_cycle)) {
      map.set(_cycle, {
        cycle: _cycle,
        levels: []
      });
    }
    map.get(_cycle).levels.push(lvl);
  });
  return Array.from(map.values());
});

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

// CRUD Dialog States
const levelDialog = ref(false);
const deleteLevelDialog = ref(false);
const level = ref<Partial<Level>>({});
const submitted = ref(false);

// Subject Mapping Dialog States
const assignDialog = ref(false);
const selectedLevel = ref<Level | null>(null);
const mappingRows = ref<LevelSubjectMapping[]>([]);
const assignLoading = ref(false);

onMounted(async () => {
  await loadLevels();
  await loadSubjects();
});

const loadLevels = async () => {
  try {
    loading.value = true;
    levels.value = await LevelService.getLevels();
  } catch (error: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.error_occurred'), life: 3000 });
  } finally {
    loading.value = false;
  }
};

const loadSubjects = async () => {
    try {
        subjects.value = await SubjectService.getSubjects();
    } catch (e) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.error_occurred'), life: 3000 });
    }
};

// ======================= CRUD OPERATIONS =======================

const openNew = (cycleName?: string) => {
  isCycleLocked.value = !!cycleName;
  level.value = {
    cycle: cycleName || '',
    year_number: 1,
    sort_order: (levels.value.length + 1) * 10,
    is_active: true
  };
  submitted.value = false;
  levelDialog.value = true;
};

const hideDialog = () => {
  levelDialog.value = false;
  submitted.value = false;
};

const saveLevel = async () => {
  submitted.value = true;

  if (!level.value.name?.trim() || !level.value.cycle || !level.value.year_number || level.value.sort_order === undefined) {
    return;
  }

  try {
    if (level.value.id) {
      await LevelService.updateLevel(level.value.id, level.value);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.updated_successfully'), life: 3000 });
    } else {
      await LevelService.createLevel(level.value);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.created_successfully'), life: 3000 });
    }

    await loadLevels();
    levelDialog.value = false;
    level.value = {};
  } catch (error: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.save_failed'), life: 3000 });
  }
};

const editLevel = (lvl: Level) => {
  isCycleLocked.value = true;
  level.value = { ...lvl };
  levelDialog.value = true;
};

const confirmDeleteLevel = (lvl: Level) => {
  level.value = lvl;
  deleteLevelDialog.value = true;
};

const deleteLevel = async () => {
  try {
    await LevelService.deleteLevel(level.value.id!);
    levels.value = levels.value.filter(l => l.id !== level.value.id);
    deleteLevelDialog.value = false;
    level.value = {};
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.deleted_successfully'), life: 3000 });
  } catch (error: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.delete_failed'), life: 3000 });
  }
};

// ======================= SUBJECT MAPPING =======================

const openAssignSubjects = async (lvl: Level) => {
  selectedLevel.value = lvl;
  assignLoading.value = true;
  assignDialog.value = true;
  mappingRows.value = [];

  try {
    const existing = await LevelService.getLevelSubjects(lvl.id!);
    // Setup mapping rows from existing DB data
    // Note: the DB pivot column is `weekly_sessions_required`; the UI calls it `weekly_hours`
    mappingRows.value = existing.map((val: any) => ({
      subject_id: val.id || val.subject_id,
      coefficient: val.pivot ? val.pivot.coefficient : val.coefficient,
      weekly_hours: val.pivot?.weekly_sessions_required ?? val.weekly_sessions_required ?? 0,
      subject: val,
    }));
  } catch (error) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.load_failed'), life: 3000 });
  } finally {
    assignLoading.value = false;
  }
};

const addSubjectRow = () => {
  mappingRows.value.push({
    subject_id: 0,
    coefficient: 1,
    weekly_sessions_required: 2,
    weekly_hours: 2
  });
};

const removeSubjectRow = (index: number) => {
  mappingRows.value.splice(index, 1);
};

const saveSubjectMappings = async () => {
  if (!selectedLevel.value?.id) return;
  
  // Basic validation to ensure rows have valid subjects selected
  const validRows = mappingRows.value.filter(r => r.subject_id > 0);

  try {
    await LevelService.assignSubjects(selectedLevel.value.id, validRows);
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.assigned_successfully'), life: 3000 });
    assignDialog.value = false;
  } catch(error) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.save_failed'), life: 3000 });
  }
};
</script>

<template>
  <div class="card">
    <!-- Header / Toolbar -->
    <Toolbar class="mb-4">
      <template #start>
        <Button :label="t('levels.add_cycle')" icon="pi pi-plus" severity="success" class="mr-2" @click="() => openNew()" />
      </template>
    </Toolbar>

    <!-- Main Datatable -->
    <DataTable
      ref="dt"
      :value="groupedCycles"
      dataKey="cycle"
      v-model:expandedRows="expandedRows"
      :loading="loading"
      responsiveLayout="scroll"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0">{{ t('levels.cycles_and_levels') }}</h4>
        </div>
      </template>

      <!-- Expand Col -->
      <Column expander style="width: 5rem" />

      <!-- Columns -->
      <Column field="cycle" :header="t('common.cycle')" :sortable="true" style="min-width: 12rem">
        <template #body="slotProps">
          <span class="font-bold text-lg capitalize">{{ slotProps.data.cycle }}</span>
        </template>
      </Column>
      <Column :header="t('nav.levels')" style="min-width: 12rem">
        <template #body="slotProps">
          <Badge :value="slotProps.data.levels.length" severity="info" class="mr-2" />
          <span>{{ t('nav.levels') }}</span>
        </template>
      </Column>

      <!-- Expansion -->
      <template #expansion="slotProps">
        <div class="p-3">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0 capitalize">{{ t('nav.levels') }} ({{ slotProps.data.cycle }})</h5>
            <Button :label="t('levels.add_level')" icon="pi pi-plus" class="p-button-sm" severity="primary" @click="() => openNew(slotProps.data.cycle)" />
          </div>
          <DataTable
             :value="slotProps.data.levels"
             dataKey="id"
             class="p-datatable-sm"
             :paginator="true"
             :rows="10"
             :filters="filters"
          >
            <template #header>
              <div class="flex justify-end">
                <IconField>
                  <InputIcon>
                    <i class="pi pi-search" />
                  </InputIcon>
                  <InputText v-model="filters['global'].value" :placeholder="t('common.search')" />
                </IconField>
              </div>
            </template>
            <Column field="year_number" :header="t('common.year_number')" :sortable="true" style="min-width: 6rem"></Column>
            <Column field="name" :header="t('common.name')" :sortable="true" style="min-width: 12rem"></Column>
            <Column field="track" :header="t('common.track')" :sortable="true" style="min-width: 10rem"></Column>
            <Column field="sort_order" :header="t('common.sort_order')" :sortable="true" style="min-width: 6rem"></Column>
            <Column field="is_active" :header="t('common.status')" :sortable="true" style="min-width: 8rem">
              <template #body="subProps">
                <Tag :value="subProps.data.is_active ? t('common.active') : t('common.inactive')" :severity="subProps.data.is_active ? 'success' : 'danger'" />
              </template>
            </Column>

            <!-- Actions -->
            <Column :header="t('common.actions')" :exportable="false" style="min-width: 12rem">
              <template #body="subProps">
                <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editLevel(subProps.data)" />
                <Button icon="pi pi-list" outlined rounded severity="info" class="mr-2" @click="openAssignSubjects(subProps.data)" v-tooltip.top="t('levels.assign_subjects')" />
                <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteLevel(subProps.data)" />
              </template>
            </Column>
          </DataTable>
        </div>
      </template>
    </DataTable>

    <!-- Create / Edit Dialog -->
    <Dialog v-model:visible="levelDialog" :style="{ width: '450px' }" :header="t('nav.levels')" :modal="true">
      <div class="flex flex-col gap-4">
        <div>
          <label for="cycle" class="block font-bold mb-2">{{ t('common.cycle') }} *</label>
          <!-- Using InputText so you can type any arbitrary cycle name (maternelle, etc.) -->
          <InputText id="cycle" v-model.trim="level.cycle" required class="w-full" :class="{ 'p-invalid': submitted && !level.cycle }" :disabled="isCycleLocked" />
          <small class="p-error" v-if="submitted && !level.cycle">{{ t('common.required_field') }}</small>
        </div>

        <div>
          <label for="year_number" class="block font-bold mb-2">{{ t('common.year_number') }} *</label>
          <InputNumber id="year_number" v-model="level.year_number" class="w-full" :class="{ 'p-invalid': submitted && !level.year_number }" />
        </div>

        <div>
          <label for="name" class="block font-bold mb-2">{{ t('common.name') }} *</label>
          <InputText id="name" v-model.trim="level.name" required autofocus class="w-full" :class="{ 'p-invalid': submitted && !level.name }" />
        </div>

        <div>
          <label for="track" class="block font-bold mb-2">{{ t('common.track') }}</label>
          <InputText id="track" v-model="level.track" class="w-full" />
        </div>

        <div>
          <label for="sort_order" class="block font-bold mb-2">{{ t('common.sort_order') }} *</label>
          <InputNumber id="sort_order" v-model="level.sort_order" class="w-full" :class="{ 'p-invalid': submitted && level.sort_order === undefined }" />
        </div>

        <div class="flex items-center gap-2 mt-2">
          <Checkbox v-model="level.is_active" :binary="true" inputId="is_active" />
          <label for="is_active" class="font-bold">{{ t('common.active') }}</label>
        </div>
      </div>
      
      <template #footer>
        <Button :label="t('common.cancel')" icon="pi pi-times" text @click="hideDialog" />
        <Button :label="t('common.save')" icon="pi pi-check" @click="saveLevel" />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:visible="deleteLevelDialog" :style="{ width: '450px' }" :header="t('common.confirm_deletion')" :modal="true">
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle !text-3xl" />
        <span v-if="level">{{ t('levels.confirm_delete_level', { name: level.name }) }}</span>
      </div>
      <template #footer>
        <Button :label="t('common.no')" icon="pi pi-times" text @click="deleteLevelDialog = false" />
        <Button :label="t('common.yes')" icon="pi pi-check" @click="deleteLevel" />
      </template>
    </Dialog>

    <!-- Map Subjects Dialog -->
    <Dialog v-model:visible="assignDialog" :style="{ width: '800px' }" :header="t('levels.assign_subjects')" :modal="true">
      
      <div class="mb-4">
        <Button :label="t('levels.add_row')" icon="pi pi-plus" class="p-button-sm" @click="addSubjectRow" />
      </div>

      <DataTable :value="mappingRows" class="p-datatable-sm" responsiveLayout="scroll">
        <Column :header="t('levels.subject')">
          <template #body="{ data }">
            <Dropdown v-model="data.subject_id" :options="subjects" optionLabel="name" optionValue="id" :placeholder="t('levels.select_subject')" class="w-full" filter />
          </template>
        </Column>
        <Column :header="t('levels.coefficient')" style="width: 15%">
          <template #body="{ data }">
            <InputNumber v-model="data.coefficient" class="w-full" :min="1" />
          </template>
        </Column>
        <Column :header="t('levels.weekly_hours')" style="width: 20%">
          <template #body="{ data }">
            <InputNumber v-model="data.weekly_hours" class="w-full" :min="0" />
          </template>
        </Column>
        <Column :header="t('common.actions')" style="width: 10%">
          <template #body="{ index }">
            <Button icon="pi pi-trash" outlined rounded severity="danger" @click="removeSubjectRow(index)" />
          </template>
        </Column>
        <template #empty>
          <div class="p-3 text-center">{{ t('levels.no_subjects_assigned') }}</div>
        </template>
      </DataTable>

      <template #footer>
        <Button :label="t('common.cancel')" icon="pi pi-times" text @click="assignDialog = false" />
        <Button :label="t('common.save')" icon="pi pi-check" @click="saveSubjectMappings" :loading="assignLoading" />
      </template>
    </Dialog>

  </div>
</template>