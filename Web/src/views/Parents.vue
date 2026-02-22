<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import ParentService, { type Parent, type CreateParentDTO, type UpdateParentDTO } from '@/service/ParentService';

const toast = useToast();
const dt = ref();
const parents = ref<Parent[]>([]);
const parentDialog = ref(false);
const deleteParentDialog = ref(false);
const deleteParentsDialog = ref(false);
const parent = ref<Partial<Parent>>({});
const selectedParents = ref<Parent[]>([]);
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});
const submitted = ref(false);
const loading = ref(false);
const createAccountDialog = ref(false);
const accountData = ref({
  email: '',
  password: '',
  confirmPassword: ''
});
const validationErrors = ref({
  email: '',
  password: '',
  confirmPassword: ''
});

// Load parents on mount
onMounted(async () => {
  await loadParents();
});

// Load all parents
const loadParents = async () => {
  try {
    loading.value = true;
    parents.value = await ParentService.getParents();
    
    // Add computed properties for each parent
    parents.value = parents.value.map(p => ({
      ...p,
      full_name: `${p.first_name} ${p.last_name}`,
      has_account: !!p.user_id
    }));
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load parents',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

// Open new parent dialog
const openNew = () => {
  parent.value = {};
  submitted.value = false;
  parentDialog.value = true;
};

// Hide dialog
const hideDialog = () => {
  parentDialog.value = false;
  submitted.value = false;
};

// Save parent (create or update)
const saveParent = async () => {
  submitted.value = true;

  if (!parent.value.first_name?.trim() || !parent.value.last_name?.trim()) {
    return;
  }

  try {
    if (parent.value.id) {
      // Update existing parent
      const updated = await ParentService.updateParent(parent.value.id, parent.value as UpdateParentDTO);
      const index = parents.value.findIndex(p => p.id === parent.value.id);
      if (index !== -1) {
        parents.value[index] = {
          ...updated,
          full_name: `${updated.first_name} ${updated.last_name}`,
          has_account: !!updated.user_id
        };
      }
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Parent updated successfully',
        life: 3000
      });
    } else {
      // Create new parent
      const created = await ParentService.createParent(parent.value as CreateParentDTO);
      parents.value.push({
        ...created,
        full_name: `${created.first_name} ${created.last_name}`,
        has_account: !!created.user_id
      });
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Parent created successfully',
        life: 3000
      });
    }

    parentDialog.value = false;
    parent.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to save parent',
      life: 3000
    });
  }
};

// Edit parent
const editParent = (parentToEdit: Parent) => {
  parent.value = { ...parentToEdit };
  parentDialog.value = true;
};

// Confirm delete parent
const confirmDeleteParent = (parentToDelete: Parent) => {
  parent.value = parentToDelete;
  deleteParentDialog.value = true;
};

// Delete parent
const deleteParent = async () => {
  try {
    await ParentService.deleteParent(parent.value.id!);
    parents.value = parents.value.filter(p => p.id !== parent.value.id);
    deleteParentDialog.value = false;
    parent.value = {};
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Parent deleted successfully',
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to delete parent',
      life: 3000
    });
  }
};

// Export to CSV
const exportCSV = () => {
  dt.value.exportCSV();
};

// Confirm delete selected parents
const confirmDeleteSelected = () => {
  deleteParentsDialog.value = true;
};

// Delete selected parents
const deleteSelectedParents = async () => {
  try {
    const ids = selectedParents.value.map(p => p.id);
    await ParentService.bulkDeleteParents(ids);
    parents.value = parents.value.filter(p => !selectedParents.value.some(sp => sp.id === p.id));
    deleteParentsDialog.value = false;
    selectedParents.value = [];
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Parents deleted successfully',
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to delete parents',
      life: 3000
    });
  }
};

// Get account status badge
const getAccountStatusSeverity = (hasAccount: boolean) => {
  return hasAccount ? 'success' : 'warn';
};

const getAccountStatusLabel = (hasAccount: boolean) => {
  return hasAccount ? 'Active Account' : 'No Account';
};

