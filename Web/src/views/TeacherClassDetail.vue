<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import apiService from '@/service/ApiService';
import ApiService from '@/service/ApiService';
import AttendanceService, { type AttendanceRecord } from '@/service/AttendanceService';
import GradeService, { type GradeRecord } from '@/service/GradeService';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { t } = useI18n();

const classId = computed(() => Number(route.params.classId));
const activeTab = ref<string>('1');

// Session mode — set when navigating from "Today's Sessions"
const sessionMode = computed(() => !!route.query.scheduleId);
const sessionScheduleId = computed(() => route.query.scheduleId ? Number(route.query.scheduleId) : null);
const sessionSubjectId = computed(() => route.query.subjectId ? Number(route.query.subjectId) : null);
const sessionDate = computed(() => route.query.date as string | undefined);

const classData = ref<any>(null);
const classLoading = ref(false);

const students = computed<any[]>(() => classData.value?.students ?? []);
const subjects = computed<any[]>(() => classData.value?.subjects ?? []);

const currentUser = computed(() => apiService.getUser());
const teacher = computed(() => currentUser.value?.teacher ?? null);
const teacherId = computed<number>(() => teacher.value?.id ?? 0);

// ─── Attendance state ─────────────────────────────────────
const attDateObj = ref<Date>(new Date());
const attSubjectId = ref<number | null>(null);
const attLoading = ref(false);
const attSaving = ref(false);
const existingAttMap = ref<Map<number, AttendanceRecord>>(new Map());
const attStatuses = ref<Record<number, 'present' | 'absent' | 'late' | 'excused'>>({});

