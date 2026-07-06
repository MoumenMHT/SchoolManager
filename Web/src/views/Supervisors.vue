<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import SupervisorService from '@/service/SupervisorService';
import ApiService from '@/service/ApiService';
import { FilterMatchMode } from '@primevue/core/api';

const toast = useToast();
const { t } = useI18n();

// State
const supervisors = ref<any[]>([]);
const loading = ref(false);
const classes = ref<any[]>([]);

// Dialog
const supervisorDialog = ref(false);
const deleteSupervisorDialog = ref(false);
const supervisorDetailsDialog = ref(false);
const selectedSupervisor = ref<any>(null);
const selectedSupervisorDetails = ref<any>(null);
const isNew = ref(false);
const saving = ref(false);
const submitted = ref(false);

// Form
const form = ref({
  first_name: '',
  last_name: '',
  phone: '',
  hire_date: null as Date | null,
  status: 'active',
  username: '',
  password: '',
  class_ids: [] as number[],
});

// Search
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
];

const totalSupervisors = computed(() => supervisors.value.length);
const activeSupervisors = computed(() => supervisors.value.filter((s) => s.status === 'active').length);
const assignedClassesCount = computed(() => supervisors.value.reduce((sum, s) => sum + (s.classes?.length ?? 0), 0));

function initials(supervisor: any) {
  const first = supervisor?.first_name?.[0] ?? '';
  const last = supervisor?.last_name?.[0] ?? '';
  return `${first}${last}`.toUpperCase() || 'SV';
}

function visibleClasses(classesList: any[] = []) {
  return classesList.slice(0, 2);
}

async function loadSupervisors() {
  loading.value = true;
  try {
    supervisors.value = await SupervisorService.getAll();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('supervisors.load_error'), life: 3000 });
  } finally {
    loading.value = false;
  }
}

async function loadClasses() {
  try {
    const response = await ApiService.get<any[]>('/classes');
    const raw = (response.data as any)?.data ?? response.data ?? [];
    classes.value = Array.isArray(raw) ? raw : Object.values(raw);
  } catch {
    // no-op
  }
}

function openNew() {
  isNew.value = true;
  submitted.value = false;
  form.value = {
    first_name: '',
    last_name: '',
    phone: '',
    hire_date: null,
    status: 'active',
    username: '',
    password: '',
    class_ids: [],
  };
  selectedSupervisor.value = null;
  supervisorDialog.value = true;
}

function editSupervisor(supervisor: any) {
  isNew.value = false;
  submitted.value = false;
  selectedSupervisor.value = supervisor;
  form.value = {
    first_name: supervisor.first_name ?? '',
    last_name: supervisor.last_name ?? '',
    phone: supervisor.phone ?? '',
    hire_date: supervisor.hire_date ? new Date(supervisor.hire_date) : null,
    status: supervisor.status ?? 'active',
    username: supervisor.user?.username ?? '',
    password: '',
    class_ids: (supervisor.classes ?? []).map((c: any) => c.id),
  };
  supervisorDialog.value = true;
}

async function saveSupervisor() {
  submitted.value = true;

  if (!form.value.first_name || !form.value.last_name) {
    toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('supervisors.fill_required'), life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const payload: any = {
      first_name: form.value.first_name,
      last_name: form.value.last_name,
      phone: form.value.phone || null,
      hire_date: form.value.hire_date
        ? `${form.value.hire_date.getFullYear()}-${String(form.value.hire_date.getMonth() + 1).padStart(2, '0')}-${String(form.value.hire_date.getDate()).padStart(2, '0')}`
        : null,
      status: form.value.status,
      class_ids: form.value.class_ids,
    };

    if (isNew.value) {
      payload.username = form.value.username;
      payload.password = form.value.password;
    }

    if (isNew.value) {
      await SupervisorService.create(payload);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('supervisors.created'), life: 3000 });
    } else {
      await SupervisorService.update(selectedSupervisor.value.id, payload);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('supervisors.updated'), life: 3000 });
    }

    supervisorDialog.value = false;
    await loadSupervisors();
  } catch (err: any) {
    const data = err?.response?.data;

    // Standard Laravel validation errors — show each field error individually
    if (data?.errors) {
      const fieldErrors = Object.entries(data.errors) as [string, string[]][];
      for (const [field, messages] of fieldErrors) {
        const fieldLabel = field === 'username'
          ? t('common.username')
          : field === 'class_ids'
            ? t('supervisors.assign_classes')
            : field;
        toast.add({
          severity: 'error',
          summary: `${t('common.error')} — ${fieldLabel}`,
          detail: Array.isArray(messages) ? messages.join(' ') : String(messages),
          life: 5000,
        });
      }
      return;
    }

    // Backend-translated message (e.g. class_already_assigned) or fallback
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: data?.message ?? t('supervisors.save_error'),
      life: 5000,
    });
  } finally {
    saving.value = false;
  }
}

