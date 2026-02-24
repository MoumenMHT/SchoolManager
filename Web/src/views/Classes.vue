<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import ClassesService, { type SchoolClass, type CreateTeacherDTO, type UpdateTeacherDTO } from '@/service/ClassesService';
import TeacherService, { type Teacher } from '@/service/TeacherService';
import SubjectService, { type Subject } from '@/service/SubjectService';
import StudentService, { type Student } from '@/service/StudentService';

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

const levels = [
  '1st Grade',
  '2nd Grade',
  '3rd Grade',
  '4th Grade',
  '5th Grade',
  '6th Grade',
  '7th Grade',
  '8th Grade',
  '9th Grade',
  '10th Grade',
  '11th Grade',
  '12th Grade'
];

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
        : 'No subjects',
      teachers_text: c.teachers && Array.isArray(c.teachers) 
        ? c.teachers.map(t => t.name).join(', ') 
        : 'No teachers',
      students_display: c.students_count || 0,
      teachers_display: c.teachers_count || 0,
    }));
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load classes',
      life: 3000
    });
    console.log('Error fetching classes:', error.response?.data?.message);
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
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load teachers',
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

// Hide dialog
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
      summary: 'Validation Error',
      detail: 'Please fill in all required fields',
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
        summary: 'Success',
        detail: 'Class updated successfully',
        life: 3000
      });
    } else {
      // Create new class
      const created = await ClassesService.createClass(classData);
      await loadClasses(); // Reload to get fresh data
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Class created successfully',
        life: 3000
      });
    }

    classDialog.value = false;
    schoolClass.value = {};
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to save class',
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
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load class details',
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
      summary: 'Success',
      detail: 'Class deleted successfully',
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to delete class',
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
      summary: 'Success',
      detail: 'Classes deleted successfully',
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to delete classes',
      life: 3000
    });
  }
};

// Get status badge
const getStatusSeverity = (isActive: boolean | null) => {
  return isActive ? 'success' : 'danger';
};