// Open create account dialog
const openCreateAccount = (parentData: Parent) => {
  parent.value = parentData;
  accountData.value = {
    email: parentData.email || '',
    password: '',
    confirmPassword: ''
  };
  submitted.value = false;
  validationErrors.value = {
    email: '',
    password: '',
    confirmPassword: ''
  };
  createAccountDialog.value = true;
};

// Hide create account dialog
const hideCreateAccountDialog = () => {
  createAccountDialog.value = false;
  accountData.value = {
    email: '',
    password: '',
    confirmPassword: ''
  };
  validationErrors.value = {
    email: '',
    password: '',
    confirmPassword: ''
  };
  submitted.value = false;
};

// Validate email
const validateEmail = (email: string): string => {
  if (!email || !email.trim()) {
    return 'Email is required';
  }
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    return 'Please enter a valid email address';
  }
  return '';
};

// Validate password
const validatePassword = (password: string): string => {
  if (!password || !password.trim()) {
    return 'Password is required';
  }
  if (password.length < 8) {
    return 'Password must be at least 8 characters long';
  }
  return '';
};

// Validate confirm password
const validateConfirmPassword = (password: string, confirmPassword: string): string => {
  if (!confirmPassword || !confirmPassword.trim()) {
    return 'Please confirm your password';
  }
  if (password !== confirmPassword) {
    return 'Passwords do not match';
  }
  return '';
};

// Create user account for parent
const createUserAccount = async () => {
  submitted.value = true;

  // Client-side validation
  validationErrors.value.email = validateEmail(accountData.value.email);
  validationErrors.value.password = validatePassword(accountData.value.password);
  validationErrors.value.confirmPassword = validateConfirmPassword(
    accountData.value.password, 
    accountData.value.confirmPassword
  );

  // Check if there are any validation errors
  if (validationErrors.value.email || validationErrors.value.password || validationErrors.value.confirmPassword) {
    return;
  }

  try {
    const updated = await ParentService.createUserAccount(
      parent.value.id!,
      accountData.value.email,
      accountData.value.password
    );
    
    // Update the parent in the list
    const index = parents.value.findIndex(p => p.id === parent.value.id);
    if (index !== -1) {
      parents.value[index] = {
        ...updated,
        full_name: `${updated.first_name} ${updated.last_name}`,
        has_account: !!updated.user_id
      };
    }
    
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'User account created successfully',
      life: 3000
    });
    
    createAccountDialog.value = false;
    accountData.value = {
      email: '',
      password: '',
      confirmPassword: ''
    };
    validationErrors.value = {
      email: '',
      password: '',
      confirmPassword: ''
    };
    submitted.value = false;
  } catch (error: any) {
    // Handle server-side validation errors
    if (error.response?.data?.errors) {
      const serverErrors = error.response.data.errors;
      validationErrors.value.email = serverErrors.email?.[0] || '';
      validationErrors.value.password = serverErrors.password?.[0] || '';
    } else {
      toast.add({
        severity: 'error',
        summary: 'Error',
        detail: error.response?.data?.message || 'Failed to create user account',
        life: 3000
      });
    }
  }
};
</script>