const attDateStr = computed(() => {
  const d = attDateObj.value;
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

// In session mode, the session info comes from the URL (passed from portal)
const sessionInfo = computed(() => {
  if (!sessionMode.value) return null;
  const subject = subjects.value.find((s: any) => s.id === sessionSubjectId.value);
  return {
    subjectName: subject?.name ?? 'Session',
    date: sessionDate.value ?? attDateStr.value,
  };
});

const ATTENDANCE_OPTIONS = [
  { label: 'Present', value: 'present', severity: 'success', icon: 'pi pi-check' },
  { label: 'Absent', value: 'absent', severity: 'danger', icon: 'pi pi-times' },
  { label: 'Late', value: 'late', severity: 'warn', icon: 'pi pi-clock' },
  { label: 'Excused', value: 'excused', severity: 'info', icon: 'pi pi-info-circle' },
];

// ─── Grades state ─────────────────────────────────────────
const gradeSubjectId = ref<number | null>(null);
const gradeSemester = ref<string>('Trimester 1');
const gradeAcademicYear = ref<string>(computeAcademicYear());
const availableExams = ref<any[]>([]);
const gradeSelectedExam = ref<any>(null);
const gradeLoading = ref(false);
const gradeSaving = ref(false);

const existingGradeMap = ref<Map<number, GradeRecord>>(new Map());
// Nested dictionary: exerciseValues.value[student_id][exercise_id] = input_string
const exerciseValues = ref<Record<number, Record<number, number | null>>>({});

const EXAM_TYPES = [
  { label: 'Évaluation Continue', value: 'evaluation_continue' },
  { label: 'Devoir', value: 'devoir' },
  { label: 'Composition', value: 'composition' },
];

const SEMESTERS = [
  { label: 'Trimester 1', value: 'Trimester 1' },
  { label: 'Trimester 2', value: 'Trimester 2' },
  { label: 'Trimester 3', value: 'Trimester 3' },
];

function computeAcademicYear(): string {
  const now = new Date();
  const year = now.getFullYear();
  return now.getMonth() >= 8 ? `${year}-${year + 1}` : `${year - 1}-${year}`;
}

// ─── Load class ───────────────────────────────────────────
async function loadClass() {
  classLoading.value = true;
  try {
    const response = await ApiService.get<any[]>('/teacher/classes');
    const raw: any[] = (response.data as any)?.data ?? response.data ?? [];
    const list = Array.isArray(raw) ? raw : Object.values(raw);
    classData.value = list.find((c: any) => Number(c.id) === classId.value) ?? null;

    if (classData.value && subjects.value.length >= 1) {
      gradeSubjectId.value = subjects.value[0].id;
    }
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_class_error'), life: 3000 });
  } finally {
    classLoading.value = false;
  }
}

function initAttStatuses() {
  attStatuses.value = {};
}

// ─── Attendance ───────────────────────────────────────────
async function loadAttendance() {
  if (!attSubjectId.value) return;
  attLoading.value = true;
  try {
    // One call: fetch all records for this class from the start of time up to
    // today. The broad start_date guarantees the backend returns historical data.
    const allRecords = await AttendanceService.getClassAttendances(classId.value, {
      start_date: '2000-01-01',
      end_date: attDateStr.value,
    });

    // ── 1. Isolate records that belong to this exact session ──────────────
    const sessionRecords = sessionScheduleId.value
      ? allRecords.filter(r => r.date === attDateStr.value && r.schedule_id === sessionScheduleId.value)
      : allRecords.filter(r => r.date === attDateStr.value && r.subject_id === attSubjectId.value);

    const map = new Map<number, AttendanceRecord>();
    for (const r of sessionRecords) map.set(r.student_id, r);
    existingAttMap.value = map;

    initAttStatuses();
    for (const [sid, rec] of map) {
      attStatuses.value[sid] = rec.status;
    }

    // ── 2. For students with no record yet, pre-fill from last known status ─
    const studentsWithoutRecord = students.value.filter((s: any) => !map.has(s.id));

    if (studentsWithoutRecord.length > 0) {
      // Build the most-recent-status map, excluding only the current session's
      // records. Other sessions from today are valid "last known" sources.
      const lastMap = new Map<number, { date: string; id: number; status: 'present' | 'absent' | 'late' | 'excused' }>();
      for (const rec of allRecords) {
        const isCurrentSession = sessionScheduleId.value
          ? rec.date === attDateStr.value && rec.schedule_id === sessionScheduleId.value
          : rec.date === attDateStr.value && rec.subject_id === attSubjectId.value;
        if (isCurrentSession) continue;

        const existing = lastMap.get(rec.student_id);
        // Keep most recent by date; use id as tiebreaker for same-day records
        if (!existing || rec.date > existing.date || (rec.date === existing.date && rec.id > existing.id)) {
          lastMap.set(rec.student_id, { date: rec.date, id: rec.id, status: rec.status });
        }
      }
      for (const s of studentsWithoutRecord) {
        const last = lastMap.get(s.id);
        attStatuses.value[s.id] = last ? last.status : 'present';
      }
    }
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_attendance_error'), life: 3000 });
  } finally {
    attLoading.value = false;
  }
}

async function saveAttendance() {
  if (!attSubjectId.value) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'Please select a subject', life: 3000 });
    return;
  }
  attSaving.value = true;
  try {
    const entries = students.value.map((s: any) => ({
      student_id: s.id,
      subject_id: attSubjectId.value as number,
      teacher_id: teacherId.value,
      schedule_id: sessionScheduleId.value ?? null,
      date: attDateStr.value,
      status: attStatuses.value[s.id] ?? 'present',
      reason: null,
    }));
    await AttendanceService.saveClassAttendance(entries, existingAttMap.value);
    toast.add({ severity: 'success', summary: 'Saved', detail: 'Attendance saved', life: 3000 });
    await loadAttendance();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.save_attendance_error'), life: 3000 });
  } finally {
    attSaving.value = false;
  }
}

function markAll(status: 'present' | 'absent' | 'late') {
  for (const s of students.value) attStatuses.value[s.id] = status;
}

const presentCount = computed(() => Object.values(attStatuses.value).filter(v => v === 'present').length);
const absentCount = computed(() => Object.values(attStatuses.value).filter(v => v === 'absent').length);
const lateCount = computed(() => Object.values(attStatuses.value).filter(v => v === 'late').length);

