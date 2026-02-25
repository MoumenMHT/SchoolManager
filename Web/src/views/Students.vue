<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import StudentService, { type Student, type CreateStudentDTO, type UpdateStudentDTO } from '@/service/StudentService';
import ParentService, { type Parent } from '@/service/ParentService';
import ClassesService, { type SchoolClass } from '@/service/ClassesService';
import ScheduleService, { type Schedule } from '@/service/ScheduleService';

const toast = useToast();
const dt = ref();
const students = ref<Student[]>([]);
const studentDialog = ref(false);
const deleteStudentDialog = ref(false);
const deleteStudentsDialog = ref(false);
const student = ref<Partial<Student>>({});
const selectedStudents = ref<Student[]>([]);
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});
const submitted = ref(false);
const loading = ref(false);

// Data for dropdowns
const availableClasses = ref<SchoolClass[]>([]);
const availableParents = ref<Parent[]>([]);

// Schedule dialog states
const scheduleDialog = ref(false);
const selectedStudentForSchedule = ref<Student | null>(null);
const studentSchedules = ref<{ [day: string]: Schedule[] }>({});
const scheduleLoading = ref(false);

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
  { hour: 17, label: '17:00 - 18:00' }
];

// Gender options
const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' }
];

// Load students on mount
onMounted(async () => {
  await loadStudents();
  await loadClasses();
  await loadParents();
});

// Load all students
const loadStudents = async () => {
  try {
    loading.value = true;
    students.value = await StudentService.getStudents();
    
    // Add computed properties for each student
    students.value = students.value.map(s => ({
      ...s,
      full_name: `${s.first_name} ${s.last_name}`,
      class_name: s.class?.name || 'No Class',
      parent_name: s.parent ? `${s.parent.first_name} ${s.parent.last_name}` : 'No Parent',
      age: s.birth_date ? calculateAge(new Date(s.birth_date)) : null,
      status_text: s.is_active ? 'Active' : 'Inactive'
    } as any));
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load students',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

// Calculate age from birth date
const calculateAge = (birthDate: Date): number => {
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return age;
};

// Load available classes
const loadClasses = async () => {
  try {
    availableClasses.value = await ClassesService.getClasses();
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load classes',
      life: 3000
    });
  }
};

// Load available parents
const loadParents = async () => {
  try {
    availableParents.value = await ParentService.getParents();
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load parents',
      life: 3000
    });
  }
};

// Open new student dialog
const openNew = () => {
  student.value = {
    is_active: true,
    gender: 'male'
  };
  submitted.value = false;
  studentDialog.value = true;
};

// Hide dialog
const hideDialog = () => {
  studentDialog.value = false;
  submitted.value = false;
};

// Save student (create or update)
const saveStudent = async () => {
  submitted.value = true;

  // Validate required fields
  if (!student.value.first_name?.trim() || 
      !student.value.last_name?.trim() || 
      !student.value.code?.trim()) {
    toast.add({
      severity: 'error',
      summary: 'Validation Error',
      detail: 'Please fill in all required fields (First Name, Last Name, Code)',
      life: 3000
    });
    return;
  }

  try {
    // Format dates for API
    const studentData: any = {
      ...student.value,
      birth_date: student.value.birth_date ? formatDateForAPI(student.value.birth_date) : undefined,
      enrollment_date: student.value.enrollment_date ? formatDateForAPI(student.value.enrollment_date) : undefined
    };

    if (student.value.id) {
      // Update existing student
      const updated = await StudentService.updateStudent(student.value.id, studentData as UpdateStudentDTO);
      const index = students.value.findIndex(s => s.id === student.value.id);
      if (index !== -1) {
        students.value[index] = {
          ...updated,
          full_name: `${updated.first_name} ${updated.last_name}`,
          class_name: updated.class?.name || 'No Class',
          parent_name: updated.parent ? `${updated.parent.first_name} ${updated.parent.last_name}` : 'No Parent',
          age: updated.birth_date ? calculateAge(new Date(updated.birth_date)) : null,
          status_text: updated.is_active ? 'Active' : 'Inactive'
        } as any;
      }
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Student updated successfully',
        life: 3000
      });
    } else {
      // Create new student
      const created = await StudentService.createStudent(studentData as CreateStudentDTO);
      
      // Reload students to get updated data with relationships
      await loadStudents();
      
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Student created successfully',
        life: 3000
      });
    }

    studentDialog.value = false;
    student.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to save student',
      life: 3000
    });
  }
};