<template>
  <div class="card">
    <Toast />
    
    <!-- Toolbar -->
    <Toolbar class="mb-6">
      <template #start>
        <Button 
          label="New Parent" 
          icon="pi pi-plus" 
          severity="secondary" 
          class="mr-2" 
          @click="openNew" 
        />
        <Button 
          label="Delete" 
          icon="pi pi-trash" 
          severity="secondary" 
          :disabled="!selectedParents || !selectedParents.length" 
          @click="confirmDeleteSelected" 
        />
      </template>

      <template #end>
        <Button 
          label="Export" 
          icon="pi pi-upload" 
          severity="secondary" 
          @click="exportCSV" 
        />
      </template>
    </Toolbar>

    <!-- DataTable -->
    <DataTable
      ref="dt"
      v-model:selection="selectedParents"
      :value="parents"
      dataKey="id"
      :paginator="true"
      :rows="10"
      :filters="filters"
      :loading="loading"
      exportFilename="parents"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :rowsPerPageOptions="[5, 10, 25, 50]"
      currentPageReportTemplate="Showing {first} to {last} of {totalRecords} parents"
      class="p-datatable-sm"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">Manage Parents</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText 
              v-model="filters['global'].value" 
              placeholder="Search parents..." 
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">No parents found.</p>
        </div>
      </template>

      <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>
      
      <Column field="full_name" header="Full Name" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            <span class="font-semibold">{{ data.full_name }}</span>
          </div>
        </template>
      </Column>

      <Column field="cin" header="CIN" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span class="text-muted-color">{{ data.cin || 'N/A' }}</span>
        </template>
      </Column>

      <Column field="phone" header="Phone" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div v-if="data.phone" class="flex items-center gap-2">
            <i class="pi pi-phone text-sm text-muted-color"></i>
            <span>{{ data.phone }}</span>
          </div>
          <span v-else class="text-muted-color">N/A</span>
        </template>
      </Column>

      <Column field="email" header="Email" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div v-if="data.email" class="flex items-center gap-2">
            <i class="pi pi-envelope text-sm text-muted-color"></i>
            <span>{{ data.email }}</span>
          </div>
          <span v-else class="text-muted-color">N/A</span>
        </template>
      </Column>

      <Column field="profession" header="Profession" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <span>{{ data.profession || 'N/A' }}</span>
        </template>
      </Column>

      <Column field="has_account" header="Account Status" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <Tag 
            :value="getAccountStatusLabel(data.has_account)" 
            :severity="getAccountStatusSeverity(data.has_account)" 
          />
        </template>
      </Column>

      <Column field="students_count" header="Children" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Badge 
            :value="data.students_count || 0" 
            :severity="data.students_count > 0 ? 'info' : 'secondary'" 
          />
        </template>
      </Column>

      <Column :exportable="false" style="min-width: 13rem">
        <template #body="{ data }">
          <Button 
            v-if="!data.has_account"
            icon="pi pi-user-plus" 
            outlined 
            rounded 
            severity="success"
            class="mr-2" 
            @click="openCreateAccount(data)" 
            v-tooltip.top="'Create Account'"
          />
          <Button 
            icon="pi pi-pencil" 
            outlined 
            rounded 
            class="mr-2" 
            @click="editParent(data)" 
            v-tooltip.top="'Edit'"
          />
          <Button 
            icon="pi pi-trash" 
            outlined 
            rounded 
            severity="danger" 
            @click="confirmDeleteParent(data)" 
            v-tooltip.top="'Delete'"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Add/Edit Parent Dialog -->
    <Dialog 
      v-model:visible="parentDialog" 
      :style="{ width: '550px' }" 
      header="Parent Details" 
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <!-- Name Fields -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="first_name" class="block font-semibold mb-2">
              First Name <span class="text-red-500">*</span>
            </label>
            <InputText 
              id="first_name" 
              v-model="parent.first_name" 
              required 
              autofocus 
              :invalid="submitted && !parent.first_name" 
              placeholder="Enter first name"
            />
            <small v-if="submitted && !parent.first_name" class="text-red-500">
              First name is required.
            </small>
          </div>
          
          <div>
            <label for="last_name" class="block font-semibold mb-2">
              Last Name <span class="text-red-500">*</span>
            </label>
            <InputText 
              id="last_name" 
              v-model="parent.last_name" 
              required 
              :invalid="submitted && !parent.last_name" 
              placeholder="Enter last name"
            />
            <small v-if="submitted && !parent.last_name" class="text-red-500">
              Last name is required.
            </small>
          </div>
        </div>

        <!-- CIN -->
        <div>
          <label for="cin" class="block font-semibold mb-2">CIN (National ID)</label>
          <InputText 
            id="cin" 
            v-model="parent.cin" 
            placeholder="Enter national ID number"
          />
        </div>

        <!-- Contact Fields -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="phone" class="block font-semibold mb-2">Phone</label>
            <InputText 
              id="phone" 
              v-model="parent.phone" 
              placeholder="+212 xxx xxx xxx"
            />
          </div>
          
          <div>
            <label for="email" class="block font-semibold mb-2">Email</label>
            <InputText 
              id="email" 
              v-model="parent.email" 
              type="email"
              placeholder="email@example.com"
            />
          </div>
        </div>

        <!-- Profession -->
        <div>
          <label for="profession" class="block font-semibold mb-2">Profession</label>
          <InputText 
            id="profession" 
            v-model="parent.profession" 
            placeholder="Enter profession"
          />
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          icon="pi pi-times" 
          text 
          @click="hideDialog" 
        />
        <Button 
          label="Save" 
          icon="pi pi-check" 
          @click="saveParent" 
        />
      </template>
    </Dialog>

    <!-- Delete Parent Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteParentDialog" 
      :style="{ width: '450px' }" 
      header="Confirm Deletion" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-3xl text-red-500"></i>
        <span v-if="parent">
          Are you sure you want to delete <b>{{ parent.full_name }}</b>?
        </span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="deleteParentDialog = false" 
        />
        <Button 
          label="Yes" 
          icon="pi pi-check" 
          severity="danger"
          @click="deleteParent" 
        />
      </template>
    </Dialog>

    <!-- Delete Multiple Parents Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteParentsDialog" 
      :style="{ width: '450px' }" 
      header="Confirm Deletion" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-3xl text-red-500"></i>
        <span>Are you sure you want to delete the selected parents?</span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="deleteParentsDialog = false" 
        />
        <Button 
          label="Yes" 
          icon="pi pi-check" 
          severity="danger"
          @click="deleteSelectedParents" 
        />
      </template>
    </Dialog>

    <!-- Create User Account Dialog -->
    <Dialog 
      v-model:visible="createAccountDialog" 
      :style="{ width: '500px' }" 
      header="Create User Account" 
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4 rounded">
          <div class="flex items-start gap-3">
            <i class="pi pi-info-circle text-blue-600 text-xl mt-1"></i>
            <div>
              <p class="font-semibold text-blue-900 dark:text-blue-100 mb-1">Creating account for:</p>
              <p class="text-blue-700 dark:text-blue-300">{{ parent.full_name }}</p>
            </div>
          </div>
        </div>

        <!-- Email -->
        <div>
          <label for="account_email" class="block font-semibold mb-2">
            Email <span class="text-red-500">*</span>
          </label>
          <InputText 
            id="account_email" 
            v-model="accountData.email" 
            type="email"
            required 
            autofocus
            :invalid="submitted && validationErrors.email" 
            placeholder="Enter email address"
            @blur="validationErrors.email = validateEmail(accountData.email)"
          />
          <small v-if="submitted && validationErrors.email" class="text-red-500">
            {{ validationErrors.email }}
          </small>
        </div>

        <!-- Password -->
        <div>
          <label for="account_password" class="block font-semibold mb-2">
            Password <span class="text-red-500">*</span>
          </label>
          <Password 
            id="account_password" 
            v-model="accountData.password" 
            required
            toggleMask
            :invalid="submitted && validationErrors.password" 
            placeholder="Enter password (min. 8 characters)"
            :feedback="true"
            @blur="validationErrors.password = validatePassword(accountData.password)"
          />
          <small v-if="submitted && validationErrors.password" class="text-red-500">
            {{ validationErrors.password }}
          </small>
        </div>

        <!-- Confirm Password -->
        <div>
          <label for="account_confirm_password" class="block font-semibold mb-2">
            Confirm Password <span class="text-red-500">*</span>
          </label>
          <Password 
            id="account_confirm_password" 
            v-model="accountData.confirmPassword" 
            required
            toggleMask
            :invalid="submitted && validationErrors.confirmPassword" 
            placeholder="Confirm password"
            :feedback="false"
            @blur="validationErrors.confirmPassword = validateConfirmPassword(accountData.password, accountData.confirmPassword)"
          />
          <small v-if="submitted && validationErrors.confirmPassword" class="text-red-500">
            {{ validationErrors.confirmPassword }}
          </small>
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          icon="pi pi-times" 
          text 
          @click="hideCreateAccountDialog" 
        />
        <Button 
          label="Create Account" 
          icon="pi pi-check" 
          severity="success"
          @click="createUserAccount" 
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
  background-color: var(--surface-50);
  color: var(--text-color);
  font-weight: 600;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
  background-color: var(--surface-100);
}
</style>
