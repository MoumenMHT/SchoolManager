<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import ParentService, { type Parent, type CreateParentDTO, type UpdateParentDTO } from '@/service/ParentService';
import StudentService, { type CreateStudentDTO } from '@/service/StudentService';
import ScheduleService from '@/service/ScheduleService';
import ClassesService from '@/service/ClassesService';

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
const parentDetailsDialog = ref(false);
const selectedParentDetails = ref<any>(null);
const loadingDetails = ref(false);
const studentDetailsDialog = ref(false);
const selectedStudentDetails = ref<any>(null);
const studentSchedule = ref<any>(null);
const loadingStudentDetails = ref(false);
const addChildDialog = ref(false);
const newChild = ref<any>({});
const addChildSubmitted = ref(false);
const availableClasses = ref<any[]>([]);

// Days and hours for schedule grid
const weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const schoolHours = [
  { hour: 8, label: '08:00 - 09:00' },
  { hour: 9, label: '09:00 - 10:00' },
  { hour: 10, label: '10:00 - 11:00' },
  { hour: 11, label: '11:00 - 12:00' },
  { hour: 12, label: '12:00 - 13:00' },
  { hour: 13, label: '13:00 - 14:00' },
  { hour: 14, label: '14:00 - 15:00' },
  { hour: 15, label: '15:00 - 16:00' },
  { hour: 16, label: '16:00 - 17:00' },
  { hour: 17, label: '17:00 - 18:00' },
];

// Load parents on mount
onMounted(async () => {
  await loadParents();
  await loadClasses();
});

// Load available classes
const loadClasses = async () => {
  try {
    availableClasses.value = await ClassesService.getClasses();
  } catch (error: any) {
    console.error('Failed to load classes:', error);
  }
};

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

// Show parent details
const showParentDetails = async (parentData: Parent) => {
  try {
    loadingDetails.value = true;
    parentDetailsDialog.value = true;
    
    // Fetch full parent details with children
    const response = await ParentService.getParent(parentData.id);
    selectedParentDetails.value = response;
    selectedParentDetails.value.students?.forEach((student: any) => {
      student.birth_date = student.birth_date ? new Date(student.birth_date).toLocaleDateString() : null;
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load parent details',
      life: 3000
    });
    parentDetailsDialog.value = false;
  } finally {
    loadingDetails.value = false;
  }
};

// Show student details
const showStudentDetails = async (student: any) => {
  try {
    loadingStudentDetails.value = true;
    studentDetailsDialog.value = true;
    
    // Fetch full student details and schedule
    const [studentDetails, scheduleData] = await Promise.all([
      StudentService.getStudent(student.id),
      ScheduleService.getStudentSchedule(student.class?.id, student.class?.academic_year) // Pass class ID and academic year to get the schedule
    ]);
    
    selectedStudentDetails.value = studentDetails;
    
    // Organize schedule data
    if (Array.isArray(scheduleData)) {
      const organizedSchedules: { [day: string]: any[] } = {};
      scheduleData.forEach((schedule: any) => {
        const dayKey = schedule.day.toLowerCase();
        if (!organizedSchedules[dayKey]) {
          organizedSchedules[dayKey] = [];
        }
        organizedSchedules[dayKey].push(schedule);
      });
      studentSchedule.value = organizedSchedules;
    } else {
      studentSchedule.value = scheduleData;
    }
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load student details',
      life: 3000
    });
    studentDetailsDialog.value = false;
  } finally {
    loadingStudentDetails.value = false;
  }
};

// Open add child dialog
const openAddChildDialog = () => {
  newChild.value = {
    parent_id: selectedParentDetails.value.id,
    is_active: true
  };
  addChildSubmitted.value = false;
  addChildDialog.value = true;
};

// Save new child
const saveNewChild = async () => {
  addChildSubmitted.value = true;

  if (!newChild.value.first_name?.trim() || !newChild.value.last_name?.trim() || !newChild.value.code?.trim()) {
    return;
  }

  try {
    const childData: CreateStudentDTO = {
      first_name: newChild.value.first_name,
      last_name: newChild.value.last_name,
      code: newChild.value.code,
      birth_date: newChild.value.birth_date,
      gender: newChild.value.gender,
      class_id: newChild.value.class_id,
      parent_id: selectedParentDetails.value.id,
      enrollment_date: newChild.value.enrollment_date,
      medical_info: newChild.value.medical_info,
      is_active: true
    };

    const created = await StudentService.createStudent(childData);
    
    // Refresh parent details to show the new child
    const updatedParent = await ParentService.getParent(selectedParentDetails.value.id);
    selectedParentDetails.value = updatedParent;
    selectedParentDetails.value.students?.forEach((student: any) => {
      student.birth_date = student.birth_date ? new Date(student.birth_date).toLocaleDateString() : null;
    });

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Child added successfully',
      life: 3000
    });

    addChildDialog.value = false;
    newChild.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to add child',
      life: 3000
    });
  }
};

