<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import ClassesService, { type SchoolClass, type CreateTeacherDTO, type UpdateTeacherDTO } from '@/service/ClassesService';
import TeacherService, { type Teacher } from '@/service/TeacherService';
import SubjectService, { type Subject } from '@/service/SubjectService';
import StudentService, { type Student } from '@/service/StudentService';
import ScheduleService, { type Schedule, type CreateScheduleDTO } from '@/service/ScheduleService';

const { t } = useI18n();

const toast = useToast();
const dt = ref();
const classes = ref<SchoolClass[]>([]);
const classDialog = ref(false);
const deleteClassDialog = ref(false);
const deleteClassesDialog = ref(false);
const schoolClass = ref<Partial<SchoolClass>>({});
const selectedClasses = ref<SchoolClass[]>([]);
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});
const submitted = ref(false);
const loading = ref(false);
const viewDetailsDialog = ref(false);
const selectedClassDetails = ref<SchoolClass | null>(null);
const availableTeachers = ref<Teacher[]>([]);

// Teacher assignment dialog states
const assignTeacherDialog = ref(false);
const selectedSubjectForAssignment = ref<number | null>(null);
const availableSubjects = ref<Subject[]>([]);
const availableTeachersForSubject = ref<any[]>([]);
const selectedTeacherToAssign = ref<number | null>(null);
const assignmentLoading = ref(false);
const classAssignments = ref<any[]>([]);

// Student assignment dialog states
const assignStudentDialog = ref(false);
const availableStudents = ref<Student[]>([]);
const selectedStudentToAssign = ref<number | null>(null);
const studentAssignmentLoading = ref(false);

// Confirmation dialog states
const removeTeacherConfirmDialog = ref(false);
const teacherToRemove = ref<{ teacherId: number; subjectId: number } | null>(null);
const removeStudentConfirmDialog = ref(false);
const studentToRemove = ref<number | null>(null);

// Schedule dialog states
const scheduleDialog = ref(false);
const selectedClassForSchedule = ref<SchoolClass | null>(null);
const classSchedules = ref<{ [day: string]: Schedule[] }>({});
const scheduleLoading = ref(false);
const scheduleEditDialog = ref(false);
const selectedScheduleSlot = ref<{ day: string; hour: number } | null>(null);
const scheduleToEdit = ref<Partial<Schedule> | null>(null);
const scheduleSubmitted = ref(false);

// Days (English values kept for API/logic use — display via t() in template)
const weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const schoolHours = [
  { hour: 8, label: '8:00 - 9:00' },
  { hour: 9, label: '9:00 - 10:00' },
  { hour: 10, label: '10:00 - 11:00' },
  { hour: 11, label: '11:00 - 12:00' },
  { hour: 12, label: '12:00 - 13:00' },
  { hour: 13, label: '13:00 - 14:00' },
  { hour: 14, label: '14:00 - 15:00' },
  { hour: 15, label: '15:00 - 16:00' },
  { hour: 16, label: '16:00 - 17:00' }
];

// Computed properties
const currentYear = computed(() => new Date().getFullYear());
const academicYears = computed(() => {
  const years = [];
  for (let i = -2; i <= 2; i++) {
    const year = currentYear.value + i;
    years.push(`${year}-${year + 1}`);
  }
  return years;
});

// Grade levels — label is translated, value is numeric string sent to the API
const levels = computed(() => [
  { label: t('classes.grade_1'),  value: '1' },
  { label: t('classes.grade_2'),  value: '2' },
  { label: t('classes.grade_3'),  value: '3' },
  { label: t('classes.grade_4'),  value: '4' },
  { label: t('classes.grade_5'),  value: '5' },
  { label: t('classes.grade_6'),  value: '6' },
  { label: t('classes.grade_7'),  value: '7' },
  { label: t('classes.grade_8'),  value: '8' },
  { label: t('classes.grade_9'),  value: '9' },
  { label: t('classes.grade_10'), value: '10' },
  { label: t('classes.grade_11'), value: '11' },
  { label: t('classes.grade_12'), value: '12' },
]);

// Load classes on mount
onMounted(async () => {
  await loadClasses();
  await loadTeachers();
});

// Load all classes
const loadClasses = async () => {
  try {
    loading.value = true;
    classes.value = await ClassesService.getClasses();

    // Add computed properties for each class
    classes.value = classes.value.map(c => ({
      ...c,
      subjects_text: c.subjects && Array.isArray(c.subjects)
        ? c.subjects.map(s => s.name).join(', ')
        : t('classes.no_subjects'),
      teachers_text: c.teachers && Array.isArray(c.teachers)
        ? c.teachers.map(teacher => teacher.name).join(', ')
        : t('classes.no_teachers'),
      students_display: c.students_count || 0,
      teachers_display: c.teachers_count || 0,
    }));
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_failed'),
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

// Load available teachers for selection
const loadTeachers = async () => {
  try {
    availableTeachers.value = await TeacherService.getTeachers();
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_teachers_failed'),
      life: 3000
    });
  }
};

// Open new class dialog
const openNew = () => {
  schoolClass.value = {
    is_active: true,
    academic_year: `${currentYear.value}-${currentYear.value + 1}`
  };
  submitted.value = false;
  classDialog.value = true;
};

const hideDialog = () => {
  classDialog.value = false;
  submitted.value = false;
};