// Format date for API (YYYY-MM-DD)
const formatDateForAPI = (date: any): string => {
  if (!date) return '';
  const d = new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Edit student
const editStudent = (studentToEdit: Student) => {
  student.value = { 
    ...studentToEdit,
    // Convert date strings to Date objects for DatePicker
    birth_date: studentToEdit.birth_date ? new Date(studentToEdit.birth_date) : null,
    enrollment_date: studentToEdit.enrollment_date ? new Date(studentToEdit.enrollment_date) : null,
  };
  submitted.value = false;
  studentDialog.value = true;
};

// Confirm delete student
const confirmDeleteStudent = (studentToDelete: Student) => {
  student.value = studentToDelete;
  deleteStudentDialog.value = true;
};

// Delete student
const deleteStudent = async () => {
  try {
    if (student.value.id) {
      await StudentService.deleteStudent(student.value.id);
      students.value = students.value.filter(s => s.id !== student.value.id);
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Student deleted successfully',
        life: 3000
      });
    }
    deleteStudentDialog.value = false;
    student.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to delete student',
      life: 3000
    });
  }
};

// Confirm delete selected
const confirmDeleteSelected = () => {
  deleteStudentsDialog.value = true;
};

// Delete selected students
const deleteSelectedStudents = async () => {
  try {
    const ids = selectedStudents.value.map(s => s.id);
    await StudentService.bulkDeleteStudents(ids);
    students.value = students.value.filter(s => !ids.includes(s.id));
    deleteStudentsDialog.value = false;
    selectedStudents.value = [];
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Students deleted successfully',
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to delete students',
      life: 3000
    });
  }
};

// Export to CSV
const exportCSV = () => {
  dt.value.exportCSV();
};

// Get status severity
const getStatusSeverity = (isActive: boolean) => {
  return isActive ? 'success' : 'danger';
};

// Get status label
const getStatusLabel = (isActive: boolean) => {
  return isActive ? 'Active' : 'Inactive';
};

// Get gender severity
const getGenderSeverity = (gender: string) => {
  return gender === 'male' ? 'info' : 'warn';
};

// Get gender label
const getGenderLabel = (gender: string) => {
  return gender === 'male' ? 'Male' : 'Female';
};

// Schedule Management Functions

// Open schedule dialog for a student
const viewSchedule = async (studentToView: Student) => {
  if (!studentToView.class_id) {
    toast.add({
      severity: 'warn',
      summary: 'No Class',
      detail: 'This student is not assigned to any class',
      life: 3000
    });
    return;
  }

  try {
    scheduleLoading.value = true;
    selectedStudentForSchedule.value = studentToView;

    
    const scheduleData = await ScheduleService.getClassSchedule(studentToView.class_id, studentToView.class?.academic_year);
    
    // If scheduleData is an array, organize it by day
    if (Array.isArray(scheduleData)) {
      const organizedSchedules: { [day: string]: Schedule[] } = {};
      scheduleData.forEach((schedule: Schedule) => {
        const dayKey = schedule.day.toLowerCase();
        if (!organizedSchedules[dayKey]) {
          organizedSchedules[dayKey] = [];
        }
        organizedSchedules[dayKey].push(schedule);
      });
      studentSchedules.value = organizedSchedules;
    } else {
      studentSchedules.value = scheduleData;
    }
    
    scheduleDialog.value = true;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load student schedule',
      life: 3000
    });
  } finally {
    scheduleLoading.value = false;
  }
};