// Get schedule for a specific day and hour
const getScheduleForSlot = (day: string, hour: number): any | null => {
  const dayKey = day.toLowerCase();
  const daySchedules = studentSchedule.value?.[dayKey] || [];
  const startTime = `${hour.toString().padStart(2, '0')}:00:00`;
  const endTime = `${(hour + 1).toString().padStart(2, '0')}:00:00`;
  
  const found = daySchedules.find((schedule: any) => {
    const scheduleStart = schedule.start_time;
    const scheduleEnd = schedule.end_time;
    return scheduleStart <= startTime && scheduleEnd > startTime;
  }) || null;
  
  return found;
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
      class="p-datatable-sm "
      @row-click="showParentDetails($event.data)"
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


      <Column field="phone" header="Phone" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div v-if="data.phone" class="flex items-center gap-2">
            <i class="pi pi-phone text-sm text-muted-color"></i>
            <span>{{ data.phone }}</span>
          </div>
          <span v-else class="text-muted-color">N/A</span>
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

      <Column header="Actions" :exportable="false" style="min-width: 13rem">
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

    <!-- Parent Details Dialog -->
    <Dialog 
      v-model:visible="parentDetailsDialog" 
      :style="{ width: '700px' }" 
      header="Parent Details" 
      :modal="true"
    >
      <div v-if="loadingDetails" class="flex justify-center items-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      </div>
      
      <div v-else-if="selectedParentDetails" class="flex flex-col gap-6">
        <!-- Parent Information Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            Personal Information
          </h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">Full Name</label>
              <p class="font-semibold">{{ selectedParentDetails.first_name }} {{ selectedParentDetails.last_name }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">CIN</label>
              <p class="font-semibold">{{ selectedParentDetails.cin || 'N/A' }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Phone</label>
              <p class="font-semibold">
                <i class="pi pi-phone text-sm mr-2"></i>
                {{ selectedParentDetails.phone || 'N/A' }}
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Email</label>
              <p class="font-semibold">
                <i class="pi pi-envelope text-sm mr-2"></i>
                {{ selectedParentDetails.email || 'N/A' }}
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Profession</label>
              <p class="font-semibold">{{ selectedParentDetails.profession || 'N/A' }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Account Status</label>
              <p>
                <Tag 
                  :value="getAccountStatusLabel(!!selectedParentDetails.user_id)" 
                  :severity="getAccountStatusSeverity(!!selectedParentDetails.user_id)" 
                />
              </p>
            </div>
          </div>
        </div>

        <!-- Children Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold flex items-center gap-2">
              <i class="pi pi-users text-primary"></i>
              Children
              <Badge :value="selectedParentDetails.students?.length || 0" severity="info" />
            </h3>
            <Button 
              icon="pi pi-plus" 
              label="Add Child"
              size="small"
              @click="openAddChildDialog" 
              v-tooltip.top="'Add New Child'"
            />
          </div>
          
          <div v-if="selectedParentDetails.students && selectedParentDetails.students.length > 0" class="flex flex-col gap-3">
            <div 
              v-for="student in selectedParentDetails.students" 
              :key="student.id"
              class="flex items-center justify-between p-3 bg-surface-50 dark:bg-surface-800 rounded-lg cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
              @click="showStudentDetails(student)"
            >
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                  <i class="pi pi-user text-primary"></i>
                </div>
                <div>
                  <p class="font-semibold">{{ student.first_name }} {{ student.last_name }}</p>
                  <p class="text-sm text-muted-color">{{ student.class.name || 'No class assigned' }}</p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm text-muted-color">Date of Birth</p>
                <p class="font-semibold">{{ student.birth_date || 'N/A' }}</p>
              </div>
            </div>
          </div>
          
          <div v-else class="text-center py-6">
            <i class="pi pi-users text-4xl text-muted-color mb-2 block"></i>
            <p class="text-muted-color">No children registered</p>
          </div>
        </div>
      </div>

      <template #footer>
        <Button 
          label="Close" 
          icon="pi pi-times" 
          @click="parentDetailsDialog = false" 
        />
      </template>
    </Dialog>

    <!-- Add Child Dialog -->
    <Dialog 
      v-model:visible="addChildDialog" 
      :style="{ width: '600px' }" 
      header="Add New Child" 
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <!-- Name Fields -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="child_first_name" class="block font-semibold mb-2">
              First Name <span class="text-red-500">*</span>
            </label>
            <InputText 
              id="child_first_name" 
              v-model="newChild.first_name" 
              required 
              autofocus 
              :invalid="addChildSubmitted && !newChild.first_name" 
              placeholder="Enter first name"
            />
            <small v-if="addChildSubmitted && !newChild.first_name" class="text-red-500">
              First name is required.
            </small>
          </div>
          
          <div>
            <label for="child_last_name" class="block font-semibold mb-2">
              Last Name <span class="text-red-500">*</span>
            </label>
            <InputText 
              id="child_last_name" 
              v-model="newChild.last_name" 
              required 
              :invalid="addChildSubmitted && !newChild.last_name" 
              placeholder="Enter last name"
            />
            <small v-if="addChildSubmitted && !newChild.last_name" class="text-red-500">
              Last name is required.
            </small>
          </div>
        </div>

        <!-- Code and Gender -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="child_code" class="block font-semibold mb-2">
              Student Code <span class="text-red-500">*</span>
            </label>
            <InputText 
              id="child_code" 
              v-model="newChild.code" 
              required 
              :invalid="addChildSubmitted && !newChild.code" 
              placeholder="Enter student code"
            />
            <small v-if="addChildSubmitted && !newChild.code" class="text-red-500">
              Student code is required.
            </small>
          </div>
          
          <div>
            <label for="child_gender" class="block font-semibold mb-2">Gender</label>
            <Select 
              id="child_gender" 
              v-model="newChild.gender" 
              :options="[{label: 'Male', value: 'male'}, {label: 'Female', value: 'female'}]" 
              optionLabel="label" 
              optionValue="value"
              placeholder="Select gender"
            />
          </div>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="child_birth_date" class="block font-semibold mb-2">Date of Birth</label>
            <DatePicker 
              id="child_birth_date" 
              v-model="newChild.birth_date" 
              dateFormat="yy-mm-dd"
              placeholder="Select date of birth"
            />
          </div>
          
          <div>
            <label for="child_enrollment_date" class="block font-semibold mb-2">Enrollment Date</label>
            <DatePicker 
              id="child_enrollment_date" 
              v-model="newChild.enrollment_date" 
              dateFormat="yy-mm-dd"
              placeholder="Select enrollment date"
            />
          </div>
        </div>

        <!-- Class -->
        <div>
          <label for="child_class" class="block font-semibold mb-2">Class</label>
          <Select 
            id="child_class" 
            v-model="newChild.class_id" 
            :options="availableClasses" 
            optionLabel="name" 
            optionValue="id"
            placeholder="Select class"
            filter
            showClear
          />
        </div>

        <!-- Medical Info -->
        <div>
          <label for="child_medical_info" class="block font-semibold mb-2">Medical Information</label>
          <Textarea 
            id="child_medical_info" 
            v-model="newChild.medical_info" 
            rows="3"
            placeholder="Enter any medical information or notes"
          />
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          icon="pi pi-times" 
          text 
          @click="addChildDialog = false" 
        />
        <Button 
          label="Add Child" 
          icon="pi pi-check" 
          @click="saveNewChild" 
        />
      </template>
    </Dialog>

    <!-- Student Details Dialog -->
    <Dialog 
      v-model:visible="studentDetailsDialog" 
      :style="{ width: '800px', maxHeight: '90vh' }" 
      header="Student Details" 
      :modal="true"
    >
      <div v-if="loadingStudentDetails" class="flex justify-center items-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      </div>
      
      <div v-else-if="selectedStudentDetails" class="flex flex-col gap-6">
        <!-- Student Information Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            Personal Information
          </h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">Full Name</label>
              <p class="font-semibold">{{ selectedStudentDetails.first_name }} {{ selectedStudentDetails.last_name }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Student Code</label>
              <p class="font-semibold">{{ selectedStudentDetails.code || 'N/A' }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Date of Birth</label>
              <p class="font-semibold">{{ selectedStudentDetails.birth_date ? new Date(selectedStudentDetails.birth_date).toLocaleDateString() : 'N/A' }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Gender</label>
              <p class="font-semibold">{{ selectedStudentDetails.gender || 'N/A' }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Class</label>
              <p class="font-semibold">{{ selectedStudentDetails.class?.name || 'No class assigned' }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">Enrollment Date</label>
              <p class="font-semibold">{{ selectedStudentDetails.enrollment_date ? new Date(selectedStudentDetails.enrollment_date).toLocaleDateString() : 'N/A' }}</p>
            </div>
            <div class="col-span-2">
              <label class="text-sm text-muted-color">Medical Information</label>
              <p class="font-semibold">{{ selectedStudentDetails.medical_info || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <!-- Schedule Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-calendar text-primary"></i>
            Weekly Schedule
          </h3>
          
          <div v-if="studentSchedule && Object.keys(studentSchedule).length > 0" class="overflow-x-auto">
            <table class="schedule-table w-full border-collapse   ">
              <thead>
                <tr>
                  <th class="schedule-header time-column">Time</th>
                  <th 
                    v-for="day in weekDays" 
                    :key="day" 
                    class="schedule-header"
                  >
                    {{ day }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="timeSlot in schoolHours" :key="timeSlot.hour">
                  <td class="time-column font-semibold text-sm">
                    {{ timeSlot.label }}
                  </td>
                  <td 
                    v-for="day in weekDays" 
                    :key="day"
                    class="schedule-cell"
                  >
                    <template v-if="getScheduleForSlot(day, timeSlot.hour)">
                      <div class="schedule-content has-schedule">
                        <div class="font-semibold text-sm">
                          {{ getScheduleForSlot(day, timeSlot.hour)?.assignment?.subject?.name || getScheduleForSlot(day, timeSlot.hour)?.subject?.name }}
                        </div>
                        <div class="text-xs mt-1 text-muted-color">
                          {{ getScheduleForSlot(day, timeSlot.hour)?.assignment?.teacher?.first_name }} 
                          {{ getScheduleForSlot(day, timeSlot.hour)?.assignment?.teacher?.last_name }}
                        </div>
                        <div v-if="getScheduleForSlot(day, timeSlot.hour)?.room" class="text-xs mt-1">
                          <i class="pi pi-map-marker"></i> {{ getScheduleForSlot(day, timeSlot.hour)?.room }}
                        </div>
                      </div>
                    </template>
                    <div v-else class="schedule-content empty-schedule">
                      <span class="text-muted-color text-xs">-</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <div v-else class="text-center py-6">
            <i class="pi pi-calendar-times text-4xl text-muted-color mb-2 block"></i>
            <p class="text-muted-color">No schedule available</p>
          </div>
        </div>
      </div>

      <template #footer>
        <Button 
          label="Close" 
          icon="pi pi-times" 
          @click="studentDetailsDialog = false" 
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
            :invalid="submitted && !!validationErrors.email" 
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
            :invalid="submitted && !!validationErrors.password" 
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
            :invalid="submitted && !!validationErrors.confirmPassword" 
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

:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: pointer;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
  background-color: var(--surface-100);
}

/* Schedule Table Styles */
.schedule-table {
  min-width: 100%;
  background: var(--surface-card);
  grid: 100% / auto;
}

.schedule-header {
  background: var(--primary-color);
  color: white;
  padding: 0.75rem;
  text-align: center;
  font-weight: 600;
  border: 1px solid var(--surface-border);
}

.time-column {
  background: var(--surface-100);
  min-width: 100px;
  text-align: center;
  padding: 0.75rem;
  border: 1px solid var(--surface-border);
}

.schedule-cell {
  border: 1px solid var(--surface-border);
  padding: 0;
  min-width: 120px;
  height: 80px;
  vertical-align: top;
}

.schedule-content {
  padding: 0.5rem;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}

.has-schedule {
  background: linear-gradient(135deg, var(--primary-100) 0%, var(--primary-50) 100%);
  border-left: 3px solid var(--primary-color);
}

.empty-schedule {
  opacity: 0.3;
}
</style>