// Save class (create or update)
const saveClass = async () => {
  submitted.value = true;

  // Validate required fields
  if (!schoolClass.value.name?.trim() || !schoolClass.value.level?.trim()) {
    toast.add({
      severity: 'error',
      summary: t('classes.validation_error'),
      detail: t('classes.fill_required_fields'),
      life: 3000
    });
    return;
  }

  try {
    const classData: any = {
      name: schoolClass.value.name,
      level: schoolClass.value.level,
      academic_year: schoolClass.value.academic_year || null,
      capacity: schoolClass.value.capacity ? String(schoolClass.value.capacity) : null,
      main_teacher_id: schoolClass.value.main_teacher_id ? String(schoolClass.value.main_teacher_id) : null
    };

    if (schoolClass.value.id) {
      // Update existing class
      const updated = await ClassesService.updateClass(schoolClass.value.id, classData);
      await loadClasses(); // Reload to get fresh data
      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('classes.class_updated'),
        life: 3000
      });
    } else {
      // Create new class
      const created = await ClassesService.createClass(classData);
      await loadClasses(); // Reload to get fresh data
      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('classes.class_created'),
        life: 3000
      });
    }

    classDialog.value = false;
    schoolClass.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.save_failed'),
      life: 3000
    });
  }
};

// Edit class
const editClass = (classToEdit: SchoolClass) => {
  schoolClass.value = { ...classToEdit };
  classDialog.value = true;
};

// View class details
const viewClass = async (classToView: SchoolClass) => {
  try {
    loading.value = true;
    selectedClassDetails.value = await ClassesService.getClass(classToView.id);

    // Load class assignments (teacher-subject mappings)
    const assignmentsData = await ClassesService.getClassAssignments(classToView.id);
    classAssignments.value = assignmentsData.assignments || [];

    viewDetailsDialog.value = true;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_details_failed'),
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

// Hide details dialog
const hideDetailsDialog = () => {
  viewDetailsDialog.value = false;
  selectedClassDetails.value = null;
};

// Confirm delete class
const confirmDeleteClass = (classToDelete: SchoolClass) => {
  schoolClass.value = classToDelete;
  deleteClassDialog.value = true;
};

// Delete class
const deleteClass = async () => {
  try {
    await ClassesService.deleteClass(schoolClass.value.id!);
    classes.value = classes.value.filter(c => c.id !== schoolClass.value.id);
    deleteClassDialog.value = false;
    schoolClass.value = {};
    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('classes.class_deleted'),
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.delete_failed'),
      life: 3000
    });
  }
};

// Export to CSV
const exportCSV = () => {
  dt.value.exportCSV();
};

// Confirm delete selected classes
const confirmDeleteSelected = () => {
  deleteClassesDialog.value = true;
};

// Delete selected classes
const deleteSelectedClasses = async () => {
  try {
    const ids = selectedClasses.value.map(c => c.id);
    await ClassesService.bulkDeleteClasses(ids);
    classes.value = classes.value.filter(c => !selectedClasses.value.some(sc => sc.id === c.id));
    deleteClassesDialog.value = false;
    selectedClasses.value = [];
    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('classes.classes_deleted'),
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.delete_selected_failed'),
      life: 3000
    });
  }
};

// Get status badge
const getStatusSeverity = (isActive: boolean | null) => {
  return isActive ? 'success' : 'danger';
};

const getStatusLabel = (isActive: boolean | null) => {
  return isActive ? t('common.active') : t('common.inactive');
};

// Get capacity status
const getCapacityStatus = (studentsCount: number, capacity: number | null) => {
  if (!capacity) return 'secondary';
  const percentage = (studentsCount / capacity) * 100;
  if (percentage >= 90) return 'danger';
  if (percentage >= 70) return 'warn';
  return 'success';
};

// Load available subjects
const loadSubjects = async () => {
  try {
    availableSubjects.value = await SubjectService.getSubjects();
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_subjects_failed'),
      life: 3000
    });
  }
};

// Open assign teacher dialog
const openAssignTeacherDialog = async () => {
  await loadSubjects();
  selectedSubjectForAssignment.value = null;
  selectedTeacherToAssign.value = null;
  availableTeachersForSubject.value = [];
  assignTeacherDialog.value = true;
};

// Handle subject selection change
const onSubjectChange = async () => {
  if (!selectedSubjectForAssignment.value) {
    availableTeachersForSubject.value = [];
    return;
  }

  try {
    assignmentLoading.value = true;
    availableTeachersForSubject.value = await SubjectService.getTeachersBySubject(selectedSubjectForAssignment.value);
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_teachers_for_subject_failed'),
      life: 3000
    });
  } finally {
    assignmentLoading.value = false;
  }
};

// Assign teacher to class
const assignTeacherToClass = async () => {
  if (!selectedSubjectForAssignment.value || !selectedTeacherToAssign.value || !selectedClassDetails.value) {
    toast.add({
      severity: 'error',
      summary: t('classes.validation_error'),
      detail: t('classes.select_subject_and_teacher'),
      life: 3000
    });
    return;
  }

  try {
    assignmentLoading.value = true;

    // Get the coefficient for this subject and class level
    const academicYear = selectedClassDetails.value.academic_year || `${currentYear.value}-${currentYear.value + 1}`;

    await SubjectService.assignSubjectToTeacher(
      selectedSubjectForAssignment.value,
      selectedTeacherToAssign.value,
      selectedClassDetails.value.id,
      academicYear,
      1 // Default coefficient, you can make this dynamic if needed
    );

    await loadClasses(); // Reload classes to update the main table

    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('classes.teacher_assigned'),
      life: 3000
    });

    // Reload class details
    await viewClass(selectedClassDetails.value);

    // Close dialog
    assignTeacherDialog.value = false;
    selectedSubjectForAssignment.value = null;
    selectedTeacherToAssign.value = null;
    availableTeachersForSubject.value = [];
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.assign_teacher_failed'),
      life: 3000
    });
  } finally {
    assignmentLoading.value = false;
  }
};

// Confirm remove teacher from class
const confirmRemoveTeacher = (teacherId: number, subjectId: number) => {
  teacherToRemove.value = { teacherId, subjectId };
  removeTeacherConfirmDialog.value = true;
};