// ─── Grades ───────────────────────────────────────────────
async function fetchExams() {
  if (!gradeSubjectId.value) return;
  availableExams.value = [];
  gradeSelectedExam.value = null;
  exerciseValues.value = {};
  existingGradeMap.value = new Map();
  try {
    const data = await GradeService.getExams({
      class_id: classId.value,
      subject_id: gradeSubjectId.value,
      semester: gradeSemester.value,
      academic_year: gradeAcademicYear.value,
    });
    availableExams.value = data || [];
    if (availableExams.value.length > 0) {
      gradeSelectedExam.value = availableExams.value[0];
    }
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: 'Failed to fetch exams', life: 3000 });
  }
}

async function loadGrades() {
  if (!gradeSubjectId.value || !gradeSelectedExam.value) return;
  gradeLoading.value = true;
  try {
    let records: any = await GradeService.getClassGrades(classId.value, {
      subject_id: gradeSubjectId.value,
      semester: gradeSemester.value,
      academic_year: gradeAcademicYear.value,
    });

    // Normalize possible wrapper shapes:
    // - API may return { success: true, data: [...] }
    // - Or { success: true, data: { grades: [...] } }
    // - Or { grades: [...] }
    if (!records) records = [];
    if (records && records.data) {
      if (Array.isArray(records.data)) records = records.data;
      else if (records.data.grades && Array.isArray(records.data.grades)) records = records.data.grades;
    } else if (records && records.grades && Array.isArray(records.grades)) {
      records = records.grades;
    }
    const map = new Map<number, GradeRecord>();
    for (const r of (records as GradeRecord[])) {
      const recExamId = (r as any).exam_id ?? (r as any).exam?.id ?? null;
      const studentIdKey = (r as any).student_id ?? (r as any).student?.id ?? null;
      if (Number(recExamId) == Number(gradeSelectedExam.value.id) && studentIdKey !== null) {
        map.set(Number(studentIdKey), r);
      }
    }
    existingGradeMap.value = map;

    // Initialize exerciseValues
    const newVals: Record<number, Record<number, number | null>> = {};
    for (const student of students.value) {
      newVals[student.id] = {};
      const exercises = gradeSelectedExam.value.exercises || [];
      const record = map.get(Number(student.id));
      
      for (const ex of exercises) {
        // Default to 0 as requested if no grade exists
        newVals[student.id][ex.id] = 0;
        
        if (record && record.exercise_grades) {
          const exGrade = (record.exercise_grades || []).find((eg: any) => 
            Number(eg.exam_exercise_id ?? eg.exam_exercise?.id) == Number(ex.id)
          );
          if (exGrade) {
            newVals[student.id][ex.id] = exGrade.note !== null && exGrade.note !== undefined ? Number(exGrade.note) : 0;
          }
        }
      }
    }
    exerciseValues.value = newVals;
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_grades_error'), life: 3000 });
  } finally {
    gradeLoading.value = false;
  }
}

function calculateStudentTotal(studentId: number): number {
  if (!gradeSelectedExam.value || Object.keys(exerciseValues.value).length === 0) return 0;
  const values = exerciseValues.value[studentId] || {};
  let total = 0;
  for (const val of Object.values(values)) {
    const num = parseFloat(String(val));
    if (!isNaN(num)) total += num;
  }
  return total;
}

function clampExerciseValue(studentId: number, exerciseId: number, maxNote: any) {
  if (!exerciseValues.value[studentId]) return;
  const raw = exerciseValues.value[studentId][exerciseId];
  if (raw === null || raw === undefined) return;
  const num = parseFloat(String(raw));
  const max = Number(maxNote);

  if (isNaN(num)) {
    exerciseValues.value[studentId][exerciseId] = 0;
    return;
  }

  if (num < 0) {
    exerciseValues.value[studentId][exerciseId] = 0;
  } else if (!isNaN(max) && num > max) {
    exerciseValues.value[studentId][exerciseId] = max;
    toast.add({ severity: 'warn', summary: 'Warning', detail: `Value cannot exceed ${max}`, life: 3000 });
  }
}