const getStatusLabel = (isActive: boolean | null) => {
  return isActive ? 'Active' : 'Inactive';
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
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load subjects',
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
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load teachers for subject',
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
      summary: 'Validation Error',
      detail: 'Please select both subject and teacher',
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
      summary: 'Success',
      detail: 'Teacher assigned successfully',
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
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to assign teacher',
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
      summary: 'Success',
      detail: 'Teacher removed successfully',
      life: 3000
    });

    // Reload class details
    await viewClass(selectedClassDetails.value);
    
    removeTeacherConfirmDialog.value = false;
    teacherToRemove.value = null;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to remove teacher',
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
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to load students',
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
      summary: 'Validation Error',
      detail: 'Please select a student',
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
      summary: 'Success',
      detail: 'Student assigned successfully',
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
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to assign student',
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
      summary: 'Success',
      detail: 'Student removed from class',
      life: 3000
    });

    // Reload class details
    await viewClass(selectedClassDetails.value);
    
    removeStudentConfirmDialog.value = false;
    studentToRemove.value = null;
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to remove student',
      life: 3000
    });
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
          label="New Class" 
          icon="pi pi-plus" 
          severity="secondary" 
          class="mr-2" 
          @click="openNew" 
        />
        <Button 
          label="Delete" 
          icon="pi pi-trash" 
          severity="secondary" 
          :disabled="!selectedClasses || !selectedClasses.length" 
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
      currentPageReportTemplate="Showing {first} to {last} of {totalRecords} classes"
      class="p-datatable-sm"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">Manage Classes</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText 
              v-model="filters['global'].value" 
              placeholder="Search classes..." 
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-building text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">No classes found.</p>
        </div>
      </template>

      <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>
      
      <Column field="name" header="Class Name" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <i class="pi pi-building text-primary"></i>
            <span class="font-semibold">{{ data.name }}</span>
          </div>
        </template>
      </Column>

      <Column field="level" header="Level" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <Tag :value="data.level" severity="info" />
        </template>
      </Column>

      <Column field="academic_year" header="Academic Year" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span>{{ data.academic_year || 'N/A' }}</span>
        </template>
      </Column>

      <Column field="students_display" header="Students" sortable style="min-width: 10rem">
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

      <Column field="teachers_display" header="Teachers" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Badge 
            :value="data.teachers_display" 
            :severity="data.teachers_display > 0 ? 'info' : 'secondary'" 
          />
        </template>
      </Column>

      <Column field="subjects_text" header="Subjects" style="min-width: 15rem">
        <template #body="{ data }">
          <span class="text-sm">{{ data.subjects_text }}</span>
        </template>
      </Column>

      <Column field="is_active" header="Status" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <Tag 
            :value="getStatusLabel(data.is_active)" 
            :severity="getStatusSeverity(data.is_active)" 
          />
        </template>
      </Column>

      <Column field="updated_at" header="Last Updated" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <span class="text-sm text-muted-color">
            {{ new Date(data.updated_at).toLocaleDateString() }}
          </span>
        </template>
      </Column>

      <Column :exportable="false" style="min-width: 12rem">
        <template #body="{ data }">
          <Button 
            icon="pi pi-eye" 
            outlined 
            rounded 
            severity="info"
            class="mr-2" 
            @click="viewClass(data)" 
            v-tooltip.top="'View Details'"
          />
          <Button 
            icon="pi pi-pencil" 
            outlined 
            rounded 
            class="mr-2" 
            @click="editClass(data)" 
            v-tooltip.top="'Edit'"
          />
          <Button 
            icon="pi pi-trash" 
            outlined 
            rounded 
            severity="danger" 
            @click="confirmDeleteClass(data)" 
            v-tooltip.top="'Delete'"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Add/Edit Class Dialog -->
    <Dialog 
      v-model:visible="classDialog" 
      :style="{ width: '550px' }" 
      header="Class Details" 
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <!-- Class Name -->
        <div>
          <label for="name" class="block font-semibold mb-2">
            Class Name <span class="text-red-500">*</span>
          </label>
          <InputText 
            id="name" 
            v-model="schoolClass.name" 
            required 
            autofocus 
            :invalid="submitted && !schoolClass.name" 
            placeholder="e.g., Class A, Section 1"
          />
          <small v-if="submitted && !schoolClass.name" class="text-red-500">
            Class name is required.
          </small>
        </div>

        <!-- Level -->
        <div>
          <label for="level" class="block font-semibold mb-2">
            Level <span class="text-red-500">*</span>
          </label>
          <Select 
            id="level" 
            v-model="schoolClass.level" 
            :options="levels" 
            placeholder="Select a level"
            :invalid="submitted && !schoolClass.level"
          />
          <small v-if="submitted && !schoolClass.level" class="text-red-500">
            Level is required.
          </small>
        </div>

        <!-- Academic Year -->
        <div>
          <label for="academic_year" class="block font-semibold mb-2">
            Academic Year
          </label>
          <Select 
            id="academic_year" 
            v-model="schoolClass.academic_year" 
            :options="academicYears" 
            placeholder="Select academic year"
          />
        </div>

        <!-- Capacity -->
        <div>
          <label for="capacity" class="block font-semibold mb-2">
            Capacity
          </label>
          <InputNumber 
            id="capacity" 
            v-model="schoolClass.capacity" 
            placeholder="Maximum number of students"
            :min="1"
            :max="100"
            showButtons
          />
          <small class="text-muted-color">
            Maximum number of students allowed in this class
          </small>
        </div>

        <!-- Main Teacher -->
        <div>
          <label for="main_teacher" class="block font-semibold mb-2">
            Main Teacher (Class Supervisor)
          </label>
          <Select 
            id="main_teacher" 
            v-model="schoolClass.main_teacher_id" 
            :options="availableTeachers" 
            optionLabel="first_name"
            optionValue="id"
            placeholder="Select main teacher"
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
              <span v-else>Select main teacher</span>
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
          <label for="is_active" class="font-semibold">Active Class</label>
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
          @click="saveClass" 
        />
      </template>
    </Dialog>

    <!-- View Class Details Dialog -->
    <Dialog 
      v-model:visible="viewDetailsDialog" 
      :style="{ width: '700px' }" 
      header="Class Details" 
      :modal="true"
    >
      <div v-if="selectedClassDetails" class="flex flex-col gap-6">
        <!-- Class Info -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">Class Name</h6>
            <p class="text-lg font-semibold">{{ selectedClassDetails.name }}</p>
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">Level</h6>
            <Tag :value="selectedClassDetails.level" severity="info" />
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">Academic Year</h6>
            <p>{{ selectedClassDetails.academic_year || 'N/A' }}</p>
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">Status</h6>
            <Tag 
              :value="getStatusLabel(selectedClassDetails.is_active)" 
              :severity="getStatusSeverity(selectedClassDetails.is_active)" 
            />
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">Capacity</h6>
            <p>{{ selectedClassDetails.capacity || 'Not set' }}</p>
          </div>
          <div>
            <h6 class="text-sm font-semibold text-muted-color mb-1">Current Students</h6>
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
            <i class="pi pi-book mr-2"></i>Subjects
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
          <p v-else class="text-muted-color">No subjects assigned</p>
        </div>

        <Divider />

        <!-- Teachers & Subjects Assignment Table -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-muted-color">
              <i class="pi pi-users mr-2"></i>Teachers
            </h6>
            <Button 
              icon="pi pi-plus" 
              label="Assign Teacher"
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
            <Column field="subject.name" header="Subject" sortable>
              <template #body="{ data }">
                <Tag :value="data.subject?.name || 'N/A'" severity="secondary" />
              </template>
            </Column>
            <Column header="Teacher" sortable>
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i class="pi pi-user text-muted-color"></i>
                  <span>{{ data.teacher?.first_name || '' }} {{ data.teacher?.last_name || '' }}</span>
                </div>
              </template>
            </Column>
            <Column field="coefficient" header="Coefficient" sortable>
              <template #body="{ data }">
                <Badge :value="data.coefficient || 1" severity="info" />
              </template>
            </Column>
            <Column header="Actions">
              <template #body="{ data }">
                <Button 
                  icon="pi pi-trash" 
                  severity="danger"
                  text
                  rounded
                  @click="confirmRemoveTeacher(data.teacher_id, data.subject_id)"
                  v-tooltip.top="'Remove'"
                />
              </template>
            </Column>
          </DataTable>
          
          <p v-else class="text-muted-color">No teacher-subject assignments yet. Click "Assign Teacher" to add one.</p>
        </div>

        <Divider />

        <!-- Students -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-muted-color">
              <i class="pi pi-user mr-2"></i>Students ({{ selectedClassDetails.sudents?.length || 0 }})
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
                    <p class="text-sm text-muted-color">Code: {{ student.code }}</p>
                  </div>
                  <Badge 
                    :value="student.birth_date ? new Date(student.birth_date).toLocaleDateString() : 'N/A'" 
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
                  v-tooltip.top="'Remove from class'"
                />
              </div>
              
              <!-- Add Student Card -->
              <div 
                @click="openAssignStudentDialog"
                class="p-3 border-2 border-dashed border-surface rounded-lg flex items-center justify-center gap-2 hover:bg-surface-hover hover:border-primary transition-all cursor-pointer"
                style="min-height: 70px;"
              >
                <i class="pi pi-plus text-2xl text-muted-color"></i>
                <span class="font-semibold text-muted-color">Add Student</span>
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
                <p class="font-semibold text-muted-color">No students enrolled</p>
                <p class="text-sm text-muted-color">Click to add students to this class</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button 
          label="Close" 
          icon="pi pi-times" 
          @click="hideDetailsDialog" 
        />
      </template>
    </Dialog>

    <!-- Delete Class Dialog -->
    <Dialog 
      v-model:visible="deleteClassDialog" 
      :style="{ width: '450px' }" 
      header="Confirm Deletion" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500"></i>
        <span v-if="schoolClass">
          Are you sure you want to delete <b>{{ schoolClass.name }}</b>?
        </span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="deleteClassDialog = false" 
        />
        <Button 
          label="Yes" 
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
      header="Confirm Deletion" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500"></i>
        <span v-if="selectedClasses">
          Are you sure you want to delete the selected classes?
        </span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="deleteClassesDialog = false" 
        />
        <Button 
          label="Yes" 
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
      header="Assign Teacher to Class" 
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <p class="text-muted-color">
          First, select the subject you want to assign, then choose from the available teachers who can teach that subject.
        </p>

        <!-- Subject Selection -->
        <div>
          <label for="subject" class="block font-semibold mb-2">
            Subject <span class="text-red-500">*</span>
          </label>
          <Select 
            id="subject" 
            v-model="selectedSubjectForAssignment" 
            :options="availableSubjects" 
            optionLabel="name"
            optionValue="id"
            placeholder="Select a subject"
            filter
            @change="onSubjectChange"
          />
          <small class="text-muted-color">
            Choose the subject that the teacher will teach for this class
          </small>
        </div>

        <!-- Teacher Selection (shows only after subject is selected) -->
        <div v-if="selectedSubjectForAssignment">
          <label for="teacher" class="block font-semibold mb-2">
            Teacher <span class="text-red-500">*</span>
          </label>
          
          <div v-if="assignmentLoading" class="flex items-center gap-2 p-3">
            <i class="pi pi-spin pi-spinner"></i>
            <span>Loading available teachers...</span>
          </div>
          
          <Select 
            v-else-if="availableTeachersForSubject.length > 0"
            id="teacher" 
            v-model="selectedTeacherToAssign" 
            :options="availableTeachersForSubject" 
            optionLabel="first_name"
            optionValue="id"
            placeholder="Select a teacher"
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
              <span v-else>Select a teacher</span>
            </template>
          </Select>
          
          <div v-else class="p-3 border border-surface rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
            <div class="flex items-center gap-2 text-yellow-700 dark:text-yellow-300">
              <i class="pi pi-info-circle"></i>
              <span>No teachers available for this subject. Teachers need to be qualified for this subject first.</span>
            </div>
          </div>
        </div>

        <!-- Info Message -->
        <div v-if="!selectedSubjectForAssignment" class="p-3 border border-surface rounded-lg bg-blue-50 dark:bg-blue-900/20">
          <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <i class="pi pi-info-circle"></i>
            <span>Please select a subject to see available teachers</span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          icon="pi pi-times" 
          text 
          @click="assignTeacherDialog = false" 
          :disabled="assignmentLoading"
        />
        <Button 
          label="Assign" 
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
      header="Assign Student to Class" 
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <p class="text-muted-color">
          Select a student to assign to <strong>{{ selectedClassDetails?.name }}</strong>
        </p>

        <!-- Student Selection -->
        <div>
          <label for="student" class="block font-semibold mb-2">
            Student <span class="text-red-500">*</span>
          </label>
          
          <div v-if="studentAssignmentLoading" class="flex items-center gap-2 p-3">
            <i class="pi pi-spin pi-spinner"></i>
            <span>Loading available students...</span>
          </div>
          
          <Select 
            v-else-if="availableStudents.length > 0"
            id="student" 
            v-model="selectedStudentToAssign" 
            :options="availableStudents" 
            optionLabel="first_name"
            optionValue="id"
            placeholder="Select a student"
            filter
          >
            <template #option="{ option }">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 font-semibold text-sm">
                  {{ option.first_name?.[0] }}{{ option.last_name?.[0] }}
                </div>
                <div class="flex flex-col">
                  <span class="font-semibold">{{ option.first_name }} {{ option.last_name }}</span>
                  <span class="text-sm text-muted-color">Code: {{ option.code }}</span>
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
              <span v-else>Select a student</span>
            </template>
          </Select>
          
          <div v-else class="p-3 border border-surface rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
            <div class="flex items-center gap-2 text-yellow-700 dark:text-yellow-300">
              <i class="pi pi-info-circle"></i>
              <span>No available students. All students are already assigned to classes.</span>
            </div>
          </div>
          
          <small class="text-muted-color mt-2 block">
            Only students without a class assignment are shown
          </small>
        </div>

        <!-- Info about capacity -->
        <div v-if="selectedClassDetails?.capacity" class="p-3 border border-surface rounded-lg bg-blue-50 dark:bg-blue-900/20">
          <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <i class="pi pi-info-circle"></i>
            <span>
              Class capacity: {{ selectedClassDetails.students_count || 0 }}/{{ selectedClassDetails.capacity }}
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          icon="pi pi-times" 
          text 
          @click="assignStudentDialog = false" 
          :disabled="studentAssignmentLoading"
        />
        <Button 
          label="Assign" 
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
      header="Confirm Removal" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span>
          Are you sure you want to remove this teacher from the class?
        </span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="removeTeacherConfirmDialog = false" 
        />
        <Button 
          label="Yes" 
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
      header="Confirm Removal" 
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-orange-500"></i>
        <span>
          Are you sure you want to remove this student from the class?
        </span>
      </div>
      <template #footer>
        <Button 
          label="No" 
          icon="pi pi-times" 
          text 
          @click="removeStudentConfirmDialog = false" 
        />
        <Button 
          label="Yes" 
          icon="pi pi-check" 
          severity="danger"
          @click="removeStudentFromClass" 
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
</style>
