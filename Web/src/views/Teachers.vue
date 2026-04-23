<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import TeacherService, { type Teacher, type CreateTeacherDTO, type UpdateTeacherDTO, type TeacherAvailability } from '@/service/TeacherService';
import SubjectService, { type Subject } from '@/service/SubjectService';
import ScheduleService, { type Schedule } from '@/service/ScheduleService';
import { Column } from 'primevue';

const { t } = useI18n();
const toast = useToast();
const dt = ref();
const teachers = ref<Teacher[]>([]);
const teacherDialog = ref(false);
const parentDetailsDialog = ref(false);
const deleteTeacherDialog = ref(false);
const deleteTeachersDialog = ref(false);
const teacher = ref<Partial<Teacher>>({});
const selectedTeachers = ref<Teacher[]>([]);
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});
const submitted = ref(false);
const loading = ref(false);
const createAccountDialog = ref(false);
const accountData = ref({
  password: '',
  confirmPassword: '',
  username: '',
  role: 'teacher'
});
const validationErrors = ref({
  password: '',
  confirmPassword: '',
  role: 'teacher',
  username: ''
});

const selectedTeacherData = ref<any>(null);
const manageSubjectsDialog = ref(false);
const availableSubjects = ref<Subject[]>([]);
const selectedSubjects = ref<Subject[]>([]);
const teacherSubjects = ref<Subject[]>([]);
const subjectsLoading = ref(false);
const teacherLoading = ref(false);
// For adding new teacher with subjects
const newTeacherSubjects = ref<Subject[]>([]);

// Schedule dialog states
const scheduleDialog = ref(false);
const selectedTeacherForSchedule = ref<Teacher | null>(null);
const teacherSchedules = ref<{ [day: string]: Schedule[] }>({});
const scheduleLoading = ref(false);
const selectedAcademicYear = ref<string>('');
const availableAcademicYears = ref<string[]>([]);

const availabilityDayOptions = computed(() => [
  { label: t('common.sunday'), value: 'Sunday' },
  { label: t('common.monday'), value: 'Monday' },
  { label: t('common.tuesday'), value: 'Tuesday' },
  { label: t('common.wednesday'), value: 'Wednesday' },
  { label: t('common.thursday'), value: 'Thursday' },
  { label: t('common.friday'), value: 'Friday' },
  { label: t('common.saturday'), value: 'Saturday' }
]);

const contractTypeOptions = [
  { label: 'Permanent', value: 'permanent' },
  { label: 'Part-time', value: 'part_time' }
];

// Days and hours for schedule grid
// English keys used internally for schedule data lookup (API returns English day names)
const weekDayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];

const weekDays = computed(() => [
  t('common.sunday'),
  t('common.monday'),
  t('common.tuesday'),
  t('common.wednesday'),
  t('common.thursday')
]);

const genderOptions = computed(() => [
  { label: t('common.male'), value: 'male' },
  { label: t('common.female'), value: 'female' }
]);

const birthDateModel = computed<Date | null>({
  get() {
    const value = teacher.value.birth_date;
    if (!value) return null;
    return value instanceof Date ? value : new Date(value);
  },
  set(value) {
    teacher.value.birth_date = value;
  }
});

const hireDateModel = computed<Date | null>({
  get() {
    const value = teacher.value.hire_date;
    if (!value) return null;
    return value instanceof Date ? value : new Date(value);
  },
  set(value) {
    teacher.value.hire_date = value;
  }
});

const schoolHours = [
  { hour: 8, label: '08:00 - 09:00' },
  { hour: 9, label: '09:00 - 10:00' },
  { hour: 10, label: '10:00 - 11:00' },
  { hour: 11, label: '11:00 - 12:00' },
  { hour: 12, label: '12:00 - 13:00' },
  { hour: 13, label: '13:00 - 14:00' },
  { hour: 14, label: '14:00 - 15:00' },
  { hour: 15, label: '15:00 - 16:00' },
  { hour: 16, label: '16:00 - 17:00' }
];

// Load teachers on mount
onMounted(async () => {
  await loadTeachers();
});

// Load all teachers
const loadTeachers = async () => {
  try {
    loading.value = true;
    teachers.value = await TeacherService.getTeachers();

    // Add computed properties for each teacher
    teachers.value = teachers.value.map(t => ({
      ...t,
      full_name: `${t.first_name} ${t.last_name}`,
      has_account: !!t.user_id,
      subjects_text: t.subjects && Array.isArray(t.subjects)
        ? t.subjects.map(s => s.name).join(', ')
        : '',
      classes_text: t.classes && Array.isArray(t.classes)
        ? t.classes.map(c => c.name).join(', ')
        : '',
    }));
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('teachers.load_error'),
      life: 3000
    });
    console.log('Error fetching teachers:', error.response.data.message);
  } finally {
    loading.value = false;
  }
};

// Open new teacher dialog
const openNew = async () => {
  teacher.value = {
    contract_type: 'permanent',
    weekly_hours: 20,
    availabilities: []
  };
  newTeacherSubjects.value = [];
  submitted.value = false;
  teacherDialog.value = true;
  // Load available subjects
  await loadAvailableSubjects();
};

// Load available subjects for selection
const loadAvailableSubjects = async () => {
  try {
    availableSubjects.value = await SubjectService.getSubjects();
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('subjects.load_error'),
      life: 3000
    });
  }
};

// Hide dialog
const hideDialog = () => {
  teacherDialog.value = false;
  submitted.value = false;
};

