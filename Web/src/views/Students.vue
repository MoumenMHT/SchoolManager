<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import StudentService, { type Student, type CreateStudentDTO, type UpdateStudentDTO, type StudentHistory } from '@/service/StudentService';
import ParentService, { type Parent } from '@/service/ParentService';
import ClassesService, { type SchoolClass } from '@/service/ClassesService';
import ScheduleService, { type Schedule } from '@/service/ScheduleService';

const { t } = useI18n();

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
const studentDetailsDialog = ref(false);
const selectedStudentDetails = ref<Student | null>(null);
const loadingDetails = ref(false);

// History dialog states
const studentHistory = ref<StudentHistory[]>([]);

// Days and hours for schedule grid
// Each entry has an English key (used for schedule data lookup) and a translated label (used for display)
const weekDays = computed(() => [
  { key: 'monday',    label: t('common.monday') },
  { key: 'tuesday',   label: t('common.tuesday') },
  { key: 'wednesday', label: t('common.wednesday') },
  { key: 'thursday',  label: t('common.thursday') },
  { key: 'friday',    label: t('common.friday') },
  { key: 'saturday',  label: t('common.saturday') }
]);

const schoolHours = [
  { hour: 8,  label: '08:00 - 09:00' },
  { hour: 9,  label: '09:00 - 10:00' },
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
const genderOptions = computed(() => [
  { label: t('common.male'),   value: 'male' },
  { label: t('common.female'), value: 'female' }
]);

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
      class_name: s.class?.name || t('students.no_class'),
      parent_name: s.parent ? `${s.parent.first_name} ${s.parent.last_name}` : t('students.no_parent'),
      age: s.birth_date ? calculateAge(new Date(s.birth_date)) : null,
      status_text: s.is_active ? t('common.active') : t('common.inactive')
    } as any));
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('students.load_error'),
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
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_failed'),
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
      summary: t('common.error'),
      detail: error.response?.data?.message || t('parents.load_error'),
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
      summary: t('common.warning'),
      detail: t('students.required_fields'),
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
          class_name: updated.class?.name || t('students.no_class'),
          parent_name: updated.parent ? `${updated.parent.first_name} ${updated.parent.last_name}` : t('students.no_parent'),
          age: updated.birth_date ? calculateAge(new Date(updated.birth_date)) : null,
          status_text: updated.is_active ? t('common.active') : t('common.inactive')
        } as any;
      }
      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('students.update_success'),
        life: 3000
      });
    } else {
      // Create new student
      const created = await StudentService.createStudent(studentData as CreateStudentDTO);

      // Reload students to get updated data with relationships
      await loadStudents();

      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('students.create_success'),
        life: 3000
      });
    }

    studentDialog.value = false;
    student.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('students.save_error'),
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
        summary: t('common.success'),
        detail: t('students.delete_success'),
        life: 3000
      });
    }
    deleteStudentDialog.value = false;
    student.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('students.delete_error'),
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
      summary: t('common.success'),
      detail: t('students.delete_multiple_success'),
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('students.delete_multiple_error'),
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
  return isActive ? t('common.active') : t('common.inactive');
};

// Get gender severity
const getGenderSeverity = (gender: string) => {
  return gender === 'male' ? 'info' : 'warn';
};

// Get gender label
const getGenderLabel = (gender: string) => {
  return gender === 'male' ? t('common.male') : t('common.female');
};

// Schedule Management Functions

// Open schedule dialog for a student
const viewSchedule = async (studentToView: Student) => {
  if (!studentToView.class_id) {
    toast.add({
      severity: 'warn',
      summary: t('students.no_class_warning'),
      detail: t('students.no_class_assigned'),
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
      summary: t('common.error'),
      detail: error.response?.data?.message || t('students.schedule_error'),
      life: 3000
    });
  } finally {
    scheduleLoading.value = false;
  }
};

