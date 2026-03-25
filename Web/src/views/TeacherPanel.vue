<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import ApiService from '@/service/ApiService';
import AttendanceService from '@/service/AttendanceService';
import GradeService from '@/service/GradeService';
import ScheduleService from '@/service/ScheduleService';

const toast = useToast();
const { t } = useI18n();

// ─── State ────────────────────────────────────────────────────────────────────
const loading = ref(false);
const teacherId = ref<number | null>(null);
const myClasses = ref<any[]>([]);

// ─── Schedule ─────────────────────────────────────────────────────────────────
const scheduleDialog = ref(false);
const scheduleLoading = ref(false);
const teacherSchedules = ref<Record<string, any[]>>({});

const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
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
];

// ─── Attendance ────────────────────────────────────────────────────────────────
const attendanceDialog = ref(false);
const selectedClass = ref<any>(null);
const attendanceSubject = ref<any>(null);
const attendanceDate = ref<Date>(new Date());
const attendanceRows = ref<Array<{ student: any; status: string; reason: string; existingId: number | null }>>([]);
const attendanceLoading = ref(false);
const savingAttendance = ref(false);

const statusOptions = [
  { label: 'Present', value: 'present' },
  { label: 'Absent', value: 'absent' },
  { label: 'Late', value: 'late' },
  { label: 'Excused', value: 'excused' },
];

const statusSeverity: Record<string, string> = {
  present: 'success',
  absent: 'danger',
  late: 'warn',
  excused: 'info',
};

// ─── Grades ───────────────────────────────────────────────────────────────────
const gradesDialog = ref(false);
const gradeClass = ref<any>(null);
const gradeSubject = ref<any>(null);
const gradeExamType = ref<string | null>(null);
const gradeSemester = ref<string | null>(null);
const gradeMaxGrade = ref<number>(20);
const gradeRows = ref<Array<{ student: any; grade: number | null; comment: string }>>([]);
const savingGrades = ref(false);

const examTypeOptions = [
  { label: 'Quiz', value: 'quiz' },
  { label: 'Homework', value: 'homework' },
  { label: 'Exam', value: 'exam' },
  { label: 'Project', value: 'project' },
];

const semesterOptions = [
  { label: 'Semester 1', value: '1' },
  { label: 'Semester 2', value: '2' },
  { label: 'Semester 3', value: '3' },
];

// ─── Computed ─────────────────────────────────────────────────────────────────
const currentAcademicYear = computed(() => {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth() + 1;
  return month >= 9 ? `${year}-${year + 1}` : `${year - 1}-${year}`;
});

const attendanceSubjectOptions = computed(() => selectedClass.value?.subjects || []);
const gradeSubjectOptions = computed(() => gradeClass.value?.subjects || []);

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  await init();
});

// ─── Init ──────────────────────────────────────────────────────────────────────
const init = async () => {
  loading.value = true;
  try {
    const meResp = await ApiService.getCurrentUser();
    const user = (meResp as any).data ?? meResp;
    teacherId.value = user?.teacher?.id ?? null;

    const classesResp = await ApiService.get<any[]>('/teacher/classes');
    myClasses.value = (classesResp.data as any) || [];
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err?.response?.data?.message || t('teacher_portal.load_classes_error'), life: 4000 });
  } finally {
    loading.value = false;
  }
};

// ─── Schedule Dialog ──────────────────────────────────────────────────────────
const openSchedule = async () => {
  if (!teacherId.value) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'Teacher profile not linked to this account', life: 3000 });
    return;
  }
  scheduleDialog.value = true;
  scheduleLoading.value = true;
  try {
    const resp = await ScheduleService.getTeacherSchedule(teacherId.value);
    teacherSchedules.value = (resp as any)?.data ?? resp ?? {};
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_schedule_error'), life: 3000 });
  } finally {
    scheduleLoading.value = false;
  }
};

const getScheduleForSlot = (day: string, hour: number) => {
  const daySchedules = teacherSchedules.value[day] || [];
  return daySchedules.filter((s: any) => {
    if (!s.start_time) return false;
    const startHour = parseInt(s.start_time.split(':')[0]);
    return startHour === hour;
  });
};

// ─── Attendance Dialog ─────────────────────────────────────────────────────────
const openAttendance = async (cls: any) => {
  selectedClass.value = cls;

  // Auto-select subject if only one
  if (cls.subjects?.length === 1) {
    attendanceSubject.value = cls.subjects[0];
  } else {
    attendanceSubject.value = null;
  }

  attendanceDate.value = new Date();
  attendanceDialog.value = true;
  await loadAttendanceRows();
};