// Save teacher (create or update)
const saveTeacher = async () => {
  submitted.value = true;

  // Validate required fields
  if (!teacher.value.first_name?.trim() ||
      !teacher.value.last_name?.trim() ||
      !teacher.value.birth_date ||
      !teacher.value.hire_date ||
      !teacher.value.specialization?.trim() ||
      teacher.value.salary === undefined ||
      teacher.value.salary === null) {
    toast.add({
      severity: 'error',
      summary: t('common.validation_error'),
      detail: t('validation.fill_required_fields'),
      life: 3000
    });
    return;
  }

  if (teacher.value.weekly_hours === undefined || teacher.value.weekly_hours === null || teacher.value.weekly_hours < 1) {
    toast.add({
      severity: 'error',
      summary: t('common.validation_error'),
      detail: 'Weekly hours must be at least 1.',
      life: 3000
    });
    return;
  }

  const availabilities = normalizeAvailabilities(teacher.value.availabilities || []);
  const hasInvalidAvailability = availabilities.some(slot => !slot.day || !slot.start_time || !slot.end_time || slot.start_time >= slot.end_time);
  if (hasInvalidAvailability) {
    toast.add({
      severity: 'error',
      summary: t('common.validation_error'),
      detail: 'Each availability row must include day, start time, and end time (end must be after start).',
      life: 4000
    });
    return;
  }

  const payload: CreateTeacherDTO | UpdateTeacherDTO = {
    first_name: teacher.value.first_name!,
    last_name: teacher.value.last_name!,
    cin: teacher.value.cin || undefined,
    birth_date: formatDateForApi(teacher.value.birth_date),
    hire_date: formatDateForApi(teacher.value.hire_date),
    specialization: teacher.value.specialization || undefined,
    salary: teacher.value.salary ?? undefined,
    contract_type: (teacher.value.contract_type as 'permanent' | 'part_time') || 'permanent',
    weekly_hours: teacher.value.weekly_hours ?? 20,
    availabilities
  };

  try {
    if (teacher.value.id) {
      // Update existing teacher
      const updated = await TeacherService.updateTeacher(teacher.value.id, payload as UpdateTeacherDTO);
      const index = teachers.value.findIndex(t => t.id === teacher.value.id);
      if (index !== -1) {
        teachers.value[index] = {
          ...updated,
          full_name: `${updated.first_name} ${updated.last_name}`,
          has_account: !!updated.user_id
        };
      }
      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('teachers.update_success'),
        life: 3000
      });
    } else {
      // Create new teacher
      const created = await TeacherService.createTeacher(payload as CreateTeacherDTO);

      // Assign selected subjects if any
      if (newTeacherSubjects.value.length > 0) {
        const subjectIds = newTeacherSubjects.value.map(s => s.id);
        try {
          await TeacherService.assignMultipleSubjects(created.id, subjectIds);
        } catch (error: any) {
          console.log('Error assigning subjects:', error);
          toast.add({
            severity: 'warn',
            summary: t('common.warning'),
            detail: t('teachers.create_partial'),
            life: 3000
          });

        }
      }

      // Reload teachers to get updated data with subjects
      await loadTeachers();

      toast.add({
        severity: 'success',
        summary: t('common.success'),
        detail: t('teachers.create_success'),
        life: 3000
      });
    }

    teacherDialog.value = false;
    teacher.value = {
      contract_type: 'permanent',
      weekly_hours: 20,
      availabilities: []
    };
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('teachers.save_error'),
      life: 3000
    });
  }
};

// Edit teacher
const editTeacher = async (teacherToEdit: Teacher) => {
  teacher.value = {
    ...teacherToEdit,
    // Convert date strings to Date objects for DatePicker
    birth_date: teacherToEdit.birth_date ? new Date(teacherToEdit.birth_date) : null,
    hire_date: teacherToEdit.hire_date ? new Date(teacherToEdit.hire_date) : null,
    contract_type: teacherToEdit.contract_type || 'permanent',
    weekly_hours: teacherToEdit.weekly_hours ?? 20,
    availabilities: normalizeAvailabilities(teacherToEdit.availabilities || []),
  };
  newTeacherSubjects.value = [];
  teacherDialog.value = true;
  // Load available subjects for editing
  await loadAvailableSubjects();
};

//Teacher details
const showTeacherDetails = async(teacherData: Teacher) =>{
  try{
    teacherLoading.value = true;

    //get teacher detail with schedule
    const response = await TeacherService.getTeacher(teacherData.id);
    console.log('Raw API response:', response);



    selectedTeacherData.value = response;

    // Open dialog AFTER data is loaded
    parentDetailsDialog.value = true;
  }catch (error: any){
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('teachers.load_detail_error'),
      life: 3000
    })
  }finally{
    teacherLoading.value = false
  }

}

// Confirm delete teacher
const confirmDeleteTeacher = (teacherToDelete: Teacher) => {
  teacher.value = teacherToDelete;
  deleteTeacherDialog.value = true;
};

// Delete teacher
const deleteTeacher = async () => {
  try {
    await TeacherService.deleteTeacher(teacher.value.id!);
    teachers.value = teachers.value.filter(t => t.id !== teacher.value.id);
    deleteTeacherDialog.value = false;
    teacher.value = {};
    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('teachers.delete_success'),
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('teachers.delete_error'),
      life: 3000
    });
  }
};

// Export to CSV
const exportCSV = () => {
  dt.value.exportCSV();
};

// Confirm delete selected teachers
const confirmDeleteSelected = () => {
  deleteTeachersDialog.value = true;
};

// Delete selected teachers
const deleteSelectedTeachers = async () => {
  try {
    const ids = selectedTeachers.value.map(t => t.id);
    await TeacherService.bulkDeleteTeachers(ids);
    teachers.value = teachers.value.filter(t => !selectedTeachers.value.some(st => st.id === t.id));
    deleteTeachersDialog.value = false;
    selectedTeachers.value = [];
    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('teachers.delete_multiple_success'),
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('teachers.delete_multiple_error'),
      life: 3000
    });
  }
};

// Get account status badge
const getAccountStatusSeverity = (hasAccount: boolean) => {
  return hasAccount ? 'success' : 'warn';
};

const getAccountStatusLabel = (hasAccount: boolean) => {
  return hasAccount ? t('common.active_account') : t('common.no_account');
};