// Remove teacher from class
const removeTeacherFromClass = async () => {
  if (!selectedClassDetails.value || !teacherToRemove.value) return;

  const { teacherId, subjectId } = teacherToRemove.value;

  try {
    const academicYear = selectedClassDetails.value.academic_year || `${currentYear.value}-${currentYear.value + 1}`;

    await SubjectService.unassignSubjectFromTeacher(
      subjectId,
      teacherId,
      selectedClassDetails.value.id,
      academicYear
    );

    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('classes.teacher_removed'),
      life: 3000
    });

    // Reload class details
    await viewClass(selectedClassDetails.value);

    removeTeacherConfirmDialog.value = false;
    teacherToRemove.value = null;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.remove_teacher_failed'),
      life: 3000
    });
  }
};

// Load available students (students without a class)
const loadAvailableStudents = async () => {
  try {
    studentAssignmentLoading.value = true;
    const allStudents = await StudentService.searchStudentsWithoutClass();
    // Filter students that are not in any class or are in the current class
    availableStudents.value = allStudents.filter(student =>
      !student.class_id || student.class_id === selectedClassDetails.value?.id
    );
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_students_failed'),
      life: 3000
    });
  } finally {
    studentAssignmentLoading.value = false;
  }
};

// Open assign student dialog
const openAssignStudentDialog = async () => {
  await loadAvailableStudents();
  selectedStudentToAssign.value = null;
  assignStudentDialog.value = true;
};

// Assign student to class
const assignStudentToClass = async () => {
  if (!selectedStudentToAssign.value || !selectedClassDetails.value) {
    toast.add({
      severity: 'error',
      summary: t('classes.validation_error'),
      detail: t('classes.select_a_student'),
      life: 3000
    });
    return;
  }

  try {
    studentAssignmentLoading.value = true;

    await StudentService.assignStudentToClass(
      selectedStudentToAssign.value,
      selectedClassDetails.value.id
    );

    // Reload classes to update counts
    await loadClasses();

    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('classes.student_assigned'),
      life: 3000
    });

    // Reload class details
    await viewClass(selectedClassDetails.value);

    // Close dialog
    assignStudentDialog.value = false;
    selectedStudentToAssign.value = null;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.assign_student_failed'),
      life: 3000
    });
  } finally {
    studentAssignmentLoading.value = false;
  }
};

// Confirm remove student from class
const confirmRemoveStudent = (studentId: number) => {
  studentToRemove.value = studentId;
  removeStudentConfirmDialog.value = true;
};

// Remove student from class
const removeStudentFromClass = async () => {
  if (!selectedClassDetails.value || !studentToRemove.value) return;

  try {
    await StudentService.removeStudentFromClass(studentToRemove.value);

    // Reload classes to update counts
    await loadClasses();

    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('classes.student_removed'),
      life: 3000
    });

    // Reload class details
    await viewClass(selectedClassDetails.value);

    removeStudentConfirmDialog.value = false;
    studentToRemove.value = null;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.remove_student_failed'),
      life: 3000
    });
  }
};

// Schedule Management Functions

// Open schedule dialog for a class
const viewSchedule = async (classToView: SchoolClass) => {
  try {
    scheduleLoading.value = true;
    selectedClassForSchedule.value = classToView;

    // Fetch class schedule with academic year filter
    const academic_year = classToView.academic_year || `${currentYear.value}-${currentYear.value + 1}`;
    const scheduleData = await ScheduleService.getClassSchedule(classToView.id, academic_year);

    // If scheduleData is an object with day keys, use it directly
    // If it's an array, we need to organize it by day
    if (Array.isArray(scheduleData)) {
      // Organize schedules by day
      const organizedSchedules: { [day: string]: Schedule[] } = {};
      scheduleData.forEach((schedule: Schedule) => {
        const dayKey = schedule.day.toLowerCase();
        if (!organizedSchedules[dayKey]) {
          organizedSchedules[dayKey] = [];
        }
        organizedSchedules[dayKey].push(schedule);
      });
      classSchedules.value = organizedSchedules;
    } else {
      // It's already organized by day
      classSchedules.value = scheduleData;
    }

    // Load class assignments (teacher-subject mappings) for the schedule edit dialog
    const assignmentsData = await ClassesService.getClassAssignments(classToView.id);
    classAssignments.value = assignmentsData.assignments || [];

    scheduleDialog.value = true;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.load_schedule_failed'),
      life: 3000
    });
  } finally {
    scheduleLoading.value = false;
  }
};

// Get schedule for a specific day and hour
const getScheduleForSlot = (day: string, hour: number): Schedule | null => {
  const dayKey = day.toLowerCase();
  const daySchedules = classSchedules.value[dayKey] || [];
  const startTime = `${hour.toString().padStart(2, '0')}:00:00`;
  const endTime = `${(hour + 1).toString().padStart(2, '0')}:00:00`;

  const found = daySchedules.find(schedule => {
    const scheduleStart = schedule.start_time;
    const scheduleEnd = schedule.end_time;
    const matches = scheduleStart <= startTime && scheduleEnd > startTime;
    return matches;
  }) || null;

  return found;
};

// Handle clicking on a schedule slot
const handleSlotClick = (day: string, hour: number) => {
  const existingSchedule = getScheduleForSlot(day, hour);

  if (existingSchedule) {
    // Edit existing schedule
    scheduleToEdit.value = { ...existingSchedule };
  } else {
    // Create new schedule
    const startTime = `${hour.toString().padStart(2, '0')}:00`;
    const endTime = `${(hour + 1).toString().padStart(2, '0')}:00`;

    scheduleToEdit.value = {
      day: day.toLowerCase(),
      start_time: startTime,
      end_time: endTime,
      room: '',
      notes: '',
      academic_year: selectedClassForSchedule.value?.academic_year || null
    };
  }

  selectedScheduleSlot.value = { day, hour };
  scheduleSubmitted.value = false;
  scheduleEditDialog.value = true;
};

