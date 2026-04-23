<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import ApiService from '@/service/ApiService';

const { t } = useI18n();
const toast = useToast();

// ─── State ────────────────────────────────────────────────────────────────────

const users = ref<any[]>([]);
const loading = ref(false);
const saving = ref(false);
const submitted = ref(false);

const editDialog = ref(false);
const selectedUser = ref<any>(null);

const form = ref({
  username: '',
  password: '',
});

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// ─── Role label map ───────────────────────────────────────────────────────────

const ROLE_LABEL: Record<string, string> = {
  admin: 'Admin',
  teacher: 'Teacher / Enseignant',
  parent: 'Parent',
  supervisor: 'Supervisor / Surveillant',
  secretariat: 'Secretariat',
  accountant: 'Accountant / Comptable',
  primary_director: 'Primary Director',
  cem_director: 'CEM Director',
  lycee_director: 'Lycée Director',
};

const ROLE_SEVERITY: Record<string, string> = {
  admin: 'danger',
  teacher: 'info',
  parent: 'secondary',
  supervisor: 'warn',
  secretariat: 'success',
  accountant: 'contrast',
  primary_director: 'primary',
  cem_director: 'primary',
  lycee_director: 'primary',
};

// ─── Load ─────────────────────────────────────────────────────────────────────

async function loadUsers() {
  loading.value = true;
  try {
    const res = await ApiService.get<any[]>('/users/with-profile');
    const raw = (res as any)?.data ?? (res as any) ?? [];
    users.value = Array.isArray(raw) ? raw : Object.values(raw);
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('user_management.load_error'), life: 3000 });
  } finally {
    loading.value = false;
  }
}

onMounted(loadUsers);

// ─── Edit dialog ──────────────────────────────────────────────────────────────

function openEdit(user: any) {
  selectedUser.value = user;
  form.value = { username: user.username ?? '', password: '' };
  submitted.value = false;
  editDialog.value = true;
}

function hideEditDialog() {
  editDialog.value = false;
  selectedUser.value = null;
  submitted.value = false;
}

async function saveCredentials() {
  submitted.value = true;

  if (!form.value.username.trim()) {
    toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('user_management.username_required'), life: 3000 });
    return;
  }

  saving.value = true;
  try {
    await ApiService.put(`/users/${selectedUser.value.id}/credentials`, {
      username: form.value.username.trim(),
      password: form.value.password || undefined,
    });

    toast.add({ severity: 'success', summary: t('common.success'), detail: t('user_management.updated'), life: 3000 });
    editDialog.value = false;
    await loadUsers();
  } catch (err: any) {
    const data = err?.response?.data;
    // Handle validation errors (e.g. username taken)
    if (data?.errors) {
      const messages = Object.values(data.errors).flat().join(' ');
      toast.add({ severity: 'error', summary: t('common.error'), detail: messages, life: 5000 });
    } else {
      toast.add({ severity: 'error', summary: t('common.error'), detail: data?.message ?? t('user_management.update_error'), life: 4000 });
    }
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="card">
    <Toast />

    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold m-0">{{ t('user_management.title') }}</h2>
      <p class="text-muted-color mt-1 mb-0">{{ t('user_management.subtitle') }}</p>
    </div>

    <!-- Table -->
    <DataTable
      :value="users"
      dataKey="id"
      :paginator="true"
      :rows="15"
      :filters="filters"
      :loading="loading"
      :globalFilterFields="['full_name', 'username', 'role']"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :rowsPerPageOptions="[10, 15, 25, 50]"
      :currentPageReportTemplate="t('user_management.page_report')"
      class="p-datatable-sm"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">{{ t('user_management.title') }}</h4>
          <IconField>
            <InputIcon><i class="pi pi-search" /></InputIcon>
            <InputText
              v-model="filters['global'].value"
              :placeholder="t('user_management.search_placeholder')"
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">{{ t('user_management.no_users') }}</p>
        </div>
      </template>

      <!-- Full Name -->
      <Column field="full_name" :header="t('user_management.col_full_name')" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            <span class="font-semibold">{{ data.full_name || t('user_management.no_name') }}</span>
          </div>
        </template>
      </Column>

      <!-- Username -->
      <Column field="username" :header="t('user_management.col_username')" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <span class="font-mono text-sm bg-surface-100 dark:bg-surface-800 px-2 py-1 rounded">
            {{ data.username }}
          </span>
        </template>
      </Column>

      <!-- Role -->
      <Column field="role" :header="t('user_management.col_role')" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <Tag
            :value="ROLE_LABEL[data.role] ?? data.role"
            :severity="(ROLE_SEVERITY[data.role] as any) ?? 'secondary'"
            class="text-xs capitalize"
          />
        </template>
      </Column>

      <!-- Status -->
      <Column field="is_active" :header="t('user_management.col_status')" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Tag
            :value="data.is_active ? t('common.active') : t('common.inactive')"
            :severity="data.is_active ? 'success' : 'danger'"
            class="text-xs"
          />
        </template>
      </Column>

      <!-- Actions -->
      <Column :header="t('common.actions')" :exportable="false" style="min-width: 8rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-key"
            outlined
            rounded
            severity="info"
            v-tooltip.top="t('user_management.edit_credentials')"
            @click="openEdit(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Edit Credentials Dialog -->
    <Dialog
      v-model:visible="editDialog"
      :style="{ width: '480px' }"
      :header="t('user_management.edit_dialog_title')"
      :modal="true"
      class="p-fluid"
    >
      <div v-if="selectedUser" class="flex flex-col gap-5">

        <!-- Who are we editing -->
        <div class="flex items-center gap-3 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg border border-surface-200 dark:border-surface-700">
          <i class="pi pi-user text-2xl text-primary"></i>
          <div>
            <p class="font-bold m-0">{{ selectedUser.full_name || t('user_management.no_name') }}</p>
            <p class="text-sm text-muted-color m-0">{{ t('user_management.edit_dialog_subtitle') }}</p>
          </div>
        </div>

        <!-- Username -->
        <div>
          <label class="block font-semibold mb-2">
            {{ t('common.username') }} <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.username"
            :placeholder="t('common.username')"
            :invalid="submitted && !form.username.trim()"
            autocomplete="username"
          />
          <small v-if="submitted && !form.username.trim()" class="text-red-500">
            {{ t('user_management.username_required') }}
          </small>
        </div>

        <!-- New Password -->
        <div>
          <label class="block font-semibold mb-2">
            {{ t('common.password') }}
            <span class="text-xs text-surface-400 font-normal ml-1">({{ t('common.optional') }})</span>
          </label>
          <Password
            v-model="form.password"
            :placeholder="t('user_management.new_password_optional')"
            :feedback="false"
            toggleMask
            class="w-full"
            inputClass="w-full"
            autocomplete="new-password"
          />
        </div>
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" icon="pi pi-times" text @click="hideEditDialog" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveCredentials" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: default;
}
</style>