// Open create account dialog
const openCreateAccount = (teacherData: Teacher) => {
  teacher.value = teacherData;
  accountData.value = {
    password: '',
    confirmPassword: '',
    username: teacherData.first_name + '_' + teacherData.last_name,
    role: 'teacher'
  };
  submitted.value = false;
  validationErrors.value = {
    password: '',
    confirmPassword: '',
    username: '',
    role: ''
  };
  createAccountDialog.value = true;
};

// Hide create account dialog
const hideCreateAccountDialog = () => {
  createAccountDialog.value = false;
  accountData.value = {
    password: '',
    confirmPassword: '',
    username: '',
    role: 'teacher'
  };
  validationErrors.value = {
    password: '',
    confirmPassword: '',
    username: '',
    role: ''
  };
  submitted.value = false;
};

// Validate username
const validateUsername = (username: string): string => {
  if (!username || !username.trim()) {
    return t('common.username_required');
  }
  return '';
};

// Validate password
const validatePassword = (password: string): string => {
  if (!password || !password.trim()) {
    return t('validation.password_required');
  }
  if (password.length < 8) {
    return t('validation.password_min_length');
  }
  return '';
};

// Validate confirm password
const validateConfirmPassword = (password: string, confirmPassword: string): string => {
  if (!confirmPassword || !confirmPassword.trim()) {
    return t('validation.confirm_password_required');
  }
  if (password !== confirmPassword) {
    return t('validation.passwords_no_match');
  }
  return '';
};

// Create user account for teacher
const createUserAccount = async () => {
  submitted.value = true;

  // Client-side validation
  validationErrors.value.password = validatePassword(accountData.value.password);
  validationErrors.value.confirmPassword = validateConfirmPassword(
    accountData.value.password,
    accountData.value.confirmPassword,
  );

  // Check if there are any validation errors
  if (validationErrors.value.password || validationErrors.value.confirmPassword) {
    return;
  }

  try {
    const updated = await TeacherService.createUserAccount(
      teacher.value.id!,
      accountData.value.username,
      accountData.value.password,
      accountData.value.role
    );

    // Update the teacher in the list
    const index = teachers.value.findIndex(t => t.id === teacher.value.id);
    if (index !== -1) {
      teachers.value[index] = {
        ...updated,
        full_name: `${updated.first_name} ${updated.last_name}`,
        has_account: !!updated.user_id
      };
    }

    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('teachers.account_created'),
      life: 3000
    });

    createAccountDialog.value = false;
    accountData.value = {
      password: '',
      confirmPassword: '',
      username: '',
      role: 'teacher'
    };
    validationErrors.value = {
      password: '',
      confirmPassword: '',
      username: '',
      role: ''
    };
    submitted.value = false;
  } catch (error: any) {
    // Handle server-side validation errors
    if (error.response?.data?.errors) {
      const serverErrors = error.response.data.errors;
      console.log('Server validation errors:', serverErrors);
      validationErrors.value.password = serverErrors.password?.[0] || '';
    } else {
      toast.add({
        severity: 'error',
        summary: t('common.error'),
        detail: error.response?.data?.message || t('teachers.account_create_error'),
        life: 3000
      });
    }
  }finally {
    await loadTeachers();
  }
};

// Get subjects of teachers
const getTeacherSubjects = (teacher: Teacher) => {
  return teacher.subjects && Array.isArray(teacher.subjects)
    ? teacher.subjects.map(s => s.name).join(', ')
    : t('common.na');
};



// Open manage subjects dialog
const openManageSubjects = async (teacherData: Teacher) => {
  teacher.value = teacherData;
  manageSubjectsDialog.value = true;
  await loadSubjectsForDialog();
};

// Load all subjects and teacher's current subjects
const loadSubjectsForDialog = async () => {
  try {
    subjectsLoading.value = true;
    // Load all available subjects
    availableSubjects.value = await SubjectService.getSubjects();
    // Load teacher's current subjects
    teacherSubjects.value = await TeacherService.getTeacherSubjects(teacher.value.id!);
    // Set selected subjects
    selectedSubjects.value = [...teacherSubjects.value];
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('subjects.load_error'),
      life: 3000
    });
  } finally {
    subjectsLoading.value = false;
  }
};

// Hide manage subjects dialog
const hideManageSubjectsDialog = () => {
  manageSubjectsDialog.value = false;
  selectedSubjects.value = [];
  teacherSubjects.value = [];
};

// Save subject assignments
const saveSubjectAssignments = async () => {
  try {
    subjectsLoading.value = true;

    // Get subject IDs
    const selectedIds = selectedSubjects.value.map(s => s.id);
    const currentIds = teacherSubjects.value.map(s => s.id);

    // Find subjects to add and remove
    const toAdd = selectedIds.filter(id => !currentIds.includes(id));
    const toRemove = currentIds.filter(id => !selectedIds.includes(id));

    // Add new subjects
    if (toAdd.length > 0) {
      await TeacherService.assignMultipleSubjects(teacher.value.id!, toAdd);
    }

    // Remove unselected subjects
    for (const subjectId of toRemove) {
      await TeacherService.removeSubject(teacher.value.id!, subjectId);
    }

    // Reload teachers to update the list
    await loadTeachers();

    toast.add({
      severity: 'success',
      summary: t('common.success'),
      detail: t('teachers.subjects_updated'),
      life: 3000
    });

    hideManageSubjectsDialog();
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('teachers.subjects_update_error'),
      life: 3000
    });
  } finally {
    subjectsLoading.value = false;
  }
};

// Schedule Management Functions

// Calculate current academic year
const getCurrentAcademicYear = (): string => {
  const now = new Date();
  const currentYear = now.getFullYear();
  const currentMonth = now.getMonth(); // 0-11

  // If we're past September (month 8), academic year is current-next
  // Otherwise it's previous-current
  if (currentMonth >= 8) {
    return `${currentYear}-${currentYear + 1}`;
  } else {
    return `${currentYear - 1}-${currentYear}`;
  }
};