// Save schedule (create or update)
const saveSchedule = async () => {
  scheduleSubmitted.value = true;

  if (!scheduleToEdit.value || !selectedClassForSchedule.value) return;

  // Validate that a teacher-subject assignment is selected
  if (!scheduleToEdit.value.class_subject_teacher_id) {
    toast.add({
      severity: 'error',
      summary: t('classes.validation_error'),
      detail: t('classes.select_subject_and_teacher'),
      life: 3000
    });
    return;
  }

  try {
    scheduleLoading.value = true;

    const scheduleData: CreateScheduleDTO = {
      class_subject_teacher_id: scheduleToEdit.value.class_subject_teacher_id,
      day: scheduleToEdit.value.day!,
      start_time: scheduleToEdit.value.start_time!,
      end_time: scheduleToEdit.value.end_time!,
      room: scheduleToEdit.value.room || null,
      notes: scheduleToEdit.value.notes || null,
      academic_year: scheduleToEdit.value.academic_year || null
    };

    if (scheduleToEdit.value.id) {
      // Update existing schedule
      await ScheduleService.updateSchedule(scheduleToEdit.value.id, scheduleData);
      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('classes.schedule_updated'),
        life: 3000
      });
    } else {
      // Create new schedule
      await ScheduleService.createSchedule(scheduleData);
      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('classes.schedule_created'),
        life: 3000
      });
    }

    // Reload schedule
    await viewSchedule(selectedClassForSchedule.value);

    scheduleEditDialog.value = false;
    scheduleToEdit.value = null;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.save_schedule_failed'),
      life: 3000
    });
  } finally {
    scheduleLoading.value = false;
  }
};

// Delete schedule
const deleteSchedule = async () => {
  if (!scheduleToEdit.value?.id) return;

  try {
    scheduleLoading.value = true;
    await ScheduleService.deleteSchedule(scheduleToEdit.value.id);

    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('classes.schedule_deleted'),
      life: 3000
    });

    // Reload schedule
    if (selectedClassForSchedule.value) {
      await viewSchedule(selectedClassForSchedule.value);
    }

    scheduleEditDialog.value = false;
    scheduleToEdit.value = null;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('classes.delete_schedule_failed'),
      life: 3000
    });
  } finally {
    scheduleLoading.value = false;
  }
};

// Close schedule dialogs
const hideScheduleDialog = () => {
  scheduleDialog.value = false;
  selectedClassForSchedule.value = null;
  classSchedules.value = {};
};

const hideScheduleEditDialog = () => {
  scheduleEditDialog.value = false;
  scheduleToEdit.value = null;
  selectedScheduleSlot.value = null;
  scheduleSubmitted.value = false;
};
</script>

