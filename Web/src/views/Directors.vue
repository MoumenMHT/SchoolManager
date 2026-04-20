<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import UserService from '@/service/UserService';
import { FilterMatchMode } from '@primevue/core/api';

const toast = useToast();
const { t } = useI18n();

// State
const users = ref<any[]>([]);
const loading = ref(false);

// Dialog
const userDialog = ref(false);
const deleteUserDialog = ref(false);
const selectedUser = ref<any>(null);
const isNew = ref(false);
const saving = ref(false);
const submitted = ref(false);

// Form
const form = ref({
  username: '',
  email: '',
  password: '',
  status: 'active',
  phone: '',
  address: '',
  role: 'primary_director'
});

// Search
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
];

const roleOptions = [
  { label: 'Primary Director', value: 'primary_director' },
  { label: 'CEM Director', value: 'cem_director' },
  { label: 'Lycée Director', value: 'lycee_director' },
];

function getRoleLabel(roleValue: string) {
  const match = roleOptions.find(r => r.value === roleValue);
  return match ? match.label : roleValue;
}

async function loadUsers() {
  loading.value = true;
  try {
    users.value = await UserService.getUsersByRoles(['primary_director', 'cem_director', 'lycee_director']);
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('admin_users.load_error'), life: 3000 });
  } finally {
    loading.value = false;
  }
}

function openNew() {
  isNew.value = true;
  submitted.value = false;
  form.value = {
    username: '',
    email: '',
    password: '',
    status: 'active',
    phone: '',
    address: '',
    role: 'primary_director'
  };
  selectedUser.value = null;
  userDialog.value = true;
}

function editUser(user: any) {
  isNew.value = false;
  submitted.value = false;
  selectedUser.value = user;
  form.value = {
    username: user.username ?? '',
    email: user.email ?? '',
    password: '',
    status: (user.is_active || user.is_active === 1) ? 'active' : 'inactive',
    phone: user.phone ?? '',
    address: user.address ?? '',
    role: user.role ?? 'primary_director'
  };
  userDialog.value = true;
}

async function saveUser() {
  submitted.value = true;

  if (!form.value.username || !form.value.email || !form.value.role) {
    toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('validation.required_fields'), life: 3000 });
    return;
  }

  if (isNew.value && !form.value.password) {
    toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('validation.password_required'), life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const payload: any = {
      username: form.value.username,
      email: form.value.email,
      phone: form.value.phone || null,
      address: form.value.address || null,
      is_active: form.value.status === 'active',
      role: form.value.role,
    };

    if (form.value.password) {
      payload.password = form.value.password;
    }

    if (isNew.value) {
      await UserService.createUser(payload);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('admin_users.created'), life: 3000 });
    } else {
      await UserService.updateUser(selectedUser.value.id, payload);
      toast.add({ severity: 'success', summary: t('common.success'), detail: t('admin_users.updated'), life: 3000 });
    }

    userDialog.value = false;
    await loadUsers();
  } catch (err: any) {
    const msg = err?.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(', ')
      : (err?.response?.data?.message ?? t('admin_users.save_error'));
    toast.add({ severity: 'error', summary: t('common.error'), detail: msg, life: 4000 });
  } finally {
    saving.value = false;
  }
}

function confirmDelete(user: any) {
  selectedUser.value = user;
  deleteUserDialog.value = true;
}

async function deleteUser() {
  try {
    await UserService.deleteUser(selectedUser.value.id);
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('admin_users.deleted'), life: 3000 });
    deleteUserDialog.value = false;
    await loadUsers();
  } catch (err: any) {
    const msg = err?.response?.data?.message ?? t('admin_users.delete_error');
    toast.add({ severity: 'error', summary: t('common.error'), detail: msg, life: 3000 });
  }
}

onMounted(async () => {
  await loadUsers();
});
</script>