// Open schedule dialog for a teacher
const viewSchedule = async (teacherToView: Teacher) => {
  selectedTeacherForSchedule.value = teacherToView;

  // Get unique academic years from teacher's classes
  const uniqueYears = new Set<string>();
  if (teacherToView.classes && Array.isArray(teacherToView.classes)) {
    teacherToView.classes.forEach((cls: any) => {
      if (cls.academic_year) {
        uniqueYears.add(cls.academic_year);
      }
    });
  }

  // Convert to array and sort (most recent first)
  availableAcademicYears.value = Array.from(uniqueYears).sort().reverse();

  // Set default academic year to current year or first available
  const currentYear = getCurrentAcademicYear();
  if (availableAcademicYears.value.includes(currentYear)) {
    selectedAcademicYear.value = currentYear;
  } else if (availableAcademicYears.value.length > 0) {
    selectedAcademicYear.value = availableAcademicYears.value[0];
  } else {
    selectedAcademicYear.value = currentYear;
    availableAcademicYears.value = [currentYear];
  }

  // Load schedule for selected year
  await loadTeacherSchedule();

  scheduleDialog.value = true;
};

// Load teacher schedule for selected academic year
const loadTeacherSchedule = async () => {
  if (!selectedTeacherForSchedule.value) return;

  try {
    scheduleLoading.value = true;

    const response = await ScheduleService.getTeacherSchedule(
      selectedTeacherForSchedule.value.id,
      selectedAcademicYear.value
    );


    // Extract the data from response
    const scheduleData = (response as any).data || response;


    // The API returns data grouped by day with capitalized day names
    // We need to normalize to lowercase for consistent access
    if (scheduleData && typeof scheduleData === 'object' && !Array.isArray(scheduleData)) {
      // Convert keys to lowercase for consistent access
      const normalizedSchedules: { [day: string]: Schedule[] } = {};
      Object.keys(scheduleData).forEach(day => {
        const dayKey = day.toLowerCase();
        normalizedSchedules[dayKey] = scheduleData[day];
      });
      teacherSchedules.value = normalizedSchedules;
    } else if (Array.isArray(scheduleData)) {
      // If it's an array, organize by day
      const organizedSchedules: { [day: string]: Schedule[] } = {};
      scheduleData.forEach((schedule: Schedule) => {
        const dayKey = schedule.day.toLowerCase();
        if (!organizedSchedules[dayKey]) {
          organizedSchedules[dayKey] = [];
        }
        organizedSchedules[dayKey].push(schedule);
      });
      teacherSchedules.value = organizedSchedules;
    } else {
      teacherSchedules.value = {};
    }

  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('common.error'),
      detail: error.response?.data?.message || t('teachers.schedule_error'),
      life: 3000
    });
  } finally {
    scheduleLoading.value = false;
  }
};

// Handler for academic year change
const onAcademicYearChange = async () => {
  await loadTeacherSchedule();
};

// Get schedule for a specific day key (English lowercase) and hour
const getScheduleForSlot = (dayKey: string, hour: number): Schedule | null => {
  const daySchedules = teacherSchedules.value[dayKey] || [];
  const startTime = `${hour.toString().padStart(2, '0')}:00:00`;

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
  selectedTeacherForSchedule.value = null;
  teacherSchedules.value = {};
  selectedAcademicYear.value = '';
  availableAcademicYears.value = [];
};