<template>
  <div class="card">
    <Toast />

    <!-- Toolbar -->
    <Toolbar class="mb-6">
      <template #start>
        <Button
          :label="t('classes.new_class')"
          icon="pi pi-plus"
          severity="secondary"
          class="mr-2"
          @click="openNew"
        />
        <Button
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="secondary"
          :disabled="!selectedClasses || !selectedClasses.length"
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
      v-model:selection="selectedClasses"
      :value="classes"
      dataKey="id"
      :paginator="true"
      :rows="10"
      :filters="filters"
      :loading="loading"
      exportFilename="classes"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :rowsPerPageOptions="[5, 10, 25, 50]"
      :currentPageReportTemplate="t('classes.page_report')"
      class="p-datatable-sm"
      @row-click="viewClass($event.data)"

    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">{{ t('classes.manage_classes') }}</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="filters['global'].value"
              :placeholder="t('common.search')"
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-building text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">{{ t('classes.no_classes_found') }}</p>
        </div>
      </template>

      <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>

      <Column field="name" :header="t('classes.class_name')" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <i class="pi pi-building text-primary"></i>
            <span class="font-semibold">{{ data.name }}</span>
          </div>
        </template>
      </Column>

      <Column field="level" :header="t('classes.level')" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <Tag :value="data.level" severity="info" />
        </template>
      </Column>

      <Column field="academic_year" :header="t('classes.academic_year')" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span>{{ data.academic_year || t('common.na') }}</span>
        </template>
      </Column>

      <Column field="students_display" :header="t('common.students')" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <Badge
              :value="data.students_display"
              :severity="getCapacityStatus(data.students_display, data.capacity)"
            />
            <span v-if="data.capacity" class="text-sm text-muted-color">/ {{ data.capacity }}</span>
          </div>
        </template>
      </Column>

      <Column field="teachers_display" :header="t('common.teachers')" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Badge
            :value="data.teachers_display"
            :severity="data.teachers_display > 0 ? 'info' : 'secondary'"
          />
        </template>
      </Column>

      <Column field="subjects_text" :header="t('common.subjects')" style="min-width: 15rem">
        <template #body="{ data }">
          <span class="text-sm">{{ data.subjects_text }}</span>
        </template>
      </Column>

      <Column field="is_active" :header="t('common.status')" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <Tag
            :value="getStatusLabel(data.is_active)"
            :severity="getStatusSeverity(data.is_active)"
          />
        </template>
      </Column>



      <Column :exportable="false" style="min-width: 12rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-calendar"
            outlined
            rounded
            severity="success"
            class="mr-2"
            @click="viewSchedule(data)"
            v-tooltip.top="t('classes.view_schedule')"
          />
          <Button
            icon="pi pi-eye"
            outlined
            rounded
            severity="info"
            class="mr-2"
            @click="viewClass(data)"
            v-tooltip.top="t('classes.view_details')"
          />
          <Button
            icon="pi pi-pencil"
            outlined
            rounded
            class="mr-2"
            @click="editClass(data)"
            v-tooltip.top="t('classes.edit')"
          />
          <Button
            icon="pi pi-trash"
            outlined
            rounded
            severity="danger"
            @click="confirmDeleteClass(data)"
            v-tooltip.top="t('common.delete')"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Add/Edit Class Dialog -->
    <Dialog
      v-model:visible="classDialog"
      :style="{ width: '550px' }"
      :header="t('classes.class_details')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <!-- Class Name -->
        <div>
          <label for="name" class="block font-semibold mb-2">
            {{ t('classes.class_name') }} <span class="text-red-500">*</span>
          </label>
          <InputText
            id="name"
            v-model="schoolClass.name"
            required
            autofocus
            :invalid="submitted && !schoolClass.name"
            :placeholder="t('classes.class_name_placeholder')"
          />
          <small v-if="submitted && !schoolClass.name" class="text-red-500">
            {{ t('classes.class_name_required') }}
          </small>
        </div>

        

        <!-- Academic Year -->
        <div>
          <label for="academic_year" class="block font-semibold mb-2">
            {{ t('classes.academic_year') }}
          </label>
          <Select
            id="academic_year"
            v-model="schoolClass.academic_year"
            :options="academicYears"
            :placeholder="t('classes.select_academic_year')"
          />
        </div>

        <!-- Capacity -->
        <div>
          <label for="capacity" class="block font-semibold mb-2">
            {{ t('classes.capacity') }}
          </label>
          <InputNumber
            id="capacity"
            v-model="schoolClass.capacity"
            :placeholder="t('classes.capacity_placeholder')"
            :min="1"
            :max="100"
            showButtons
          />
          <small class="text-muted-color">
            {{ t('classes.capacity_hint') }}
          </small>
        </div>

        <!-- Main Teacher -->
        <div>
          <label for="main_teacher" class="block font-semibold mb-2">
            {{ t('classes.main_teacher') }}
          </label>
          <Select
            id="main_teacher"
            v-model="schoolClass.main_teacher_id"
            :options="availableTeachers"
            optionLabel="first_name"
            optionValue="id"
            :placeholder="t('classes.select_main_teacher')"
            filter
          >
            <template #option="{ option }">
              <div>{{ option.first_name }} {{ option.last_name }}</div>
            </template>
            <template #value="{ value }">
              <div v-if="value">
                {{ availableTeachers.find(t => t.id === value)?.first_name }}
                {{ availableTeachers.find(t => t.id === value)?.last_name }}
              </div>
              <span v-else>{{ t('classes.select_main_teacher') }}</span>
            </template>
          </Select>
        </div>

        <!-- Status -->
        <div v-if="schoolClass.id" class="flex items-center gap-2">
          <Checkbox
            id="is_active"
            v-model="schoolClass.is_active"
            :binary="true"
          />
          <label for="is_active" class="font-semibold">{{ t('classes.active_class') }}</label>
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
          @click="saveClass"
        />
      </template>
    </Dialog>

    <!-- View Class Details Dialog -->
    <Dialog
      v-model:visible="viewDetailsDialog"
      :style="{ width: '700px' }"
      :header="t('classes.class_details')"
      :modal="true"
    >
      <div v-if="selectedClassDetails" class="flex flex-col gap-6">
        <!-- Class Info -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">{{ t('classes.class_name') }}</h6>
            <p class="text-lg font-semibold">{{ selectedClassDetails.name }}</p>
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">{{ t('classes.level') }}</h6>
            <Tag :value="selectedClassDetails.level" severity="info" />
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">{{ t('classes.academic_year') }}</h6>
            <p>{{ selectedClassDetails.academic_year || t('common.na') }}</p>
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">{{ t('common.status') }}</h6>
            <Tag
              :value="getStatusLabel(selectedClassDetails.is_active)"
              :severity="getStatusSeverity(selectedClassDetails.is_active)"
            />
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">{{ t('classes.capacity') }}</h6>
            <p>{{ selectedClassDetails.capacity || t('common.na') }}</p>
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">{{ t('classes.current_students') }}</h6>
            <Badge
              :value="selectedClassDetails.students_count || 0"
              :severity="getCapacityStatus(selectedClassDetails.students_count || 0, selectedClassDetails.capacity)"
            />
          </div>
        </div>

        <Divider />

        <!-- Subjects -->
        <div>
          <h6 class="text-sm font-semibold text-muted-color mb-3">
            <i class="pi pi-book mr-2"></i>{{ t('common.subjects') }}
          </h6>
          <div v-if="selectedClassDetails.subjects && selectedClassDetails.subjects.length > 0">
            <div class="grid grid-cols-2 gap-2">
              <Tag
                v-for="(subject, index) in selectedClassDetails.subjects"
                :key="index"
                :value="subject.name"
                severity="secondary"
              />
            </div>
          </div>
          <p v-else class="text-muted-color">{{ t('classes.no_subjects_assigned') }}</p>
        </div>

        <Divider />

        <!-- Teachers & Subjects Assignment Table -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-muted-color">
              <i class="pi pi-users mr-2"></i>{{ t('common.teachers') }}
            </h6>
            <Button
              icon="pi pi-plus"
              :label="t('classes.assign_teacher')"
              size="small"
              @click="openAssignTeacherDialog"
            />
          </div>

          <DataTable
            v-if="classAssignments && classAssignments.length > 0"
            :value="classAssignments"
            :rows="5"
            :paginator="classAssignments.length > 5"
            class="p-datatable-sm"
          >
            <Column field="subject.name" :header="t('classes.subject')" sortable>
              <template #body="{ data }">
                <Tag :value="data.subject?.name || t('common.na')" severity="secondary" />
              </template>
            </Column>
            <Column :header="t('classes.teacher')" sortable>
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i class="pi pi-user text-muted-color"></i>
                  <span>{{ data.teacher?.first_name || '' }} {{ data.teacher?.last_name || '' }}</span>
                </div>
              </template>
            </Column>
            <Column field="coefficient" :header="t('classes.coefficient')" sortable>
              <template #body="{ data }">
                <Badge :value="data.coefficient || 1" severity="info" />
              </template>
            </Column>
            <Column :header="t('common.actions')">
              <template #body="{ data }">
                <Button
                  icon="pi pi-trash"
                  severity="danger"
                  text
                  rounded
                  @click="confirmRemoveTeacher(data.teacher_id, data.subject_id)"
                  v-tooltip.top="t('classes.remove')"
                />
              </template>
            </Column>
          </DataTable>

          <p v-else class="text-muted-color">{{ t('classes.no_assignments_yet') }}</p>
        </div>

        <Divider />

        <!-- Students -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-muted-color">
              <i class="pi pi-user mr-2"></i>{{ t('common.students') }} ({{ selectedClassDetails.sudents?.length || 0 }})
            </h6>
          </div>
          <div v-if="selectedClassDetails.sudents && selectedClassDetails.sudents.length > 0" class="max-h-60 overflow-y-auto">
            <div class="grid grid-cols-1 gap-2">
              <div
                v-for="(student, index) in selectedClassDetails.sudents"
                :key="index"
                class="p-3 border border-surface rounded-lg flex items-center justify-between hover:bg-surface-hover transition-colors"
              >
                <div class="flex items-center gap-3 flex-1">
                  <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 font-semibold">
                    {{ student.first_name?.[0] }}{{ student.last_name?.[0] }}
                  </div>
                  <div class="flex-1">
                    <p class="font-semibold">{{ student.first_name }} {{ student.last_name }}</p>
                    <p class="text-sm text-muted-color">{{ t('classes.code') }}: {{ student.code }}</p>
                  </div>
                  <Badge
                    :value="student.birth_date ? new Date(student.birth_date).toLocaleDateString() : t('common.na')"
                    severity="secondary"
                  />
                </div>
                <Button
                  icon="pi pi-times"
                  severity="danger"
                  text
                  rounded
                  size="small"
                  @click="confirmRemoveStudent(student.id)"
                  v-tooltip.top="t('classes.remove_from_class')"
                />
              </div>

              <!-- Add Student Card -->
              <div
                @click="openAssignStudentDialog"
                class="p-3 border-2 border-dashed border-surface rounded-lg flex items-center justify-center gap-2 hover:bg-surface-hover hover:border-primary transition-all cursor-pointer"
                style="min-height: 70px;"
              >
                <i class="pi pi-plus text-2xl text-muted-color"></i>
                <span class="font-semibold text-muted-color">{{ t('classes.add_student') }}</span>
              </div>
            </div>
          </div>
          <div v-else>
            <!-- Add Student Card (when no students) -->
            <div
              @click="openAssignStudentDialog"
              class="p-4 border-2 border-dashed border-surface rounded-lg flex flex-col items-center justify-center gap-3 hover:bg-surface-hover hover:border-primary transition-all cursor-pointer"
              style="min-height: 120px;"
            >
              <i class="pi pi-user-plus text-4xl text-muted-color"></i>
              <div class="text-center">
                <p class="font-semibold text-muted-color">{{ t('classes.no_students_enrolled') }}</p>
                <p class="text-sm text-muted-color">{{ t('classes.click_to_add_students') }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="t('common.close')"
          icon="pi pi-times"
          @click="hideDetailsDialog"
        />
      </template>
    </Dialog>

    <!-- Delete Class Dialog -->
    <Dialog
      v-model:visible="deleteClassDialog"
      :style="{ width: '450px' }"
      :header="t('common.confirm_deletion')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500"></i>
        <span v-if="schoolClass">
          {{ t('classes.confirm_delete_class', { name: schoolClass.name }) }}
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteClassDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="deleteClass"
        />
      </template>
    </Dialog>

    <!-- Delete Multiple Classes Dialog -->
    <Dialog
      v-model:visible="deleteClassesDialog"
      :style="{ width: '450px' }"
      :header="t('common.confirm_deletion')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500"></i>
        <span v-if="selectedClasses">
          {{ t('classes.confirm_delete_selected') }}
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteClassesDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="deleteSelectedClasses"
        />
      </template>
    </Dialog>

    <!-- Assign Teacher Dialog -->
    <Dialog
      v-model:visible="assignTeacherDialog"
      :style="{ width: '550px' }"
      :header="t('classes.assign_teacher_to_class')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <p class="text-muted-color">
          {{ t('classes.assign_teacher_hint') }}
        </p>

        <!-- Subject Selection -->
        <div>
          <label for="subject" class="block font-semibold mb-2">
            {{ t('classes.subject') }} <span class="text-red-500">*</span>
          </label>
          <Select
            id="subject"
            v-model="selectedSubjectForAssignment"
            :options="availableSubjects"
            optionLabel="name"
            optionValue="id"
            :placeholder="t('classes.select_subject')"
            filter
            @change="onSubjectChange"
          />
          <small class="text-muted-color">
            {{ t('classes.subject_hint') }}
          </small>
        </div>

        <!-- Teacher Selection (shows only after subject is selected) -->
        <div v-if="selectedSubjectForAssignment">
          <label for="teacher" class="block font-semibold mb-2">
            {{ t('classes.teacher') }} <span class="text-red-500">*</span>
          </label>

          <div v-if="assignmentLoading" class="flex items-center gap-2 p-3">
            <i class="pi pi-spin pi-spinner"></i>
            <span>{{ t('classes.loading_teachers') }}</span>
          </div>

          <Select
            v-else-if="availableTeachersForSubject.length > 0"
            id="teacher"
            v-model="selectedTeacherToAssign"
            :options="availableTeachersForSubject"
            optionLabel="first_name"
            optionValue="id"
            :placeholder="t('classes.select_teacher')"
            filter
          >
            <template #option="{ option }">
              <div class="flex flex-col">
                <span class="font-semibold">{{ option.first_name }} {{ option.last_name }}</span>
              </div>
            </template>
            <template #value="{ value }">
              <div v-if="value">
                {{ availableTeachersForSubject.find(t => t.id === value)?.first_name }}
                {{ availableTeachersForSubject.find(t => t.id === value)?.last_name }}
              </div>
              <span v-else>{{ t('classes.select_teacher') }}</span>
            </template>
          </Select>

          <div v-else class="p-3 border border-surface rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
            <div class="flex items-center gap-2 text-yellow-700 dark:text-yellow-300">
              <i class="pi pi-info-circle"></i>
              <span>{{ t('classes.no_teachers_for_subject') }}</span>
            </div>
          </div>
        </div>

        <!-- Info Message -->
        <div v-if="!selectedSubjectForAssignment" class="p-3 border border-surface rounded-lg bg-blue-50 dark:bg-blue-900/20">
          <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <i class="pi pi-info-circle"></i>
            <span>{{ t('classes.select_subject_first') }}</span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="t('common.cancel')"
          icon="pi pi-times"
          text
          @click="assignTeacherDialog = false"
          :disabled="assignmentLoading"
        />
        <Button
          :label="t('classes.assign')"
          icon="pi pi-check"
          @click="assignTeacherToClass"
          :disabled="!selectedSubjectForAssignment || !selectedTeacherToAssign || assignmentLoading"
          :loading="assignmentLoading"
        />
      </template>
    </Dialog>

    <!-- Assign Student Dialog -->
    <Dialog
      v-model:visible="assignStudentDialog"
      :style="{ width: '550px' }"
      :header="t('classes.assign_student_to_class')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <p class="text-muted-color">
          {{ t('classes.assign_student_hint', { name: selectedClassDetails?.name }) }}
        </p>

        <!-- Student Selection -->
        <div>
          <label for="student" class="block font-semibold mb-2">
            {{ t('classes.student') }} <span class="text-red-500">*</span>
          </label>

          <div v-if="studentAssignmentLoading" class="flex items-center gap-2 p-3">
            <i class="pi pi-spin pi-spinner"></i>
            <span>{{ t('classes.loading_students') }}</span>
          </div>

          <Select
            v-else-if="availableStudents.length > 0"
            id="student"
            v-model="selectedStudentToAssign"
            :options="availableStudents"
            optionLabel="first_name"
            optionValue="id"
            :placeholder="t('classes.select_student')"
            filter
          >
            <template #option="{ option }">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 font-semibold text-sm">
                  {{ option.first_name?.[0] }}{{ option.last_name?.[0] }}
                </div>
                <div class="flex flex-col">
                  <span class="font-semibold">{{ option.first_name }} {{ option.last_name }}</span>
                  <span class="text-sm text-muted-color">{{ t('classes.code') }}: {{ option.code }}</span>
                </div>
              </div>
            </template>
            <template #value="{ value }">
              <div v-if="value" class="flex items-center gap-2">
                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 font-semibold text-xs">
                  {{ availableStudents.find(s => s.id === value)?.first_name?.[0] }}{{ availableStudents.find(s => s.id === value)?.last_name?.[0] }}
                </div>
                <span>
                  {{ availableStudents.find(s => s.id === value)?.first_name }}
                  {{ availableStudents.find(s => s.id === value)?.last_name }}
                </span>
              </div>
              <span v-else>{{ t('classes.select_student') }}</span>
            </template>
          </Select>

          <div v-else class="p-3 border border-surface rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
            <div class="flex items-center gap-2 text-yellow-700 dark:text-yellow-300">
              <i class="pi pi-info-circle"></i>
              <span>{{ t('classes.no_available_students') }}</span>
            </div>
          </div>

          <small class="text-muted-color mt-2 block">
            {{ t('classes.students_without_class_hint') }}
          </small>
        </div>

        <!-- Info about capacity -->
        <div v-if="selectedClassDetails?.capacity" class="p-3 border border-surface rounded-lg bg-blue-50 dark:bg-blue-900/20">
          <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <i class="pi pi-info-circle"></i>
            <span>
              {{ t('classes.class_capacity_info', { current: selectedClassDetails.students_count || 0, max: selectedClassDetails.capacity }) }}
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="t('common.cancel')"
          icon="pi pi-times"
          text
          @click="assignStudentDialog = false"
          :disabled="studentAssignmentLoading"
        />
        <Button
          :label="t('classes.assign')"
          icon="pi pi-check"
          @click="assignStudentToClass"
          :disabled="!selectedStudentToAssign || studentAssignmentLoading"
          :loading="studentAssignmentLoading"
        />
      </template>
    </Dialog>

    <!-- Remove Teacher Confirmation Dialog -->
    <Dialog
      v-model:visible="removeTeacherConfirmDialog"
      :style="{ width: '450px' }"
      :header="t('classes.confirm_removal')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span>
          {{ t('classes.confirm_remove_teacher') }}
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="removeTeacherConfirmDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="removeTeacherFromClass"
        />
      </template>
    </Dialog>

    <!-- Remove Student Confirmation Dialog -->
    <Dialog
      v-model:visible="removeStudentConfirmDialog"
      :style="{ width: '450px' }"
      :header="t('classes.confirm_removal')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span>
          {{ t('classes.confirm_remove_student') }}
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="removeStudentConfirmDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="removeStudentFromClass"
        />
      </template>
    </Dialog>

    <!-- Schedule View Dialog -->
    <Dialog
      v-model:visible="scheduleDialog"
      :style="{ width: '95vw', maxWidth: '1200px' }"
      :header="t('common.weekly_schedule') + ' - ' + (selectedClassForSchedule?.name || '')"
      :modal="true"
      @hide="hideScheduleDialog"
    >
      <div v-if="scheduleLoading" class="flex justify-center items-center p-8">
        <i class="pi pi-spin pi-spinner text-4xl"></i>
      </div>

      <div v-else class="schedule-container">
        <div class="mb-4 p-3 border border-surface rounded-lg bg-blue-50 dark:bg-blue-900/20">
          <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <i class="pi pi-info-circle"></i>
            <span>{{ t('classes.schedule_click_hint') }}</span>
          </div>
        </div>

        <div class="overflow-auto">
          <table class="schedule-table w-full border-collapse">
            <thead>
              <tr>
                <th class="schedule-header time-column">{{ t('common.time') }}</th>
                <th
                  v-for="day in weekDays"
                  :key="day"
                  class="schedule-header"
                >
                  {{ t('common.' + day.toLowerCase()) }}
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
                  @click="handleSlotClick(day, timeSlot.hour)"
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
                    <i class="pi pi-plus text-muted-color"></i>
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

    <!-- Schedule Edit Dialog -->
    <Dialog
      v-model:visible="scheduleEditDialog"
      :style="{ width: '550px' }"
      :header="scheduleToEdit?.id ? t('classes.edit_schedule') : t('classes.add_schedule')"
      :modal="true"
      class="p-fluid"
      @hide="hideScheduleEditDialog"
    >
      <div v-if="scheduleToEdit" class="flex flex-col gap-6">
        <!-- Day and Time Info -->
        <div class="p-3 border border-surface rounded-lg bg-surface-100 dark:bg-surface-800">
          <div class="flex items-center gap-2 mb-2">
            <i class="pi pi-calendar text-primary"></i>
            <span class="font-semibold">{{ scheduleToEdit.day ? scheduleToEdit.day.charAt(0).toUpperCase() + scheduleToEdit.day.slice(1) : '' }}</span>
          </div>
          <div class="flex items-center gap-2">
            <i class="pi pi-clock text-primary"></i>
            <span>{{ scheduleToEdit.start_time }} - {{ scheduleToEdit.end_time }}</span>
          </div>
        </div>

        <!-- Subject and Teacher Selection -->
        <div>
          <label for="assignment" class="block font-semibold mb-2">
            {{ t('classes.subject_and_teacher') }} <span class="text-red-500">*</span>
          </label>
          <Select
            id="assignment"
            v-model="scheduleToEdit.class_subject_teacher_id"
            :options="classAssignments"
            optionValue="id"
            :placeholder="t('classes.select_subject_and_teacher')"
            :invalid="scheduleSubmitted && !scheduleToEdit.class_subject_teacher_id"
          >
            <template #option="{ option }">
              <div class="flex flex-col">
                <span class="font-semibold">{{ option.subject?.name || t('common.na') }}</span>
                <span class="text-sm text-muted-color">{{ option.teacher?.first_name }} {{ option.teacher?.last_name }}</span>
              </div>
            </template>
            <template #value="{ value }">
              <div v-if="value">
                <span class="font-semibold">{{ classAssignments.find(a => a.id === value)?.subject?.name }}</span>
                <span class="text-sm text-muted-color ml-2">- {{ classAssignments.find(a => a.id === value)?.teacher?.first_name }} {{ classAssignments.find(a => a.id === value)?.teacher?.last_name }}</span>
              </div>
              <span v-else>{{ t('classes.select_subject_and_teacher') }}</span>
            </template>
          </Select>
          <small v-if="scheduleSubmitted && !scheduleToEdit.class_subject_teacher_id" class="text-red-500">
            {{ t('classes.subject_teacher_required') }}
          </small>
        </div>

        <!-- Room -->
        <div>
          <label for="room" class="block font-semibold mb-2">
            {{ t('classes.room') }}
          </label>
          <InputText
            id="room"
            v-model="scheduleToEdit.room"
            :placeholder="t('classes.room_placeholder')"
          />
        </div>

        <!-- Notes -->
        <div>
          <label for="notes" class="block font-semibold mb-2">
            {{ t('classes.notes') }}
          </label>
          <Textarea
            id="notes"
            v-model="scheduleToEdit.notes"
            rows="3"
            :placeholder="t('classes.notes_placeholder')"
          />
        </div>
      </div>

      <template #footer>
        <Button
          v-if="scheduleToEdit?.id"
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="danger"
          text
          @click="deleteSchedule"
        />
        <Button
          :label="t('common.cancel')"
          icon="pi pi-times"
          text
          @click="hideScheduleEditDialog"
        />
        <Button
          :label="t('common.save')"
          icon="pi pi-check"
          :loading="scheduleLoading"
          @click="saveSchedule"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.card {
  background: var(--surface-card);
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Schedule Table Styles */
.schedule-table {
  min-width: 100%;
  background: var(--surface-card);
}

.schedule-header {
  background: var(--primary-color);
  color: white;
  padding: 0.75rem;
  text-align: center;
  font-weight: 600;
  border: 1px solid var(--surface-border);
  position: sticky;
  top: 0;
  z-index: 10;
}

.time-column {
  background: var(--surface-100);
  min-width: 100px;
  text-align: center;
  padding: 0.75rem;
  border: 1px solid var(--surface-border);
  position: sticky;
  left: 0;
  z-index: 5;
}

.schedule-cell {
  border: 1px solid var(--surface-border);
  padding: 0;
  min-width: 120px;
  height: 80px;
  cursor: pointer;
  transition: all 0.2s;
  vertical-align: top;
}

.schedule-cell:hover {
  background: var(--primary-50);
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

.empty-schedule:hover {
  opacity: 0.7;
}

.schedule-container {
  margin: 0 -1.5rem;
  padding: 0 1.5rem;
}

:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: pointer;
}

@media (max-width: 768px) {
  .schedule-table {
    font-size: 0.875rem;
  }

  .schedule-cell {
    min-width: 100px;
    height: 70px;
  }

  .schedule-content {
    padding: 0.25rem;
  }
}
</style>