// Get schedule for a specific day key (English, lowercase) and hour
const getScheduleForSlot = (dayKey: string, hour: number): Schedule | null => {
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

// Show student details with schedule
const showStudentDetails = async (studentData: Student) => {
  try {
    loadingDetails.value = true;
    studentDetailsDialog.value = true;
    studentHistory.value = [];

    // Fetch full student details and history in parallel
    const [studentDetails, history] = await Promise.all([
      StudentService.getStudent(studentData.id),
      StudentService.getStudentHistory(studentData.id)
    ]);
    selectedStudentDetails.value = studentDetails;
    studentHistory.value = history;

    // Fetch schedule if student has a class
    if (studentDetails.class_id) {
      const scheduleData = await ScheduleService.getClassSchedule(studentDetails.class_id, studentDetails.class?.academic_year);

      // Organize schedule data
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
    } else {
      studentSchedules.value = {};
    }
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('students.load_details_error'),
      life: 3000
    });
    studentDetailsDialog.value = false;
  } finally {
    loadingDetails.value = false;
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
          :label="t('students.new_student')"
          icon="pi pi-plus"
          severity="secondary"
          class="mr-2"
          @click="openNew"
        />
        <Button
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="secondary"
          :disabled="!selectedStudents || !selectedStudents.length"
          @click="confirmDeleteSelected"
        />
      </template>

      <template #end>
        <Button
          :label="t('common.export')"
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
      @row-click="showStudentDetails($event.data)"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">{{ t('students.title') }}</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="filters['global'].value"
              :placeholder="t('students.search_placeholder')"
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">{{ t('students.no_students') }}</p>
        </div>
      </template>

      <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>

      <Column field="full_name" :header="t('common.full_name')" sortable style="min-width: 14rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            <span class="font-semibold">{{ data.full_name }}</span>
          </div>
        </template>
      </Column>

      <Column field="code" :header="t('students.student_code')" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span class="text-muted-color">{{ data.code || t('common.na') }}</span>
        </template>
      </Column>


      <Column field="class_name" :header="t('common.class')" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span v-if="data.class">{{ data.class.name }}</span>
          <span v-else class="text-muted-color">{{ t('common.na') }}</span>
        </template>
      </Column>

      <Column field="parent_name" :header="t('common.parents')" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <span v-if="data.parent">{{ data.parent.first_name }} {{ data.parent.last_name }}</span>
          <span v-else class="text-muted-color">{{ t('common.na') }}</span>
        </template>
      </Column>



      <Column field="is_active" :header="t('common.status')" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Tag
            :value="getStatusLabel(data.is_active)"
            :severity="getStatusSeverity(data.is_active)"
          />
        </template>
      </Column>

      <Column :header="t('common.actions')" :exportable="false" style="min-width: 13rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-calendar"
            outlined
            rounded
            severity="info"
            class="mr-2"
            @click="viewSchedule(data)"
            v-tooltip.top="t('students.view_schedule')"
            :disabled="!data.class_id"
          />
          <Button
            icon="pi pi-pencil"
            outlined
            rounded
            class="mr-2"
            @click="editStudent(data)"
            v-tooltip.top="t('common.edit')"
          />
          <Button
            icon="pi pi-trash"
            outlined
            rounded
            severity="danger"
            @click="confirmDeleteStudent(data)"
            v-tooltip.top="t('common.delete')"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Add/Edit Student Dialog -->
    <Dialog
      v-model:visible="studentDialog"
      :style="{ width: '550px' }"
      :header="t('students.student_details')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <!-- Name Fields -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="first_name" class="block font-semibold mb-2">
              {{ t('common.first_name') }} <span class="text-red-500">*</span>
            </label>
            <InputText
              id="first_name"
              v-model.trim="student.first_name"
              required
              autofocus
              :invalid="submitted && !student.first_name"
              :placeholder="t('common.first_name')"
            />
            <small v-if="submitted && !student.first_name" class="text-red-500">
              {{ t('validation.first_name_required') }}
            </small>
          </div>

          <div>
            <label for="last_name" class="block font-semibold mb-2">
              {{ t('common.last_name') }} <span class="text-red-500">*</span>
            </label>
            <InputText
              id="last_name"
              v-model.trim="student.last_name"
              required
              :invalid="submitted && !student.last_name"
              :placeholder="t('common.last_name')"
            />
            <small v-if="submitted && !student.last_name" class="text-red-500">
              {{ t('validation.last_name_required') }}
            </small>
          </div>
        </div>

        <!-- Student Code -->
        <div>
          <label for="code" class="block font-semibold mb-2">
            {{ t('students.student_code') }} <span class="text-red-500">*</span>
          </label>
          <InputText
            id="code"
            v-model.trim="student.code"
            required
            :invalid="submitted && !student.code"
            :placeholder="t('students.student_code_placeholder')"
          />
          <small v-if="submitted && !student.code" class="text-red-500">
            {{ t('students.student_code_required') }}
          </small>
        </div>

        <!-- Birth Date and Gender -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="birth_date" class="block font-semibold mb-2">
              {{ t('students.birth_date') }}
            </label>
            <DatePicker
              id="birth_date"
              v-model="student.birth_date"
              dateFormat="yy-mm-dd"
              showIcon
              :placeholder="t('students.birth_date')"
            />
          </div>

          <div>
            <label for="gender" class="block font-semibold mb-2">
              {{ t('common.gender') }}
            </label>
            <Select
              id="gender"
              v-model="student.gender"
              :options="genderOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="t('common.gender')"
            />
          </div>
        </div>

        <!-- Class and Parent -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="class" class="block font-semibold mb-2">
              {{ t('common.class') }}
            </label>
            <Select
              id="class"
              v-model="student.class_id"
              :options="availableClasses"
              optionLabel="name"
              optionValue="id"
              :placeholder="t('students.select_class')"
              filter
              showClear
            />
          </div>

          <div>
            <label for="parent" class="block font-semibold mb-2">
              {{ t('common.parents') }}
            </label>
            <Select
              id="parent"
              v-model="student.parent_id"
              :options="availableParents"
              optionLabel="first_name"
              optionValue="id"
              :placeholder="t('students.select_parent')"
              filter
              :filterFields="['first_name', 'last_name']"
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
                <span v-else>{{ t('students.select_parent') }}</span>
              </template>
            </Select>
          </div>
        </div>

        <!-- Enrollment Date -->
        <div>
          <label for="enrollment_date" class="block font-semibold mb-2">
            {{ t('common.enrollment_date') }}
          </label>
          <DatePicker
            id="enrollment_date"
            v-model="student.enrollment_date"
            dateFormat="yy-mm-dd"
            showIcon
            :placeholder="t('common.enrollment_date')"
          />
        </div>

        <!-- Medical Info -->
        <div>
          <label for="medical_info" class="block font-semibold mb-2">
            {{ t('common.medical_info') }}
          </label>
          <Textarea
            id="medical_info"
            v-model="student.medical_info"
            rows="3"
            :placeholder="t('common.medical_info')"
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
            {{ t('students.active_student') }}
          </label>
        </div>
      </div>

      <template #footer>
        <Button
          :label="t('common.cancel')"
          icon="pi pi-times"
          text
          @click="hideDialog"
        />
        <Button
          :label="t('common.save')"
          icon="pi pi-check"
          @click="saveStudent"
        />
      </template>
    </Dialog>

    <!-- Student Details Dialog -->
    <Dialog
      v-model:visible="studentDetailsDialog"
      :style="{ width: '900px', maxHeight: '90vh' }"
      :header="t('students.student_details')"
      :modal="true"
    >
      <div v-if="loadingDetails" class="flex justify-center items-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      </div>

      <div v-else-if="selectedStudentDetails" class="flex flex-col gap-6">
        <!-- Student Information Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            {{ t('common.personal_information') }}
          </h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">{{ t('common.full_name') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.first_name }} {{ selectedStudentDetails.last_name }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('students.student_code') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.code || t('common.na') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.date_of_birth') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.birth_date ? new Date(selectedStudentDetails.birth_date).toLocaleDateString() : t('common.na') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.gender') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.gender || t('common.na') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.class') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.class?.name || t('students.no_subject') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.parents') }}</label>
              <p class="font-semibold">
                {{ selectedStudentDetails.parent ? `${selectedStudentDetails.parent.first_name} ${selectedStudentDetails.parent.last_name}` : t('common.na') }}
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.enrollment_date') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.enrollment_date ? new Date(selectedStudentDetails.enrollment_date).toLocaleDateString() : t('common.na') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.status') }}</label>
              <p>
                <Tag
                  :value="getStatusLabel(selectedStudentDetails.is_active)"
                  :severity="getStatusSeverity(selectedStudentDetails.is_active)"
                />
              </p>
            </div>
            <div class="col-span-2">
              <label class="text-sm text-muted-color">{{ t('common.medical_info') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.medical_info || t('common.na') }}</p>
            </div>
          </div>

        </div>

        <!-- Student Parent Information Section -->

        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            {{ t('students.parent_information') }}
          </h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">{{ t('common.full_name') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.parent?.first_name }} {{ selectedStudentDetails.parent?.last_name }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.cin') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.parent?.cin || t('common.na') }}</p>
            </div>

            <div>
              <label class="text-sm text-muted-color">{{ t('common.email') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.parent?.email || t('common.na') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.phone') }}</label>
              <p class="font-semibold">{{ selectedStudentDetails.parent?.phone || t('common.na') }}</p>
            </div>


            <div>
              <label class="text-sm text-muted-color">{{ t('common.account_status') }}</label>
              <p>
                <Tag
                  :value="getStatusLabel(selectedStudentDetails.is_active)"
                  :severity="getStatusSeverity(selectedStudentDetails.is_active)"
                />
              </p>
            </div>

          </div>

        </div>

        <!-- Schedule Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-calendar text-primary"></i>
            {{ t('common.weekly_schedule') }}
          </h3>

          <div v-if="studentSchedules && Object.keys(studentSchedules).length > 0" class="overflow-x-auto">
            <table class="schedule-table w-full border-collapse">
              <thead>
                <tr>
                  <th class="schedule-header time-column">{{ t('common.time') }}</th>
                  <th
                    v-for="day in weekDays"
                    :key="day.key"
                    class="schedule-header"
                  >
                    {{ day.label }}
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
                    :key="day.key"
                    class="schedule-cell"
                  >
                    <template v-if="getScheduleForSlot(day.key, timeSlot.hour)">
                      <div class="schedule-content has-schedule">
                        <div class="font-semibold text-sm">
                          {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.subject?.name || getScheduleForSlot(day.key, timeSlot.hour)?.subject?.name }}
                        </div>
                        <div class="text-xs mt-1 text-muted-color">
                          {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.teacher?.first_name }}
                          {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.teacher?.last_name }}
                        </div>
                        <div v-if="getScheduleForSlot(day.key, timeSlot.hour)?.room" class="text-xs mt-1">
                          <i class="pi pi-map-marker"></i> {{ getScheduleForSlot(day.key, timeSlot.hour)?.room }}
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
            <p class="text-muted-color">{{ t('common.no_schedule') }}</p>
          </div>
        </div>

        <!-- Class History Section -->
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-history text-primary"></i>
            {{ t('students.class_history') }}
          </h3>

          <div v-if="studentHistory.length > 0">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-surface-200 dark:border-surface-700">
                  <th class="text-left py-2 pr-4 font-semibold text-muted-color">{{ t('students.academic_year') }}</th>
                  <th class="text-left py-2 pr-4 font-semibold text-muted-color">{{ t('common.class') }}</th>
                  <th class="text-left py-2 pr-4 font-semibold text-muted-color">{{ t('students.level') }}</th>
                  <th class="text-left py-2 pr-4 font-semibold text-muted-color">{{ t('students.enrolled') }}</th>
                  <th class="text-left py-2 font-semibold text-muted-color">{{ t('students.left') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="record in studentHistory"
                  :key="record.id"
                  class="border-b border-surface-100 dark:border-surface-800"
                >
                  <td class="py-2 pr-4 font-semibold">{{ record.academic_year }}</td>
                  <td class="py-2 pr-4">{{ record.school_class?.name || t('common.na') }}</td>
                  <td class="py-2 pr-4">{{ record.school_class?.level || t('common.na') }}</td>
                  <td class="py-2 pr-4">{{ new Date(record.enrolled_at).toLocaleDateString() }}</td>
                  <td class="py-2">
                    <Tag v-if="!record.left_at" :value="t('students.current')" severity="success" />
                    <span v-else>{{ new Date(record.left_at).toLocaleDateString() }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="text-center py-6">
            <i class="pi pi-inbox text-4xl text-muted-color mb-2 block"></i>
            <p class="text-muted-color">{{ t('students.no_history') }}</p>
          </div>
        </div>

      </div>

      <template #footer>
        <Button
          :label="t('common.close')"
          icon="pi pi-times"
          @click="studentDetailsDialog = false"
        />
      </template>
    </Dialog>

    <!-- Delete Student Dialog -->
    <Dialog
      v-model:visible="deleteStudentDialog"
      :style="{ width: '450px' }"
      :header="t('students.confirm_delete')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span v-if="student">
          {{ t('common.are_you_sure_delete') }} <strong>{{ student.first_name }} {{ student.last_name }}</strong>?
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteStudentDialog = false"
        />
        <Button
          :label="t('common.yes')"
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
      :header="t('students.confirm_delete')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span v-if="selectedStudents">
          {{ t('students.confirm_delete_selected') }}
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteStudentsDialog = false"
        />
        <Button
          :label="t('common.yes')"
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
        <p class="text-muted-color">{{ t('students.loading_schedule') }}</p>
      </div>

      <div v-else>
        <div v-if="selectedStudentForSchedule?.class" class="mb-4 p-3 bg-surface-50 dark:bg-surface-800 rounded-border">
          <div class="flex items-center gap-2">
            <i class="pi pi-building text-primary"></i>
            <span class="font-semibold">{{ t('common.class') }}:</span>
            <span>{{ selectedStudentForSchedule.class.name }}</span>
          </div>
        </div>

        <div class="schedule-container" style="overflow-x: auto;">
          <table class="schedule-table">
            <thead>
              <tr>
                <th class="schedule-header time-column">{{ t('common.time') }}</th>
                <th
                  v-for="day in weekDays"
                  :key="day.key"
                  class="schedule-header"
                >
                  {{ day.label }}
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
                  :key="day.key"
                  class="schedule-cell"
                >
                  <template v-if="getScheduleForSlot(day.key, timeSlot.hour)">
                    <div class="schedule-content has-schedule">
                      <div class="font-semibold text-sm">
                        {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.subject?.name }}
                      </div>
                      <div class="text-xs mt-1">
                        {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.teacher?.first_name }} {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.teacher?.last_name }}
                      </div>
                      <div v-if="getScheduleForSlot(day.key, timeSlot.hour)?.room" class="text-xs mt-1">
                        <i class="pi pi-map-marker"></i> {{ getScheduleForSlot(day.key, timeSlot.hour)?.room }}
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
          :label="t('common.close')"
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

:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: pointer;
}

:deep(.p-dialog-content) {
  padding-top: 0;
}
</style>