const formatDateForApi = (value: Date | string | null | undefined): string | undefined => {
  if (!value) return undefined;
  if (typeof value === 'string') {
    return value.slice(0, 10);
  }

  const year = value.getFullYear();
  const month = `${value.getMonth() + 1}`.padStart(2, '0');
  const day = `${value.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const normalizeTimeForApi = (value: string | undefined): string => {
  if (!value) return '';
  return value.slice(0, 5);
};

const normalizeAvailabilities = (items: TeacherAvailability[]): TeacherAvailability[] => {
  return items.map((item) => ({
    day: item.day,
    start_time: normalizeTimeForApi(item.start_time),
    end_time: normalizeTimeForApi(item.end_time)
  }));
};

const formatDisplayDate = (value: Date | string | null | undefined): string => {
  if (!value) return t('common.na');

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return t('common.na');
  }

  return date.toLocaleDateString();
};

const formatCurrency = (value: number | null | undefined): string => {
  if (value === null || value === undefined) return t('common.na');
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'MAD',
    maximumFractionDigits: 0
  }).format(value);
};

const getContractTypeLabel = (contractType: string | null | undefined): string => {
  if (contractType === 'permanent') return 'Permanent';
  if (contractType === 'part_time') return 'Part-time';
  return t('common.na');
};

const getTeacherDetailSubjects = () => {
  if (!selectedTeacherData.value) return [];

  if (Array.isArray(selectedTeacherData.value.teachable_subjects)) {
    return selectedTeacherData.value.teachable_subjects;
  }

  if (Array.isArray(selectedTeacherData.value.subjects)) {
    return selectedTeacherData.value.subjects;
  }

  return [];
};

const getTeacherDetailClasses = () => {
  if (!selectedTeacherData.value || !Array.isArray(selectedTeacherData.value.classes)) {
    return [];
  }

  return selectedTeacherData.value.classes;
};

const getTeacherDetailAvailabilities = () => {
  if (!selectedTeacherData.value || !Array.isArray(selectedTeacherData.value.availabilities)) {
    return [];
  }

  return selectedTeacherData.value.availabilities;
};

const addAvailabilityRow = () => {
  if (!teacher.value.availabilities) {
    teacher.value.availabilities = [];
  }

  teacher.value.availabilities.push({
    day: 'Sunday',
    start_time: '08:00',
    end_time: '16:00'
  });
};

const removeAvailabilityRow = (index: number) => {
  if (!teacher.value.availabilities) return;
  teacher.value.availabilities.splice(index, 1);
};
</script>

<template>
  <div class="card">
    <Toast />

    <!-- Toolbar -->
    <Toolbar class="mb-6">
      <template #start>
        <Button
          :label="t('teachers.new_teacher')"
          icon="pi pi-plus"
          severity="secondary"
          class="mr-2"
          @click="openNew"
        />
        <Button
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="secondary"
          :disabled="!selectedTeachers || !selectedTeachers.length"
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
      v-model:selection="selectedTeachers"
      :value="teachers"
      dataKey="id"
      :paginator="true"
      :rows="10"
      :filters="filters"
      :loading="loading"
      exportFilename="teachers"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :rowsPerPageOptions="[5, 10, 25, 50]"

      currentPageReportTemplate="Showing {first} to {last} of {totalRecords} teachers"
      class="p-datatable-sm"
      @row-click="showTeacherDetails($event.data)"
    >
      <template #header>
        <div class="flex flex-wrap gap-2 items-center justify-between">
          <h4 class="m-0 text-xl font-semibold">{{ t('teachers.title') }}</h4>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="filters['global'].value"
              :placeholder="t('teachers.search_placeholder')"
            />
          </IconField>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8">
          <i class="pi pi-users text-4xl text-muted-color mb-3 block"></i>
          <p class="text-muted-color">{{ t('teachers.no_teachers') }}</p>
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



      <Column field="phone" :header="t('common.phone')" sortable style="min-width: 12rem">
        <template #body="{ data }">
          <div v-if="data.phone" class="flex items-center gap-2">
            <i class="pi pi-phone text-sm text-muted-color"></i>
            <span>{{ data.phone }}</span>
          </div>
          <span v-else class="text-muted-color">{{ t('common.na') }}</span>
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



      <Column field="classes_text" :header="t('teachers.classes')" style="min-width: 15rem">
        <template #body="{ data }">
          <span>{{ data.classes_text || t('common.na') }}</span>
        </template>
      </Column>

      <Column field="subjects_text" :header="t('teachers.subjects')" style="min-width: 15rem">
        <template #body="{ data }">
          <span>{{ data.subjects_text || t('common.na') }}</span>
        </template>
      </Column>

      <Column :header="t('common.actions')" :exportable="false" style="min-width: 16rem">
        <template #body="{ data }">

          <Button
            v-if="!data.has_account"
            icon="pi pi-user-plus"
            outlined
            rounded
            severity="success"
            class="mr-2"
            @click="openCreateAccount(data)"
            :v-tooltip.top="t('common.create_account')"
          />
          <Button
            icon="pi pi-calendar"
            outlined
            rounded
            severity="info"
            class="mr-2"
            @click="viewSchedule(data)"
            :v-tooltip.top="t('teachers.view_schedule')"
          />
          <Button
            icon="pi pi-book"
            outlined
            rounded
            severity="info"
            class="mr-2"
            @click="openManageSubjects(data)"
            :v-tooltip.top="t('teachers.manage_subjects')"
          />
          <Button
            icon="pi pi-pencil"
            outlined
            rounded
            class="mr-2"
            @click="editTeacher(data)"
            :v-tooltip.top="t('common.edit')"
          />
          <Button
            icon="pi pi-trash"
            outlined
            rounded
            severity="danger"
            @click="confirmDeleteTeacher(data)"
            :v-tooltip.top="t('common.delete')"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Add/Edit Teacher Dialog -->
    <Dialog
      v-model:visible="teacherDialog"
      :style="{ width: '550px' }"
      :header="t('teachers.teacher_details')"
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
              v-model="teacher.first_name"
              required
              autofocus
              :invalid="submitted && !teacher.first_name"
              :placeholder="t('teachers.enter_first_name')"
            />
            <small v-if="submitted && !teacher.first_name" class="text-red-500">
              {{ t('validation.first_name_required') }}
            </small>
          </div>

          <div>
            <label for="last_name" class="block font-semibold mb-2">
              {{ t('common.last_name') }} <span class="text-red-500">*</span>
            </label>
            <InputText
              id="last_name"
              v-model="teacher.last_name"
              required
              :invalid="submitted && !teacher.last_name"
              :placeholder="t('teachers.enter_last_name')"
            />
            <small v-if="submitted && !teacher.last_name" class="text-red-500">
              {{ t('validation.last_name_required') }}
            </small>
          </div>
        </div>

        <!-- CIN -->
        <div>
          <label for="cin" class="block font-semibold mb-2">{{ t('common.cin') }}</label>
          <InputText
            id="cin"
            v-model="teacher.cin"
            :placeholder="t('teachers.enter_cin')"
          />
        </div>

        <!-- Date Fields -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="birth_date" class="block font-semibold mb-2">
              {{ t('teachers.birth_date') }} <span class="text-red-500">*</span>
            </label>
            <DatePicker
              id="birth_date"
              v-model="birthDateModel"
              dateFormat="yy-mm-dd"
              showIcon
              :invalid="submitted && !birthDateModel"
              :placeholder="t('teachers.select_birth_date')"
            />
            <small v-if="submitted && !birthDateModel" class="text-red-500">
              {{ t('validation.birth_date_required') }}
            </small>
          </div>

          <div>
            <label for="hire_date" class="block font-semibold mb-2">
              {{ t('teachers.hire_date') }} <span class="text-red-500">*</span>
            </label>
            <DatePicker
              id="hire_date"
              v-model="hireDateModel"
              dateFormat="yy-mm-dd"
              showIcon
              :invalid="submitted && !hireDateModel"
              :placeholder="t('teachers.select_hire_date')"
            />
            <small v-if="submitted && !hireDateModel" class="text-red-500">
              {{ t('validation.hire_date_required') }}
            </small>
          </div>
        </div>

        <!-- Specialization -->
        <div>
          <label for="specialization" class="block font-semibold mb-2">
            {{ t('teachers.specialization') }} <span class="text-red-500">*</span>
          </label>
          <InputText
            id="specialization"
            v-model="teacher.specialization"
            :invalid="submitted && !teacher.specialization"
            :placeholder="t('teachers.enter_specialization')"
          />
          <small v-if="submitted && !teacher.specialization" class="text-red-500">
            {{ t('validation.specialization_required') }}
          </small>
        </div>

        <!-- Salary -->
        <div>
          <label for="salary" class="block font-semibold mb-2">
            {{ t('teachers.salary') }} <span class="text-red-500">*</span>
          </label>
          <InputNumber
            id="salary"
            v-model="teacher.salary"
            mode="currency"
            currency="DZD"
            locale="fr-DZ"
            :invalid="submitted && (teacher.salary === undefined || teacher.salary === null)"
            :placeholder="t('teachers.enter_salary')"
          />
          <small v-if="submitted && (teacher.salary === undefined || teacher.salary === null)" class="text-red-500">
            {{ t('validation.salary_required') }}
          </small>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="contract_type" class="block font-semibold mb-2">
              {{t('teachers.contract_type')}} <span class="text-red-500">*</span>
            </label>
            <Select
              id="contract_type"
              v-model="teacher.contract_type"
              :options="contractTypeOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="t('teachers.select_contract_type')"
              class="w-full"
            />
          </div>

          <div>
            <label for="weekly_hours" class="block font-semibold mb-2">
              {{t('teachers.weekly_hours')}} <span class="text-red-500">*</span>
            </label>
            <InputNumber
              id="weekly_hours"
              v-model="teacher.weekly_hours"
              :min="1"
              :max="60"
              class="w-full"
              :invalid="submitted && (teacher.weekly_hours === undefined || teacher.weekly_hours === null || teacher.weekly_hours < 1)"
              :placeholder="t('teachers.enter_weekly_hours')"
            />
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="block font-semibold">{{t('teachers.availability')}}</label>
            <Button
              :label="t('teachers.add_slot')"
              icon="pi pi-plus"
              size="small"
              text
              @click="addAvailabilityRow"
            />
          </div>

          <div
            v-if="teacher.availabilities && teacher.availabilities.length"
            class="flex flex-col gap-2 border border-surface-200 dark:border-surface-700 rounded p-3"
          >
            <div
              v-for="(slot, index) in teacher.availabilities"
              :key="index"
              class="grid grid-cols-12 gap-2 items-end"
            >
              <div class="col-span-12 md:col-span-4">
                <label class="block text-xs mb-1">{{t('teachers.day')}}</label>
                <Select
                  v-model="slot.day"
                  :options="availabilityDayOptions"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full"
                />
              </div>

              <div class="col-span-6 md:col-span-3">
                <label class="block text-xs mb-1">{{t('teachers.start')}}</label>
                <InputText v-model="slot.start_time" type="time" class="w-full" />
              </div>

              <div class="col-span-6 md:col-span-3">
                <label class="block text-xs mb-1">{{t('teachers.end')}}</label>
                <InputText v-model="slot.end_time" type="time" class="w-full" />
              </div>

              <div class="col-span-12 md:col-span-2">
                <Button
                  icon="pi pi-trash"
                  severity="danger"
                  text
                  class="w-full"
                  @click="removeAvailabilityRow(index)"
                />
              </div>
            </div>
          </div>

          <small v-else class="text-muted-color">{{t('teachers.no_availability_slots')}}</small>
        </div>

        <!-- Subjects (only for new teachers) -->
        <div v-if="!teacher.id">
          <label for="subjects" class="block font-semibold mb-2">
            {{ t('teachers.subjects') }}
          </label>
          <MultiSelect
            id="subjects"
            v-model="newTeacherSubjects"
            :options="availableSubjects"
            dataKey="id"
            optionLabel="name"
            :placeholder="t('teachers.select_subjects_placeholder')"
            :maxSelectedLabels="3"
            class="w-full"
            display="chip"
          >
            <template #option="{ option }">
              <div class="flex items-center gap-2">
                <i class="pi pi-book text-sm text-muted-color"></i>
                <span>{{ option.name }}</span>
              </div>
            </template>
          </MultiSelect>
          <small class="text-muted-color mt-2 block">
            {{ t('teachers.subjects_assign_hint') }}
          </small>
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
          @click="saveTeacher"
        />
      </template>
    </Dialog>

    <!-- Delete Teacher Confirmation Dialog -->
    <Dialog
      v-model:visible="deleteTeacherDialog"
      :style="{ width: '450px' }"
      :header="t('common.confirm_deletion')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-3xl text-red-500"></i>
        <span v-if="teacher">
          {{ t('common.are_you_sure_delete') }} <b>{{ teacher.full_name }}</b>?
        </span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteTeacherDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="deleteTeacher"
        />
      </template>
    </Dialog>

    <!-- Delete Multiple Teachers Confirmation Dialog -->
    <Dialog
      v-model:visible="deleteTeachersDialog"
      :style="{ width: '450px' }"
      :header="t('common.confirm_deletion')"
      :modal="true"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-3xl text-red-500"></i>
        <span>{{ t('common.are_you_sure_delete') }}</span>
      </div>
      <template #footer>
        <Button
          :label="t('common.no')"
          icon="pi pi-times"
          text
          @click="deleteTeachersDialog = false"
        />
        <Button
          :label="t('common.yes')"
          icon="pi pi-check"
          severity="danger"
          @click="deleteSelectedTeachers"
        />
      </template>
    </Dialog>

    <!-- Create User Account Dialog -->
    <Dialog
      v-model:visible="createAccountDialog"
      :style="{ width: '500px' }"
      :header="t('common.create_user_account')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4 rounded">
          <div class="flex items-start gap-3">
            <i class="pi pi-info-circle text-blue-600 text-xl mt-1"></i>
            <div>
              <p class="font-semibold text-blue-900 dark:text-blue-100 mb-1">{{ t('common.creating_account_for') }}</p>
              <p class="text-blue-700 dark:text-blue-300">{{ teacher.full_name }}</p>
            </div>
          </div>
        </div>

        <!-- Username (pre-filled, editable) -->
        <div>
          <label for="account_username" class="block font-semibold mb-2">
            {{ t('common.username') }} <span class="text-red-500">*</span>
          </label>
          <InputText
            id="account_username"
            v-model="accountData.username"
            required
            autofocus
            :invalid="submitted && !!validationErrors.username"
            :placeholder="t('common.username')"
            @blur="validationErrors.username = validateUsername(accountData.username)"
          />
          <small v-if="submitted && validationErrors.username" class="text-red-500">
            {{ validationErrors.username }}
          </small>
        </div>

        <!-- Password -->
        <div>
          <label for="account_password" class="block font-semibold mb-2">
            {{ t('common.password') }} <span class="text-red-500">*</span>
          </label>
          <Password
            id="account_password"
            v-model="accountData.password"
            required
            toggleMask
            :invalid="submitted && !!validationErrors.password"
            :placeholder="t('teachers.enter_password_hint')"
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
            {{ t('common.confirm_password') }} <span class="text-red-500">*</span>
          </label>
          <Password
            id="account_confirm_password"
            v-model="accountData.confirmPassword"
            required
            toggleMask
            :invalid="submitted && !!validationErrors.confirmPassword"
            :placeholder="t('teachers.confirm_password_placeholder')"
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
          :label="t('common.cancel')"
          icon="pi pi-times"
          text
          @click="hideCreateAccountDialog"
        />
        <Button
          :label="t('common.create_account')"
          icon="pi pi-check"
          severity="success"
          @click="createUserAccount"
        />
      </template>
    </Dialog>

    <!-- Manage Subjects Dialog -->
    <Dialog
      v-model:visible="manageSubjectsDialog"
      :style="{ width: '600px' }"
      :header="t('teachers.manage_teacher_subjects')"
      :modal="true"
      class="p-fluid"
    >
      <div class="flex flex-col gap-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4 rounded">
          <div class="flex items-start gap-3">
            <i class="pi pi-info-circle text-blue-600 text-xl mt-1"></i>
            <div>
              <p class="font-semibold text-blue-900 dark:text-blue-100 mb-1">{{ t('teachers.managing_subjects_for') }}</p>
              <p class="text-blue-700 dark:text-blue-300">{{ teacher.full_name }}</p>
            </div>
          </div>
        </div>

        <!-- Subjects MultiSelect -->
        <div>
          <label for="subjects" class="block font-semibold mb-2">
            {{ t('teachers.select_subjects') }}
          </label>
          <MultiSelect
            id="subjects"
            v-model="selectedSubjects"
            :options="availableSubjects"
            dataKey="id"
            optionLabel="name"
            :placeholder="t('teachers.select_subjects_placeholder')"
            :loading="subjectsLoading"
            :maxSelectedLabels="10"
            class="w-full"
            display="chip"
          >
            <template #option="{ option }">
              <div class="flex items-center gap-2">
                <i class="pi pi-book text-sm text-muted-color"></i>
                <span>{{ option.name }}</span>
              </div>
            </template>
          </MultiSelect>
          <small class="text-muted-color mt-2 block">
            {{ t('teachers.subjects_hint') }}
          </small>
        </div>

        <!-- Currently Assigned Subjects Preview -->
        <div v-if="selectedSubjects.length > 0" class="border rounded p-4">
          <p class="font-semibold mb-2">{{ t('teachers.selected_subjects', { count: selectedSubjects.length }) }}:</p>
          <div class="flex flex-wrap gap-2">
            <Tag
              v-for="subject in selectedSubjects"
              :key="subject.id"
              :value="subject.name"
              severity="info"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="t('common.cancel')"
          icon="pi pi-times"
          text
          @click="hideManageSubjectsDialog"
          :disabled="subjectsLoading"
        />
        <Button
          :label="t('teachers.save_subjects')"
          icon="pi pi-check"
          severity="success"
          @click="saveSubjectAssignments"
          :loading="subjectsLoading"
        />
      </template>
    </Dialog>

    <!-- View Teacher Schedule Dialog (Read-Only) -->
    <Dialog
      v-model:visible="scheduleDialog"
      :style="{ width: '95vw', maxWidth: '1200px' }"
      :header="selectedTeacherForSchedule ? `Schedule - ${selectedTeacherForSchedule.first_name} ${selectedTeacherForSchedule.last_name}` : 'Schedule'"
      :modal="true"
      maximizable
    >
      <div v-if="scheduleLoading" class="text-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary mb-3"></i>
        <p class="text-muted-color">{{ t('teachers.loading_schedule') }}</p>
      </div>

      <div v-else>
        <div class="mb-4 p-3 bg-surface-50 dark:bg-surface-800 rounded-border">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="selectedTeacherForSchedule?.specialization" class="flex items-center gap-2">
              <i class="pi pi-briefcase text-primary"></i>
              <span class="font-semibold">{{ t('teachers.specialization_label') }}</span>
              <span>{{ selectedTeacherForSchedule.specialization }}</span>
            </div>
            <div class="flex items-center gap-2">
              <label for="academicYear" class="font-semibold whitespace-nowrap">
                <i class="pi pi-calendar text-primary mr-1"></i>
                {{ t('teachers.academic_year_label') }}
              </label>
              <Select
                id="academicYear"
                v-model="selectedAcademicYear"
                :options="availableAcademicYears"
                :placeholder="t('teachers.select_year_placeholder')"
                @change="onAcademicYearChange"
                class="w-full"
              />
            </div>
          </div>
        </div>

        <div class="schedule-container" style="overflow-x: auto;">
          <table class="schedule-table">
            <thead>
              <tr>
                <th class="schedule-header time-column">{{ t('common.time') }}</th>
                <th
                  v-for="(day, idx) in weekDays"
                  :key="weekDayKeys[idx]"
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
                  v-for="(day, idx) in weekDays"
                  :key="weekDayKeys[idx]"
                  class="schedule-cell"
                >
                  <template v-if="getScheduleForSlot(weekDayKeys[idx], timeSlot.hour)">
                    <div class="schedule-content has-schedule">
                      <div class="font-semibold text-sm">
                        {{ getScheduleForSlot(weekDayKeys[idx], timeSlot.hour)?.assignment?.subject?.name }}
                      </div>
                      <div class="text-xs mt-1">
                        {{ getScheduleForSlot(weekDayKeys[idx], timeSlot.hour)?.assignment?.class?.name }}
                      </div>
                      <div v-if="getScheduleForSlot(weekDayKeys[idx], timeSlot.hour)?.room" class="text-xs mt-1">
                        <i class="pi pi-map-marker"></i> {{ getScheduleForSlot(weekDayKeys[idx], timeSlot.hour)?.room }}
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

    <!-- Teacher Details Dialog -->
    <Dialog
      v-model:visible="parentDetailsDialog"
      :style="{ width: '920px', maxWidth: '95vw' }"
      :header="t('teachers.teacher_details')"
      :modal="true"
    >
      <div v-if="teacherLoading" class="flex justify-center items-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      </div>

      <div v-else-if="selectedTeacherData" class="flex flex-col gap-6">
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-user text-primary"></i>
            {{ t('common.personal_information') }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">{{ t('common.full_name') }}</label>
              <p class="font-semibold">{{ selectedTeacherData.first_name }} {{ selectedTeacherData.last_name }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.cin') }}</label>
              <p class="font-semibold">{{ selectedTeacherData.cin || t('common.na') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.birth_date') }}</label>
              <p class="font-semibold">{{ formatDisplayDate(selectedTeacherData.birth_date) }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.hire_date') }}</label>
              <p class="font-semibold">{{ formatDisplayDate(selectedTeacherData.hire_date) }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.phone') }}</label>
              <p class="font-semibold">
                <i class="pi pi-phone text-sm mr-2"></i>
                {{ selectedTeacherData.user?.phone || selectedTeacherData.phone || t('common.na') }}
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.email') }}</label>
              <p class="font-semibold">
                <i class="pi pi-envelope text-sm mr-2"></i>
                {{ selectedTeacherData.user?.email || selectedTeacherData.email || t('common.na') }}
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.account_status') }}</label>
              <p>
                <Tag
                  :value="getAccountStatusLabel(!!selectedTeacherData.user_id)"
                  :severity="getAccountStatusSeverity(!!selectedTeacherData.user_id)"
                />
              </p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('common.employee_id') }}</label>
              <p class="font-semibold">{{ selectedTeacherData.id || t('common.na') }}</p>
            </div>
          </div>
        </div>

        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-briefcase text-primary"></i>
            {{ t('teachers.employment_information') }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">{{ t('teachers.specialization') }}</label>
              <p class="font-semibold">{{ selectedTeacherData.specialization || t('common.na') }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('teachers.contract_type') }}</label>
              <p class="font-semibold">{{ getContractTypeLabel(selectedTeacherData.contract_type) }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('teachers.salary') }}</label>
              <p class="font-semibold">{{ formatCurrency(selectedTeacherData.salary) }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('teachers.teacher_weekly_hours') }}</label>
              <p class="font-semibold">{{ selectedTeacherData.weekly_hours ?? t('common.na') }}</p>
            </div>
          </div>
        </div>

        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-book text-primary"></i>
            {{ t('teachers.subjects') }}
          </h3>
          <div v-if="getTeacherDetailSubjects().length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-2">
            <Tag
              v-for="(subject, index) in getTeacherDetailSubjects()"
              :key="subject.id || index"
              :value="subject.name"
              severity="info"
            />
          </div>
          <p v-else class="text-muted-color">{{ t('teachers.no_subjects') }}</p>
        </div>

        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-calendar text-primary"></i>
            {{ t('teachers.availability') }}
          </h3>
          <div v-if="getTeacherDetailAvailabilities().length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div
              v-for="(slot, index) in getTeacherDetailAvailabilities()"
              :key="slot.id || `${slot.day}-${slot.start_time}-${index}`"
              class="rounded-md border border-surface-200 dark:border-surface-700 px-3 py-2"
            >
              <p class="font-semibold">{{ slot.day }}</p>
              <p class="text-sm text-muted-color">{{ slot.start_time }} - {{ slot.end_time }}</p>
            </div>
          </div>
          <p v-else class="text-muted-color">{{ t('teachers.no_availability_slots') }}</p>
        </div>

        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-users text-primary"></i>
            {{ t('teachers.class_assignment') }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="text-sm text-muted-color">{{ t('teachers.total_classes') }}</label>
              <p class="font-semibold">{{ selectedTeacherData.classes_count ?? getTeacherDetailClasses().length }}</p>
            </div>
              
          </div>

          <div v-if="getTeacherDetailClasses().length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-2">
            <Tag
              v-for="(classItem, index) in getTeacherDetailClasses()"
              :key="`${classItem.name}-${index}`"
              :value="classItem.name"
              severity="success"
            />
          </div>
          <p v-else class="text-muted-color">{{ t('teachers.no_classes_assigned') }}</p>
        </div>

        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i class="pi pi-clock text-primary"></i>
            {{ t('teachers.record_information') }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-muted-color">{{ t('teachers.created_at') }}</label>
              <p class="font-semibold">{{ formatDisplayDate(selectedTeacherData.created_at) }}</p>
            </div>
            <div>
              <label class="text-sm text-muted-color">{{ t('teachers.last_updated') }}</label>
              <p class="font-semibold">{{ formatDisplayDate(selectedTeacherData.updated_at) }}</p>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          :label="t('common.close')"
          icon="pi pi-times"
          @click="parentDetailsDialog = false"
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

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
  background-color: var(--surface-50);
  color: var(--text-color);
  font-weight: 600;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
  background-color: var(--surface-100);
}
:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: pointer;
}
</style>