// Get schedule for a specific day and hour
const getScheduleForSlot = (day: string, hour: number): Schedule | null => {
  const dayKey = day.toLowerCase();
  const daySchedules = studentSchedules.value[dayKey] || [];
  const startTime = `${hour.toString().padStart(2, '0')}:00:00`;
  const endTime = `${(hour + 1).toString().padStart(2, '0')}:00:00`;
  
  const found = daySchedules.find(schedule => {
    const scheduleStart = schedule.start_time;
    const scheduleEnd = schedule.end_time;
    return scheduleStart <= startTime && scheduleEnd > startTime;
  }) || null;
  
  return found;
};

// Hide schedule dialog
const hideScheduleDialog = () => {
  scheduleDialog.value = false;
  selectedStudentForSchedule.value = null;
  studentSchedules.value = {};
};
</script>

<template>
  <div class="card">
    <Toast />
    
    <!-- Toolbar -->
    <Toolbar class="mb-6">
      <template #start>
        <Button 
          label="New Student" 
          icon="pi pi-plus" 
          severity="secondary" 
          class="mr-2" 
          @click="openNew" 
        />
        <Button 
          label="Delete" 
          icon="pi pi-trash" 
          severity="secondary" 
          :disabled="!selectedStudents || !selectedStudents.length" 
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
      v-model:selection="selectedStudents"
      :value="students"
      dataKey="id"
      :paginator="true"
      :rows="10"
      :filters="filters"
      :loading="loading"
      exportFilename="students"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :rowsPerPageOptions="[5, 10, 25, 50]"
      currentPageReportTemplate="Showing {first} to {last} of {totalRecords} students"
      class="p-datatable-sm"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">Manage Students</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText 
              v-model="filters['global'].value" 
              placeholder="Search students..." 
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">No students found.</p>
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

      <Column field="code" header="Student Code" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span class="text-muted-color">{{ data.code || 'N/A' }}</span>
        </template>
      </Column>

      <Column field="birth_date" header="Birth Date" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div v-if="data.birth_date" class="flex items-center gap-2">
            <i class="pi pi-calendar text-sm text-muted-color"></i>
            <span>{{ new Date(data.birth_date).toLocaleDateString() }}</span>
          </div>
          <span v-else class="text-muted-color">N/A</span>
        </template>
      </Column>

      <Column field="gender" header="Gender" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Tag 
            v-if="data.gender"
            :value="getGenderLabel(data.gender)" 
            :severity="getGenderSeverity(data.gender)" 
          />
          <span v-else class="text-muted-color">N/A</span>
        </template>
      </Column>

      <Column field="class_name" header="Class" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span v-if="data.class">{{ data.class.name }}</span>
          <span v-else class="text-muted-color">N/A</span>
        </template>
      </Column>

      <Column field="parent_name" header="Parent" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <span v-if="data.parent">{{ data.parent.first_name }} {{ data.parent.last_name }}</span>
          <span v-else class="text-muted-color">N/A</span>
        </template>
      </Column>

      <Column field="enrollment_date" header="Enrollment Date" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div v-if="data.enrollment_date" class="flex items-center gap-2">
            <i class="pi pi-calendar text-sm text-muted-color"></i>
            <span>{{ new Date(data.enrollment_date).toLocaleDateString() }}</span>
          </div>
          <span v-else class="text-muted-color">N/A</span>
        </template>
      </Column>

      <Column field="is_active" header="Status" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Tag 
            :value="getStatusLabel(data.is_active)" 
            :severity="getStatusSeverity(data.is_active)" 
          />
        </template>
      </Column>

      <Column :exportable="false" style="min-width: 13rem">
        <template #body="{ data }">
          <Button 
            icon="pi pi-calendar" 
            outlined 
            rounded 
            severity="info"
            class="mr-2" 
            @click="viewSchedule(data)" 
            v-tooltip.top="'View Schedule'"
            :disabled="!data.class_id"
          />
          <Button 
            icon="pi pi-pencil" 
            outlined 
            rounded 
            class="mr-2" 
            @click="editStudent(data)" 
            v-tooltip.top="'Edit'"
          />
          <Button 
            icon="pi pi-trash" 
            outlined 
            rounded 
            severity="danger" 
            @click="confirmDeleteStudent(data)" 
            v-tooltip.top="'Delete'"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Add/Edit Student Dialog -->
    <Dialog 
      v-model:visible="studentDialog" 
      :style="{ width: '550px' }" 
      header="Student Details" 
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
              v-model.trim="student.first_name" 
              required 
              autofocus 
              :invalid="submitted && !student.first_name"
              placeholder="Enter first name"
            />
            <small v-if="submitted && !student.first_name" class="text-red-500">
              First name is required.
            </small>
          </div>

          <div>
            <label for="last_name" class="block font-semibold mb-2">
              Last Name <span class="text-red-500">*</span>
            </label>
            <InputText 
              id="last_name" 
              v-model.trim="student.last_name" 
              required 
              :invalid="submitted && !student.last_name"
              placeholder="Enter last name"
            />
            <small v-if="submitted && !student.last_name" class="text-red-500">
              Last name is required.
            </small>
          </div>
        </div>

        <!-- Student Code -->
        <div>
          <label for="code" class="block font-semibold mb-2">
            Student Code <span class="text-red-500">*</span>
          </label>
          <InputText 
            id="code" 
            v-model.trim="student.code" 
            required 
            :invalid="submitted && !student.code"
            placeholder="e.g., STU2026001"
          />
          <small v-if="submitted && !student.code" class="text-red-500">
            Student code is required.
          </small>
        </div>

        <!-- Birth Date and Gender -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="birth_date" class="block font-semibold mb-2">
              Birth Date
            </label>
            <DatePicker 
              id="birth_date" 
              v-model="student.birth_date" 
              dateFormat="yy-mm-dd"
              showIcon
              placeholder="Select birth date"
            />
          </div>

          <div>
            <label for="gender" class="block font-semibold mb-2">
              Gender
            </label>
            <Select 
              id="gender" 
              v-model="student.gender" 
              :options="genderOptions" 
              optionLabel="label" 
              optionValue="value"
              placeholder="Select gender"
            />
          </div>
        </div>

        <!-- Class and Parent -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="class" class="block font-semibold mb-2">
              Class
            </label>
            <Select 
              id="class" 
              v-model="student.class_id" 
              :options="availableClasses" 
              optionLabel="name" 
              optionValue="id"
              placeholder="Select class"
              filter
              showClear
            />
          </div>

          <div>
            <label for="parent" class="block font-semibold mb-2">
              Parent
            </label>
            <Select 
              id="parent" 
              v-model="student.parent_id" 
              :options="availableParents" 
              optionLabel="first_name" 
              optionValue="id"
              placeholder="Select parent"
              filter
              showClear
            >
              <template #option="{ option }">
                <div>{{ option.first_name }} {{ option.last_name }}</div>
              </template>
              <template #value="{ value }">
                <div v-if="value">
                  {{ availableParents.find(p => p.id === value)?.first_name }} 
                  {{ availableParents.find(p => p.id === value)?.last_name }}
                </div>
                <span v-else>Select parent</span>
              </template>
            </Select>
          </div>
        </div>

        <!-- Enrollment Date -->
        <div>
          <label for="enrollment_date" class="block font-semibold mb-2">
            Enrollment Date
          </label>
          <DatePicker 
            id="enrollment_date" 
            v-model="student.enrollment_date" 
            dateFormat="yy-mm-dd"
            showIcon
            placeholder="Select enrollment date"
          />
        </div>

        <!-- Medical Info -->
        <div>
          <label for="medical_info" class="block font-semibold mb-2">
            Medical Information
          </label>
          <Textarea 
            id="medical_info" 
            v-model="student.medical_info" 
            rows="3" 
            placeholder="Any medical conditions or special requirements..."
          />
        </div>

        <!-- Active Status -->
        <div class="flex items-center gap-2">
          <Checkbox 
            inputId="is_active" 
            v-model="student.is_active" 
            :binary="true" 
          />
          <label for="is_active" class="font-semibold cursor-pointer">
            Active Student
          </label>
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
          @click="saveStudent" 
        />
      </template>
    </Dialog>

    <!-- Delete Student Dialog -->
    <Dialog 
      v-model:visible="deleteStudentDialog" 
      :style="{ width: '450px' }" 
      header="Confirm Delete" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span v-if="student">
          Are you sure you want to delete <strong>{{ student.first_name }} {{ student.last_name }}</strong>?
        </span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="deleteStudentDialog = false" 
        />
        <Button 
          label="Yes" 
          icon="pi pi-check" 
          severity="danger"
          @click="deleteStudent" 
        />
      </template>
    </Dialog>

    <!-- Delete Multiple Students Dialog -->
    <Dialog 
      v-model:visible="deleteStudentsDialog" 
      :style="{ width: '450px' }" 
      header="Confirm Delete" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span v-if="selectedStudents">
          Are you sure you want to delete the selected students?
        </span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="deleteStudentsDialog = false" 
        />
        <Button 
          label="Yes" 
          icon="pi pi-check" 
          severity="danger"
          @click="deleteSelectedStudents" 
        />
      </template>
    </Dialog>

    <!-- View Student Schedule Dialog (Read-Only) -->
    <Dialog 
      v-model:visible="scheduleDialog" 
      :style="{ width: '95vw', maxWidth: '1200px' }" 
      :header="selectedStudentForSchedule ? `Schedule - ${selectedStudentForSchedule.first_name} ${selectedStudentForSchedule.last_name}` : 'Schedule'" 
      :modal="true"
      maximizable
    >
      <div v-if="scheduleLoading" class="text-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary mb-3"></i>
        <p class="text-muted-color">Loading schedule...</p>
      </div>

      <div v-else>
        <div v-if="selectedStudentForSchedule?.class" class="mb-4 p-3 bg-surface-50 dark:bg-surface-800 rounded-border">
          <div class="flex items-center gap-2">
            <i class="pi pi-building text-primary"></i>
            <span class="font-semibold">Class:</span>
            <span>{{ selectedStudentForSchedule.class.name }}</span>
          </div>
        </div>

        <div class="schedule-container" style="overflow-x: auto;">
          <table class="schedule-table">
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
                        {{ getScheduleForSlot(day, timeSlot.hour)?.assignment?.subject?.name }}
                      </div>
                      <div class="text-xs mt-1">
                        {{ getScheduleForSlot(day, timeSlot.hour)?.assignment?.teacher?.first_name }} {{ getScheduleForSlot(day, timeSlot.hour)?.assignment?.teacher?.last_name }}
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
      </div>

      <template #footer>
        <Button 
          label="Close" 
          icon="pi pi-times" 
          @click="hideScheduleDialog" 
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.schedule-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
}

.schedule-header {
  background: var(--primary-color);
  color: white;
  padding: 12px 8px;
  text-align: center;
  font-weight: 600;
  border: 1px solid var(--surface-border);
}

.time-column {
  background: var(--surface-50);
  color: var(--text-color);
  width: 120px;
  text-align: center;
}

.schedule-cell {
  border: 1px solid var(--surface-border);
  padding: 4px;
  height: 80px;
  vertical-align: top;
  background: var(--surface-0);
}

.schedule-content {
  height: 100%;
  padding: 8px;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}

.has-schedule {
  background: var(--primary-50);
  border: 1px solid var(--primary-200);
  color: var(--primary-700);
}

.empty-schedule {
  background: transparent;
  color: var(--text-color-secondary);
}

:deep(.p-dialog-content) {
  padding-top: 0;
}
</style>