async function saveGrades() {
  if (!gradeSelectedExam.value) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'Please select an exam first', life: 3000 });
    return;
  }
  const allGrades: any[] = [];

  for (const student of students.value) {
    const exercises = gradeSelectedExam.value.exercises || [];
    const exGrades: any[] = [];
    let hasAnyValue = false;
    let totalGrade = 0;

    for (const ex of exercises) {
      const raw = exerciseValues.value[student.id]?.[ex.id];
      if (raw !== null && raw !== undefined) {
        const num = parseFloat(String(raw));
        if (!isNaN(num)) {
          exGrades.push({ exam_exercise_id: ex.id, note: num });
          totalGrade += num;
          hasAnyValue = true;
        }
      }
    }

    if (!hasAnyValue) continue;

    const existing = existingGradeMap.value.get(Number(student.id));

    const payload: any = {
      student_id: student.id,
      exam_id: gradeSelectedExam.value.id,
      grade: totalGrade,
      exercise_grades: exGrades,
    };

    if (existing) {
      payload.id = existing.id;
    }

    // Validation: each exercise note must not exceed its max_note
    for (const ex of exercises) {
      const entry = exGrades.find(e => e.exam_exercise_id === ex.id);
      if (entry && typeof ex.max_note === 'number' && entry.note > ex.max_note) {
        toast.add({ severity: 'warn', summary: 'Invalid value', detail: `${student.first_name} ${student.last_name}: ${ex.level_name} cannot exceed ${ex.max_note}`, life: 4000 });
        return;
      }
    }

    allGrades.push(payload);
  }

  if (allGrades.length === 0) {
    toast.add({ severity: 'info', summary: 'Nothing to save', detail: 'Enter at least one grade', life: 3000 });
    return;
  }

  gradeSaving.value = true;
  try {
    await GradeService.bulkCreateGrades(allGrades);
    toast.add({ severity: 'success', summary: 'Saved', detail: `${allGrades.length} student grade(s) saved`, life: 3000 });
    await loadGrades();
  } catch (err: any) {
    console.error('Save error:', err);
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.save_grades_error'), life: 3000 });
  } finally {
    gradeSaving.value = false;
  }
}

const gradeAverage = computed(() => {
  let sum = 0;
  let count = 0;
  for (const s of students.value) {
    const val = calculateStudentTotal(s.id);
    const hasAnyGrade = exerciseValues.value[s.id] && Object.values(exerciseValues.value[s.id]).some(v => v !== null && v !== undefined);
    if (hasAnyGrade) {
      sum += val;
      count++;
    }
  }
  if (count === 0) return null;
  return (sum / count).toFixed(1);
});

const gradeFilled = computed(() => {
  let filled = 0;
  for (const s of students.value) {
    const hasAnyGrade = exerciseValues.value[s.id] && Object.values(exerciseValues.value[s.id]).some(v => v !== null && v !== undefined);
    if (hasAnyGrade) filled++;
  }
  return filled;
});

// ─── Watchers ─────────────────────────────────────────────
watch([gradeSubjectId, gradeSemester, gradeAcademicYear], () => fetchExams());
watch(gradeSelectedExam, () => loadGrades());

onMounted(async () => {
  await loadClass();
  if (gradeSubjectId.value) await fetchExams();
});
</script>