<template>
  <div class="card">
    <Toast />

    <Toolbar class="mb-6">
      <template #start>
        <Button :label="t('admin_users.new_user')" icon="pi pi-plus" severity="secondary" class="mr-2" @click="openNew" />
      </template>
    </Toolbar>

    <DataTable
      :value="users"
      dataKey="id"
      :paginator="true"
      :rows="10"
      :filters="filters"
      :loading="loading"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :rowsPerPageOptions="[5, 10, 25, 50]"
      :currentPageReportTemplate="t('admin_users.page_report')"
      class="p-datatable-sm"
      :globalFilterFields="['username', 'email', 'phone', 'role']"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">{{ t('admin_users.directors_title', 'Manage Directors') }}</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="filters['global'].value"
              :placeholder="t('admin_users.search_placeholder')"
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">{{ t('admin_users.no_users') }}</p>
        </div>
      </template>

      <Column field="username" :header="t('common.username')" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            <span class="font-semibold">{{ data.username }}</span>
          </div>
        </template>
      </Column>

      <Column field="role" :header="t('admin_users.role', 'Role')" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <Tag :value="getRoleLabel(data.role)" severity="info" class="text-xs font-semibold" />
        </template>
      </Column>

      <Column field="email" :header="t('common.email')" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div v-if="data.email" class="flex items-center gap-2">
            <i class="pi pi-envelope text-sm text-muted-color"></i>
            <span>{{ data.email }}</span>
          </div>
          <span v-else class="text-muted-color">{{ t('common.na') }}</span>
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

      <Column field="is_active" :header="t('common.status')" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Tag :value="(data.is_active || data.is_active === 1) ? t('common.active') : t('common.inactive')" 
               :severity="(data.is_active || data.is_active === 1) ? 'success' : 'danger'" class="capitalize text-xs font-semibold" />
        </template>
      </Column>

      <Column :header="t('common.actions')" :exportable="false" style="min-width: 10rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-pencil"
            outlined
            rounded
            class="mr-2"
            @click="editUser(data)"
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
      v-model:visible="userDialog"
      :style="{ width: '550px' }"
      :header="isNew ? t('admin_users.new_user') : t('admin_users.edit_user')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold mb-2">{{ t('common.username') }} <span class="text-red-500">*</span></label>
            <InputText v-model="form.username" :placeholder="t('common.username')" :invalid="submitted && !form.username" />
          </div>
          <div>
            <label class="block font-semibold mb-2">{{ t('admin_users.role', 'Role') }} <span class="text-red-500">*</span></label>
            <Select v-model="form.role" :options="roleOptions" optionLabel="label" optionValue="value" :invalid="submitted && !form.role" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold mb-2">{{ t('common.email') }} <span class="text-red-500">*</span></label>
            <InputText v-model="form.email" type="email" placeholder="email@example.com" :invalid="submitted && !form.email" />
          </div>
          <div>
            <label class="block font-semibold mb-2">
              {{ t('common.password') }}
              <span v-if="isNew" class="text-red-500">*</span>
              <span v-else class="text-xs text-surface-400 font-normal ml-1">({{ t('common.optional') }})</span>
            </label>
            <InputText v-model="form.password" type="password" :invalid="submitted && isNew && !form.password" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold mb-2">{{ t('common.phone') }}</label>
            <InputText v-model="form.phone" />
          </div>
          <div>
            <label class="block font-semibold mb-2">{{ t('common.status') }}</label>
            <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" icon="pi pi-times" text @click="userDialog = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveUser" />
      </template>
    </Dialog>

    <!-- Delete User Dialog -->
    <Dialog
      v-model:visible="deleteUserDialog"
      :style="{ width: '450px' }"
      :header="t('common.confirm_deletion')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-3xl text-red-500"></i>
        <span>
          {{ t('common.are_you_sure_delete') }} <b>{{ selectedUser?.username }}</b>?
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteUserDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="deleteUser"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: default;
}
</style>