function confirmDelete(supervisor: any) {
  selectedSupervisor.value = supervisor;
  deleteSupervisorDialog.value = true;
}

function showSupervisorDetails(supervisor: any) {
  selectedSupervisorDetails.value = supervisor;
  supervisorDetailsDialog.value = true;
}

async function deleteSupervisor() {
  try {
    await SupervisorService.remove(selectedSupervisor.value.id);
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('supervisors.deleted'), life: 3000 });
    deleteSupervisorDialog.value = false;
    await loadSupervisors();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('supervisors.delete_error'), life: 3000 });
  }
}

onMounted(async () => {
  await Promise.all([loadSupervisors(), loadClasses()]);
});
</script>

<template>
  <div class="card">
    <Toast />

    <Toolbar class="mb-6">
      <template #start>
        <Button :label="t('supervisors.new_supervisor')" icon="pi pi-plus" severity="secondary" class="mr-2" @click="openNew" />
      </template>
    </Toolbar>

    <DataTable
      :value="supervisors"
      dataKey="id"
      :paginator="true"
      :rows="10"
      :filters="filters"
      :loading="loading"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :rowsPerPageOptions="[5, 10, 25, 50]"
      :currentPageReportTemplate="t('supervisors.page_report')"
      class="p-datatable-sm"
      :globalFilterFields="['first_name', 'last_name', 'phone', 'user.username']"
      @row-click="showSupervisorDetails($event.data)"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">{{ t('supervisors.title') }}</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="filters['global'].value"
              :placeholder="t('supervisors.search_placeholder')"
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">{{ t('supervisors.no_supervisors') }}</p>
        </div>
      </template>

      <Column field="first_name" :header="t('common.full_name')" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            <span class="font-semibold">{{ data.first_name }} {{ data.last_name }}</span>
          </div>
        </template>
      </Column>

      <Column field="phone" :header="t('common.phone')" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div v-if="data.phone" class="flex items-center gap-2">
            <i class="pi pi-phone text-sm text-muted-color"></i>
            <span>{{ data.phone }}</span>
          </div>
          <span v-else class="text-muted-color">{{ t('common.na') }}</span>
        </template>
      </Column>

      <Column field="username" :header="t('common.username', 'Username')" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div v-if="data.user?.username" class="flex items-center gap-2">
            <i class="pi pi-user text-sm text-muted-color"></i>
            <span>{{ data.user.username }}</span>
          </div>
          <span v-else class="text-muted-color">{{ t('common.na') }}</span>
        </template>
      </Column>

      <Column :header="t('common.classes')" style="min-width: 15rem">
        <template #body="{ data }">
          <div class="flex flex-wrap gap-1">
            <Tag v-for="cls in visibleClasses(data.classes ?? [])" :key="cls.id" :value="cls.name" severity="info" class="text-xs" />
            <Tag
              v-if="(data.classes?.length ?? 0) > 2"
              :value="`+${(data.classes?.length ?? 0) - 2}`"
              severity="secondary"
              class="text-xs"
            />
            <span v-if="!data.classes?.length" class="text-muted-color">{{ t('common.na') }}</span>
          </div>
        </template>
      </Column>

      <Column field="status" :header="t('common.status')" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="data.status === 'active' ? 'success' : 'danger'" class="capitalize text-xs font-semibold" />
        </template>
      </Column>

      <Column :header="t('common.actions')" :exportable="false" style="min-width: 10rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-pencil"
            outlined
            rounded
            class="mr-2"
            @click="editSupervisor(data)"
            v-tooltip.top="t('common.edit')"
          />
          <Button
            icon="pi pi-trash"
            outlined
            rounded
            severity="danger"
            @click="confirmDelete(data)"
            v-tooltip.top="t('common.delete')"
          />
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="supervisorDialog"
      :style="{ width: '550px' }"
      :header="isNew ? t('supervisors.new_supervisor') : t('supervisors.edit_supervisor')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold mb-2">{{ t('common.first_name') }} <span class="text-red-500">*</span></label>
            <InputText v-model="form.first_name" :placeholder="t('common.first_name')" :invalid="submitted && !form.first_name" />
          </div>
          <div>
            <label class="block font-semibold mb-2">{{ t('common.last_name') }} <span class="text-red-500">*</span></label>
            <InputText v-model="form.last_name" :placeholder="t('common.last_name')" :invalid="submitted && !form.last_name" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold mb-2">{{ t('common.phone') }}</label>
            <InputText v-model="form.phone" placeholder="0xxx xxx xxx" v-keyfilter="/[0-9]/" />
          </div>
          <div>
            <label class="block font-semibold mb-2">{{ t('common.hire_date') }}</label>
            <DatePicker v-model="form.hire_date" dateFormat="yy-mm-dd" showIcon />
          </div>
        </div>

        <div>
          <label class="block font-semibold mb-2">{{ t('common.status') }}</label>
          <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" />
        </div>

        <div class="border-t border-surface-200 dark:border-surface-700 pt-4 mt-2" v-if="isNew">
          <h4 class="text-sm font-semibold text-surface-600 dark:text-surface-300 mb-3">
            <i class="pi pi-user mr-1"></i> {{ t('supervisors.account_section') }}
          </h4>
        </div>
        <div class="grid grid-cols-2 gap-4" v-if="isNew">
          <div>
            <label class="block font-semibold mb-2">{{ t('common.username') }} <span class="text-red-500">*</span></label>
            <InputText v-model="form.username" type="text" placeholder="supervisor_123" :invalid="submitted && isNew && !form.username" />
          </div>
          <div>
            <label class="block font-semibold mb-2">
              {{ t('common.password') }} <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.password" type="password" :placeholder="t('supervisors.password_placeholder')" :invalid="submitted && isNew && !form.password" />
          </div>
        </div>

        <div>
          <label class="block font-semibold mb-2">{{ t('supervisors.assign_classes') }}</label>
          <MultiSelect
            v-model="form.class_ids"
            :options="classes"
            optionLabel="name"
            optionValue="id"
            :placeholder="t('supervisors.select_classes')"
            :maxSelectedLabels="5"
            display="chip"
          />
        </div>
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" icon="pi pi-times" text @click="supervisorDialog = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveSupervisor" />
      </template>
    </Dialog>

    <!-- Delete Supervisor Dialog -->
    <Dialog
      v-model:visible="deleteSupervisorDialog"
      :style="{ width: '450px' }"
      :header="t('common.confirm_deletion')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-3xl text-red-500"></i>
        <span>
          {{ t('common.are_you_sure_delete') }} <b>{{ selectedSupervisor?.first_name }} {{ selectedSupervisor?.last_name }}</b>?
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteSupervisorDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="deleteSupervisor"
        />
      </template>
    </Dialog>

    <!-- Supervisor Details Dialog -->
    <Dialog
      v-model:visible="supervisorDetailsDialog"
      :style="{ width: '700px' }"
      :header="t('supervisors.supervisor_details', 'Supervisor Details')"
      :modal="true"
    >
      <div v-if="selectedSupervisorDetails" class="flex flex-col gap-6">
        <!-- Personal Information Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            {{ t('common.personal_information') }}
          </h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">{{ t('common.full_name') }}</label>
              <p class="font-semibold">{{ selectedSupervisorDetails.first_name }} {{ selectedSupervisorDetails.last_name }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.status') }}</label>
              <p class="font-semibold">
                <Tag :value="selectedSupervisorDetails.status" :severity="selectedSupervisorDetails.status === 'active' ? 'success' : 'danger'" class="capitalize text-xs font-semibold" />
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.phone') }}</label>
              <p class="font-semibold">
                <i class="pi pi-phone text-sm mr-2" v-if="selectedSupervisorDetails.phone"></i>
                {{ selectedSupervisorDetails.phone || t('common.na') }}
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.username', 'Username') }}</label>
              <p class="font-semibold">
                <i class="pi pi-user text-sm mr-2" v-if="selectedSupervisorDetails.user?.username"></i>
                {{ selectedSupervisorDetails.user?.username || t('common.na') }}
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.hire_date') }}</label>
              <p class="font-semibold">
                <i class="pi pi-calendar text-sm mr-2" v-if="selectedSupervisorDetails.hire_date"></i>
                {{ selectedSupervisorDetails.hire_date || t('common.na') }}
              </p>
            </div>
          </div>
        </div>

        <!-- Assigned Classes Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-building text-primary"></i>
            {{ t('common.classes') }}
          </h3>
          <div v-if="selectedSupervisorDetails.classes?.length" class="flex flex-wrap gap-2">
            <Tag v-for="cls in selectedSupervisorDetails.classes" :key="cls.id" :value="cls.name" severity="info" />
          </div>
          <p v-else class="text-muted-color">{{ t('supervisors.no_classes_assigned') }}</p>
        </div>
      </div>

      <template #footer>
        <Button
          :label="t('common.close')"
          icon="pi pi-times"
          @click="supervisorDetailsDialog = false"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: pointer;
}
</style>