const loadAttendanceRows = async () => {
  if (!selectedClass.value) return;
  attendanceLoading.value = true;
  try {
    const dateStr = formatDate(attendanceDate.value);

    // Build rows from students
    const students: any[] = selectedClass.value.students || [];

    // Load existing attendance for this date/subject
    let existingMap = new Map<number, any>();
    if (attendanceSubject.value) {
      const existing = await AttendanceService.getClassAttendances(selectedClass.value.id, {
        start_date: dateStr,
        end_date: dateStr,
        subject_id: attendanceSubject.value.id,
      } as any);
      existing.forEach((rec) => existingMap.set(rec.student_id, rec));
    }

    attendanceRows.value = students.map((student) => {
      const existing = existingMap.get(student.id);
      return {
        student,
        status: existing ? existing.status : 'present',
        reason: existing ? (existing.reason || '') : '',
        existingId: existing ? existing.id : null,
      };
    });
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_attendance_error'), life: 3000 });
  } finally {
    attendanceLoading.value = false;
  }
};

const onAttendanceDateChange = async () => {
  await loadAttendanceRows();
};

const onAttendanceSubjectChange = async () => {
  await loadAttendanceRows();
};

const saveAttendance = async () => {
  if (!teacherId.value) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'Teacher ID not found', life: 3000 });
    return;
  }

  savingAttendance.value = true;
  try {
    const dateStr = formatDate(attendanceDate.value);

    // Build existing map from current rows
    const existingMap = new Map<number, any>();
    attendanceRows.value.forEach((row) => {
      if (row.existingId) {
        existingMap.set(row.student.id, { id: row.existingId, student_id: row.student.id } as any);
      }
    });

    const entries = attendanceRows.value.map((row) => ({
      student_id: row.student.id,
      subject_id: attendanceSubject.value?.id ?? null,
      teacher_id: teacherId.value!,
      status: row.status as any,
      reason: row.reason || null,
      date: dateStr,
    }));

    await AttendanceService.saveClassAttendance(entries, existingMap);

    toast.add({ severity: 'success', summary: 'Saved', detail: 'Attendance saved successfully', life: 3000 });
    attendanceDialog.value = false;
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err?.response?.data?.message || t('teacher_portal.save_attendance_error'), life: 4000 });
  } finally {
    savingAttendance.value = false;
  }
};

// ─── Grades Dialog ─────────────────────────────────────────────────────────────
const openGrades = (cls: any) => {
  gradeClass.value = cls;

  // Auto-select subject if only one
  if (cls.subjects?.length === 1) {
    gradeSubject.value = cls.subjects[0];
  } else {
    gradeSubject.value = null;
  }

  gradeExamType.value = null;
  gradeSemester.value = null;
  gradeMaxGrade.value = 20;

  gradeRows.value = (cls.students || []).map((student: any) => ({
    student,
    grade: null,
    comment: '',
  }));

  gradesDialog.value = true;
};

const saveGrades = async () => {
  if (!gradeSubject.value) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select a subject', life: 3000 });
    return;
  }
  if (!gradeExamType.value) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select an exam type', life: 3000 });
    return;
  }
  if (!gradeSemester.value) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select a semester', life: 3000 });
    return;
  }
  if (!teacherId.value) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'Teacher ID not found', life: 3000 });
    return;
  }

  const filled = gradeRows.value.filter((r) => r.grade !== null && r.grade !== undefined);
  if (!filled.length) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please enter at least one grade', life: 3000 });
    return;
  }

  savingGrades.value = true;
  try {
    const grades = filled.map((row) => ({
      student_id: row.student.id,
      subject_id: gradeSubject.value.id,
      teacher_id: teacherId.value!,
      exam_type: gradeExamType.value!,
      grade: row.grade!,
      max_grade: gradeMaxGrade.value,
      semester: gradeSemester.value!,
      academic_year: gradeClass.value?.academic_year || currentAcademicYear.value,
      comment: row.comment || null,
    }));

    const result = await GradeService.bulkCreateGrades(grades);
    const created = (result as any)?.created_count ?? filled.length;
    const failed = (result as any)?.failed_count ?? 0;

    if (failed > 0) {
      toast.add({ severity: 'warn', summary: 'Partial Success', detail: `${created} grades saved, ${failed} failed`, life: 4000 });
    } else {
      toast.add({ severity: 'success', summary: 'Saved', detail: `${created} grades saved successfully`, life: 3000 });
    }

    gradesDialog.value = false;
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err?.response?.data?.message || t('teacher_portal.save_grades_error'), life: 4000 });
  } finally {
    savingGrades.value = false;
  }
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatDate = (date: Date): string => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