<template>
  <div class="p-4 md:p-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-5">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push({ name: 'teacher-portal' })" />
      <div>
        <div class="text-xs text-surface-400 dark:text-surface-500 font-medium tracking-wide uppercase mb-0.5">My Classes</div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0 leading-tight">
          {{ classData?.name ?? 'Loading...' }}
        </h1>
      </div>
    </div>

    <!-- Class info chips -->
    <div v-if="classData" class="flex flex-wrap gap-3 mb-4">
      <span v-if="classData.level" class="inline-flex items-center gap-1.5 text-sm bg-surface-100 dark:bg-surface-700 text-surface-700 dark:text-surface-200 px-3 py-1 rounded-full">
        <i class="pi pi-tag text-xs"></i>{{ classData.level }}
      </span>
      <span v-if="classData.academic_year" class="inline-flex items-center gap-1.5 text-sm bg-surface-100 dark:bg-surface-700 text-surface-700 dark:text-surface-200 px-3 py-1 rounded-full">
        <i class="pi pi-calendar text-xs"></i>{{ classData.academic_year }}
      </span>
      <span class="inline-flex items-center gap-1.5 text-sm bg-surface-100 dark:bg-surface-700 text-surface-700 dark:text-surface-200 px-3 py-1 rounded-full">
        <i class="pi pi-users text-xs"></i>{{ students.length }} students
      </span>
      <span v-for="s in subjects" :key="s.id" class="inline-flex items-center gap-1.5 text-sm bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 px-3 py-1 rounded-full">
        <i class="pi pi-book text-xs"></i>{{ s.name }}
      </span>
    </div>

    <!-- Session banner (shown when navigated from Today's Sessions) -->
    <div v-if="sessionMode && sessionInfo" class="flex items-center gap-3 mb-5 p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-xl">
      <div class="bg-primary-500 text-white rounded-lg p-2">
        <i class="pi pi-clock text-sm"></i>
      </div>
      <div>
        <div class="text-xs text-primary-500 dark:text-primary-400 font-medium uppercase tracking-wide mb-0.5">Session</div>
        <div class="text-sm font-semibold text-primary-800 dark:text-primary-200">
          {{ sessionInfo.subjectName }} &mdash; {{ sessionInfo.date }}
        </div>
      </div>
      <span class="ml-auto text-xs text-primary-500 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-2 py-0.5 rounded-full">
        Locked
      </span>
    </div>

    <div v-if="classLoading" class="flex justify-center py-16">
      <ProgressSpinner style="width: 48px; height: 48px" />
    </div>

    <div v-else-if="!classData" class="text-center py-16 text-surface-400">
      <i class="pi pi-exclamation-triangle text-5xl mb-3"></i>
      <p class="text-lg">Class not found.</p>
    </div>

    <Tabs v-else v-model:value="activeTab">
      <TabList>
        <Tab v-if="false" value="0">
          <span class="flex items-center gap-2">
            <i class="pi pi-check-circle"></i>
            <span>Attendance</span>
          </span>
        </Tab>
        <Tab value="1">
          <span class="flex items-center gap-2">
            <i class="pi pi-star"></i>
            <span>Grades</span>
          </span>
        </Tab>
      </TabList>

      <TabPanels>
        <!-- ═══════════ ATTENDANCE ═══════════ -->
        <TabPanel v-if="false" value="0">
          <div class="pt-4">

            <!-- ── SESSION MODE: Marking form ────────────────────── -->
            <template v-if="sessionMode">
              <div class="flex flex-wrap gap-4 mb-5">
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Date</label>
                  <DatePicker v-model="attDateObj" show-icon :manual-input="false" class="w-full sm:w-48" :disabled="true" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Subject</label>
                  <Select
                    v-model="attSubjectId" :options="subjects" option-label="name" option-value="id"
                    placeholder="Select subject" class="w-full sm:w-56" :disabled="true"
                  />
                </div>
                <div class="flex items-end gap-2 flex-wrap">
                  <Button label="All Present" icon="pi pi-check" severity="success" outlined size="small" @click="markAll('present')" />
                  <Button label="All Absent"  icon="pi pi-times" severity="danger"  outlined size="small" @click="markAll('absent')" />
                  <Button label="All Late"    icon="pi pi-clock" severity="warn"    outlined size="small" @click="markAll('late')" />
                </div>
              </div>

              <div v-if="attLoading" class="flex justify-center py-8">
                <ProgressSpinner style="width: 36px; height: 36px" />
              </div>
              <div v-else-if="students.length === 0" class="text-center py-10 text-surface-400">
                <i class="pi pi-users text-4xl mb-2 block"></i>
                <p>No students in this class.</p>
              </div>
              <template v-else>
                <div class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-surface-50 dark:bg-surface-800/80">
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 w-10">#</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400">Student</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 hidden sm:table-cell">Code</th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(student, idx) in students" :key="student.id"
                        class="border-t border-surface-100 dark:border-surface-700 transition-colors"
                        :class="{
                          'bg-red-50/50 dark:bg-red-900/10':   attStatuses[student.id] === 'absent',
                          'bg-amber-50/50 dark:bg-amber-900/10': attStatuses[student.id] === 'late',
                          'bg-blue-50/50 dark:bg-blue-900/10': attStatuses[student.id] === 'excused',
                          'hover:bg-green-50/30 dark:hover:bg-green-900/5': attStatuses[student.id] === 'present',
                        }"
                      >
                        <td class="p-3 text-surface-400 text-xs">{{ idx + 1 }}</td>
                        <td class="p-3 font-medium text-surface-900 dark:text-surface-100">{{ student.first_name }} {{ student.last_name }}</td>
                        <td class="p-3 font-mono text-xs text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ student.code }}</td>
                        <td class="p-3">
                          
                        <div class="flex justify-center gap-1.5 flex-wrap">
                          <button
                            v-for="opt in ATTENDANCE_OPTIONS"
                            :key="opt.value"
                            v-show="opt.value !== 'excused' || attStatuses[student.id] === 'excused'"
                            class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all"
                            :class="attStatuses[student.id] === opt.value
                              ? opt.value === 'present'  ? 'bg-green-500 border-green-500 text-white'
                              : opt.value === 'absent'   ? 'bg-red-500 border-red-500 text-white'
                              : opt.value === 'late'     ? 'bg-amber-500 border-amber-500 text-white'
                              : opt.value === 'excused'  ? 'bg-blue-500 border-blue-500 text-white'
                              : ''
                              : 'bg-transparent border-surface-300 dark:border-surface-600 text-surface-600 dark:text-surface-300 hover:border-surface-400'"
                            @click="attStatuses[student.id] = opt.value as any"
                          >
                            <i :class="opt.icon" class="text-[10px]"></i>
                            {{ opt.label }}
                          </button>
                        </div>
                          
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 mt-4">
                  <div class="flex gap-4 text-sm">
                    <span class="flex items-center gap-1.5">
                      <span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>
                      <span class="text-surface-600 dark:text-surface-400">Present: <strong class="text-surface-800 dark:text-surface-200">{{ presentCount }}</strong></span>
                    </span>
                    <span class="flex items-center gap-1.5">
                      <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>
                      <span class="text-surface-600 dark:text-surface-400">Absent: <strong class="text-surface-800 dark:text-surface-200">{{ absentCount }}</strong></span>
                    </span>
                    <span class="flex items-center gap-1.5">
                      <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>
                      <span class="text-surface-600 dark:text-surface-400">Late: <strong class="text-surface-800 dark:text-surface-200">{{ lateCount }}</strong></span>
                    </span>
                  </div>
                  <Button label="Save Attendance" icon="pi pi-save" :loading="attSaving" @click="saveAttendance" />
                </div>
              </template>
            </template>

            <!-- ── VIEW MODE: Read-only history ──────────────────── -->
            <template v-else>
              <!-- filters -->
              <div class="flex flex-wrap gap-4 mb-5">
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Date</label>
                  <DatePicker v-model="attDateObj" show-icon :manual-input="false" class="w-full sm:w-48" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Subject</label>
                  <Select
                    v-model="attSubjectId" :options="subjects" option-label="name" option-value="id"
                    placeholder="All subjects" class="w-full sm:w-56"
                  />
                </div>
              </div>

              <div v-if="attLoading" class="flex justify-center py-8">
                <ProgressSpinner style="width: 36px; height: 36px" />
              </div>

              <div v-else-if="students.length === 0" class="text-center py-10 text-surface-400">
                <i class="pi pi-users text-4xl mb-2 block"></i>
                <p>No students in this class.</p>
              </div>

              <template v-else>
                <!-- summary bar -->
                <div class="flex flex-wrap gap-4 mb-4 p-3 bg-surface-50 dark:bg-surface-800/60 rounded-xl border border-surface-200 dark:border-surface-700 text-sm">
                  <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>
                    <span class="text-surface-600 dark:text-surface-400">Present: <strong class="text-surface-800 dark:text-surface-200">{{ presentCount }}</strong></span>
                  </span>
                  <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>
                    <span class="text-surface-600 dark:text-surface-400">Absent: <strong class="text-surface-800 dark:text-surface-200">{{ absentCount }}</strong></span>
                  </span>
                  <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>
                    <span class="text-surface-600 dark:text-surface-400">Late: <strong class="text-surface-800 dark:text-surface-200">{{ lateCount }}</strong></span>
                  </span>
                  <span class="ml-auto text-xs text-surface-400 dark:text-surface-500 italic flex items-center gap-1">
                    <i class="pi pi-eye text-[11px]"></i> Read-only — mark attendance from a session
                  </span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-surface-50 dark:bg-surface-800/80">
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 w-10">#</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400">Student</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 hidden sm:table-cell">Code</th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(student, idx) in students" :key="student.id"
                        class="border-t border-surface-100 dark:border-surface-700"
                      >
                        <td class="p-3 text-surface-400 text-xs">{{ idx + 1 }}</td>
                        <td class="p-3 font-medium text-surface-900 dark:text-surface-100">{{ student.first_name }} {{ student.last_name }}</td>
                        <td class="p-3 font-mono text-xs text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ student.code }}</td>
                        <td class="p-3 text-center">
                          <template v-if="existingAttMap.has(student.id)">
                            <span
                              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                              :class="{
                                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300': attStatuses[student.id] === 'present',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300':         attStatuses[student.id] === 'absent',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300': attStatuses[student.id] === 'late',
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300':     attStatuses[student.id] === 'excused',
                              }"
                            >
                              <i
                                class="text-[10px]"
                                :class="{
                                  'pi pi-check':        attStatuses[student.id] === 'present',
                                  'pi pi-times':        attStatuses[student.id] === 'absent',
                                  'pi pi-clock':        attStatuses[student.id] === 'late',
                                  'pi pi-info-circle':  attStatuses[student.id] === 'excused',
                                }"
                              ></i>
                              {{ attStatuses[student.id]?.charAt(0).toUpperCase() + attStatuses[student.id]?.slice(1) }}
                            </span>
                          </template>
                          <span v-else class="text-surface-300 dark:text-surface-600 text-xs">—</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- nudge to use sessions -->
                <div class="mt-4 flex items-center gap-3 p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-xl text-sm">
                  <i class="pi pi-arrow-left text-primary-500 dark:text-primary-400"></i>
                  <span class="text-primary-700 dark:text-primary-300">
                    To mark attendance, go back and tap a session from <strong>Today's Sessions</strong>.
                  </span>
                </div>
              </template>
            </template>

          </div>
        </TabPanel>

        <!-- ═══════════ GRADES ═══════════ -->
        <TabPanel value="1">
          <div class="pt-4">
            <!-- Controls -->
            <div class="flex flex-wrap gap-4 mb-5">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Subject</label>
                <Select
                  v-model="gradeSubjectId"
                  :options="subjects"
                  option-label="name"
                  option-value="id"
                  placeholder="Select subject"
                  class="w-full sm:w-56"
                  :disabled="subjects.length <= 1"
                />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Trimester</label>
                <Select v-model="gradeSemester" :options="SEMESTERS" option-label="label" option-value="value" class="w-full sm:w-44" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Exam</label>
                <Select v-model="gradeSelectedExam" :options="availableExams" option-label="exam_type" class="w-full sm:w-56" placeholder="Select Exam">
                  <template #value="slotProps">
                    <div v-if="slotProps.value" class="flex items-center">
                      <div>{{ slotProps.value.exam_type }} ({{ slotProps.value.max_grade }})</div>
                    </div>
                    <span v-else>
                      {{ slotProps.placeholder }}
                    </span>
                  </template>
                  <template #option="slotProps">
                    <div class="flex items-center gap-2">
                      <span class="font-medium">{{ slotProps.option.exam_type }}</span>
                      <span class="text-surface-400 text-sm">({{ slotProps.option.max_grade }})</span>
                    </div>
                  </template>
                </Select>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Academic Year</label>
                <InputText v-model="gradeAcademicYear" placeholder="e.g. 2024-2025" class="w-full sm:w-36" />
              </div>
            </div>

            <div v-if="gradeLoading" class="flex justify-center py-8">
              <ProgressSpinner style="width: 36px; height: 36px" />
            </div>

              <div v-if="!gradeSelectedExam" class="text-center py-10 text-surface-400">
                <i class="pi pi-file text-4xl mb-2 block"></i>
                <p>Please select an exam to grade.</p>
              </div>

              <div v-else-if="students.length === 0" class="text-center py-10 text-surface-400">
                <i class="pi pi-users text-4xl mb-2 block"></i>
                <p>No students in this class.</p>
              </div>

              <template v-else>
                <div class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-surface-50 dark:bg-surface-800/80">
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 w-10">#</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400">Student</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 hidden sm:table-cell">Code</th>
                        
                        <th v-for="ex in gradeSelectedExam.exercises" :key="ex.id" class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">
                          {{ ex.level_name || 'Ex' }} <span class="text-surface-400 font-normal">/ {{ ex.max_note }}</span>
                        </th>
                        
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400 border-l border-surface-200 dark:border-surface-700">
                          Total <span class="text-surface-400 font-normal">/ {{ gradeSelectedExam.max_grade }}</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(student, idx) in students"
                        :key="student.id"
                        class="border-t border-surface-100 dark:border-surface-700 hover:bg-surface-50/50 dark:hover:bg-surface-800/40 transition-colors"
                      >
                        <td class="p-3 text-surface-400 text-xs">{{ idx + 1 }}</td>
                        <td class="p-3">
                          <span class="font-medium text-surface-900 dark:text-surface-100">
                            {{ student.first_name }} {{ student.last_name }}
                          </span>
                          <span
                            v-if="existingGradeMap.has(Number(student.id))"
                            class="ml-2 text-[10px] bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-1.5 py-0.5 rounded-full align-middle"
                          >saved</span>
                        </td>
                        <td class="p-3 font-mono text-xs text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ student.code }}</td>
                        
                        <td v-for="ex in gradeSelectedExam.exercises" :key="ex.id" class="p-3">
                          <div class="flex justify-center">
                            <InputNumber
                              v-if="exerciseValues[student.id]"
                              v-model="exerciseValues[student.id][ex.id]"
                              :min="0"
                              :max="Number(ex.max_note)"
                              :show-buttons="false"
                              class="w-20 text-center text-sm"
                              @change="clampExerciseValue(student.id, ex.id, Number(ex.max_note))"
                            />
                          </div>
                        </td>
                        
                        <td class="p-3 text-center font-bold text-surface-700 dark:text-surface-200 border-l border-surface-100 dark:border-surface-700">
                          {{ calculateStudentTotal(student.id) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Summary + save -->
                <div class="flex flex-wrap items-center justify-between gap-4 mt-4">
                  <div class="flex gap-4 text-sm text-surface-500 dark:text-surface-400">
                    <span>
                      Class avg:
                      <strong class="text-surface-800 dark:text-surface-200">
                        {{ gradeAverage !== null ? `${gradeAverage} / ${gradeSelectedExam.max_grade}` : '—' }}
                      </strong>
                    </span>
                    <span>
                      Filled: <strong class="text-surface-800 dark:text-surface-200">{{ gradeFilled }} / {{ students.length }}</strong>
                    </span>
                  </div>
                  <Button label="Save Grades" icon="pi pi-save" :loading="gradeSaving" @click="saveGrades" />
                </div>
              </template>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