const getSubjectColor = (index: number | string) => {
  const colors = ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'];
  const numIndex = typeof index === 'string' ? parseInt(index, 10) : index;
  return colors[numIndex % colors.length];
};
</script>

<template>
  <div class="grid">
    <Toast />

    <!-- Page Header -->
    <div class="col-12">
      <div class="card flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h2 class="text-2xl font-bold m-0">My Classes</h2>
          <p class="text-surface-500 mt-1 mb-0">Manage attendance and grades for your classes</p>
        </div>
        <Button label="My Schedule" icon="pi pi-calendar" outlined @click="openSchedule" />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="col-12 flex justify-content-center align-items-center" style="min-height: 300px">
      <div class="text-center">
        <i class="pi pi-spin pi-spinner text-4xl text-primary mb-3" style="display:block"></i>
        <p class="text-surface-500">Loading your classes...</p>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!myClasses.length" class="col-12">
      <div class="card text-center py-6">
        <i class="pi pi-inbox text-5xl text-surface-300 mb-4" style="display:block"></i>
        <h3 class="text-surface-500">No classes assigned</h3>
        <p class="text-surface-400">You have no classes assigned yet. Contact an admin to set up your classes.</p>
      </div>
    </div>

    <!-- Class Cards -->
    <div v-else v-for="cls in myClasses" :key="cls.id" class="col-12 md:col-6 lg:col-4">
      <div class="card h-full flex flex-column gap-3">
        <!-- Class Header -->
        <div class="flex align-items-start justify-content-between">
          <div>
            <h3 class="text-xl font-bold m-0">{{ cls.name }}</h3>
            <span class="text-surface-500 text-sm">Level {{ cls.level }}</span>
          </div>
          <Tag :value="cls.academic_year || 'N/A'" severity="info" />
        </div>

        <!-- Stats Row -->
        <div class="flex gap-4">
          <div class="flex align-items-center gap-2">
            <i class="pi pi-users text-primary"></i>
            <span class="font-semibold">{{ cls.students_count ?? cls.students?.length ?? 0 }}</span>
            <span class="text-surface-500 text-sm">Students</span>
          </div>
          <div class="flex align-items-center gap-2">
            <i class="pi pi-book text-primary"></i>
            <span class="font-semibold">{{ cls.subjects?.length || 0 }}</span>
            <span class="text-surface-500 text-sm">Subjects</span>
          </div>
        </div>

        <!-- Subject Tags -->
        <div class="flex flex-wrap gap-1">
          <span
            v-for="(sub, idx) in cls.subjects"
            :key="sub.id"
            class="text-xs px-2 py-1 border-round-xl text-white font-medium"
            :style="{ backgroundColor: getSubjectColor(idx) }"
          >
            {{ sub.name }}
          </span>
          <span v-if="!cls.subjects?.length" class="text-surface-400 text-sm">No subjects assigned</span>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2 mt-auto">
          <Button
            label="Attendance"
            icon="pi pi-check-square"
            class="flex-1"
            size="small"
            @click="openAttendance(cls)"
          />
          <Button
            label="Grades"
            icon="pi pi-star"
            severity="secondary"
            class="flex-1"
            size="small"
            @click="openGrades(cls)"
          />
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Schedule Dialog                                                -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Dialog
      v-model:visible="scheduleDialog"
      header="My Weekly Schedule"
      :style="{ width: '90vw', maxWidth: '1200px' }"
      modal
      :draggable="false"
    >
      <div v-if="scheduleLoading" class="flex justify-content-center py-5">
        <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full border-collapse" style="min-width: 700px">
          <thead>
            <tr>
              <th class="p-2 border-1 surface-border text-left text-surface-500 text-sm" style="width: 100px">Time</th>
              <th
                v-for="day in weekDays"
                :key="day"
                class="p-2 border-1 surface-border text-center font-semibold"
              >
                {{ day }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in schoolHours" :key="slot.hour">
              <td class="p-2 border-1 surface-border text-xs text-surface-500 font-medium">{{ slot.label }}</td>
              <td
                v-for="day in weekDays"
                :key="day"
                class="p-1 border-1 surface-border"
                style="height: 56px; vertical-align: top"
              >
                <div
                  v-for="entry in getScheduleForSlot(day, slot.hour)"
                  :key="entry.id"
                  class="text-xs p-1 border-round mb-1 text-white"
                  style="background: linear-gradient(135deg, #6366f1, #8b5cf6)"
                >
                  <div class="font-semibold">
                    {{ entry.assignment?.subject?.name || entry.subject?.name || 'Subject' }}
                  </div>
                  <div class="opacity-80">
                    {{ entry.assignment?.class?.name || entry.class?.name || '' }}
                  </div>
                  <div v-if="entry.room" class="opacity-70">Room {{ entry.room }}</div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!Object.keys(teacherSchedules).length" class="text-center text-surface-400 mt-4">
          No schedule entries found.
        </p>
      </div>
    </Dialog>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Attendance Dialog                                              -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Dialog
      v-model:visible="attendanceDialog"
      :header="`Attendance — ${selectedClass?.name ?? ''}`"
      :style="{ width: '85vw', maxWidth: '1000px' }"
      modal
      :draggable="false"
    >
      <!-- Filters Row -->
      <div class="grid mb-4">
        <!-- Subject Select -->
        <div class="col-12 md:col-6">
          <label class="block mb-1 font-medium text-sm">Subject</label>
          <Select
            v-model="attendanceSubject"
            :options="attendanceSubjectOptions"
            optionLabel="name"
            placeholder="Select subject"
            class="w-full"
            :disabled="attendanceSubjectOptions.length <= 1"
            @change="onAttendanceSubjectChange"
          />
          <small v-if="attendanceSubjectOptions.length === 0" class="text-orange-500">
            No subjects assigned to you for this class.
          </small>
        </div>

        <!-- Date Picker -->
        <div class="col-12 md:col-6">
          <label class="block mb-1 font-medium text-sm">Date</label>
          <DatePicker
            v-model="attendanceDate"
            dateFormat="yy-mm-dd"
            :maxDate="new Date()"
            showIcon
            class="w-full"
            @date-select="onAttendanceDateChange"
          />
        </div>
      </div>

      <!-- Loading -->
      <div v-if="attendanceLoading" class="flex justify-content-center py-4">
        <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
      </div>

      <!-- Student Attendance Table -->
      <DataTable
        v-else
        :value="attendanceRows"
        stripedRows
        scrollable
        scrollHeight="400px"
        size="small"
      >
        <Column header="#" style="width: 50px">
          <template #body="{ index }">
            <span class="text-surface-500">{{ index + 1 }}</span>
          </template>
        </Column>

        <Column header="Student">
          <template #body="{ data }">
            <div>
              <div class="font-medium">{{ data.student.first_name }} {{ data.student.last_name }}</div>
              <div class="text-xs text-surface-400">{{ data.student.code }}</div>
            </div>
          </template>
        </Column>

        <Column header="Status" style="width: 180px">
          <template #body="{ data }">
            <div class="flex align-items-center gap-2">
              <Tag
                :value="data.status"
                :severity="statusSeverity[data.status] ?? 'secondary'"
                class="capitalize text-xs"
                style="min-width: 70px; justify-content: center"
              />
              <Select
                v-model="data.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                class="flex-1"
                size="small"
              />
            </div>
          </template>
        </Column>

        <Column header="Reason (optional)" style="width: 220px">
          <template #body="{ data }">
            <InputText
              v-model="data.reason"
              placeholder="Reason..."
              class="w-full"
              size="small"
              :disabled="data.status === 'present'"
            />
          </template>
        </Column>
      </DataTable>

      <div v-if="!attendanceLoading && !attendanceRows.length" class="text-center text-surface-400 py-4">
        No students found in this class.
      </div>

      <!-- Footer -->
      <template #footer>
        <div class="flex justify-content-between align-items-center">
          <div class="text-sm text-surface-400">
            {{ attendanceRows.filter(r => r.status !== 'present').length }} absent/late/excused
            out of {{ attendanceRows.length }}
          </div>
          <div class="flex gap-2">
            <Button label="Cancel" severity="secondary" outlined @click="attendanceDialog = false" />
            <Button
              label="Save Attendance"
              icon="pi pi-save"
              :loading="savingAttendance"
              :disabled="!attendanceRows.length"
              @click="saveAttendance"
            />
          </div>
        </div>
      </template>
    </Dialog>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Grades Dialog                                                  -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Dialog
      v-model:visible="gradesDialog"
      :header="`Add Grades — ${gradeClass?.name ?? ''}`"
      :style="{ width: '85vw', maxWidth: '1000px' }"
      modal
      :draggable="false"
    >
      <!-- Config Row -->
      <div class="grid mb-4">
        <!-- Subject -->
        <div class="col-12 md:col-6 lg:col-3">
          <label class="block mb-1 font-medium text-sm">Subject <span class="text-red-400">*</span></label>
          <Select
            v-model="gradeSubject"
            :options="gradeSubjectOptions"
            optionLabel="name"
            placeholder="Select subject"
            class="w-full"
            :disabled="gradeSubjectOptions.length <= 1"
          />
        </div>

        <!-- Exam Type -->
        <div class="col-12 md:col-6 lg:col-3">
          <label class="block mb-1 font-medium text-sm">Exam Type <span class="text-red-400">*</span></label>
          <Select
            v-model="gradeExamType"
            :options="examTypeOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Select type"
            class="w-full"
          />
        </div>

        <!-- Semester -->
        <div class="col-12 md:col-6 lg:col-3">
          <label class="block mb-1 font-medium text-sm">Semester <span class="text-red-400">*</span></label>
          <Select
            v-model="gradeSemester"
            :options="semesterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Select semester"
            class="w-full"
          />
        </div>

        <!-- Max Grade -->
        <div class="col-12 md:col-6 lg:col-3">
          <label class="block mb-1 font-medium text-sm">Max Grade</label>
          <InputNumber
            v-model="gradeMaxGrade"
            :min="1"
            :max="100"
            class="w-full"
            inputClass="w-full"
          />
        </div>
      </div>

      <!-- Academic Year info -->
      <div class="mb-3 text-sm text-surface-500">
        <i class="pi pi-info-circle mr-1"></i>
        Academic Year:
        <strong>{{ gradeClass?.academic_year || currentAcademicYear }}</strong>
      </div>

      <!-- Students Table -->
      <DataTable
        :value="gradeRows"
        stripedRows
        scrollable
        scrollHeight="400px"
        size="small"
      >
        <Column header="#" style="width: 50px">
          <template #body="{ index }">
            <span class="text-surface-500">{{ index + 1 }}</span>
          </template>
        </Column>

        <Column header="Student">
          <template #body="{ data }">
            <div>
              <div class="font-medium">{{ data.student.first_name }} {{ data.student.last_name }}</div>
              <div class="text-xs text-surface-400">{{ data.student.code }}</div>
            </div>
          </template>
        </Column>

        <Column :header="`Grade (/ ${gradeMaxGrade})`" style="width: 160px">
          <template #body="{ data }">
            <InputNumber
              v-model="data.grade"
              :min="0"
              :max="gradeMaxGrade"
              :maxFractionDigits="2"
              placeholder="—"
              class="w-full"
              inputClass="w-full"
              size="small"
            />
          </template>
        </Column>

        <Column header="%" style="width: 70px">
          <template #body="{ data }">
            <span
              v-if="data.grade !== null && data.grade !== undefined"
              :class="[(data.grade / gradeMaxGrade) * 100 >= 50 ? 'text-green-500' : 'text-red-400', 'font-medium text-sm']"
            >
              {{ Math.round((data.grade / gradeMaxGrade) * 100) }}%
            </span>
            <span v-else class="text-surface-300">—</span>
          </template>
        </Column>

        <Column header="Comment (optional)">
          <template #body="{ data }">
            <InputText
              v-model="data.comment"
              placeholder="Optional comment..."
              class="w-full"
              size="small"
            />
          </template>
        </Column>
      </DataTable>

      <div v-if="!gradeRows.length" class="text-center text-surface-400 py-4">
        No students found in this class.
      </div>

      <!-- Footer -->
      <template #footer>
        <div class="flex justify-content-between align-items-center">
          <div class="text-sm text-surface-400">
            {{ gradeRows.filter(r => r.grade !== null).length }} / {{ gradeRows.length }} grades entered
          </div>
          <div class="flex gap-2">
            <Button label="Cancel" severity="secondary" outlined @click="gradesDialog = false" />
            <Button
              label="Save Grades"
              icon="pi pi-save"
              :loading="savingGrades"
              :disabled="!gradeRows.filter(r => r.grade !== null).length"
              @click="saveGrades"
            />
          </div>
        </div>
      </template>
    </Dialog>
  </div>
</template>
