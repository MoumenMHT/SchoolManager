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
    subjectName: subject?.name ?? t('teacher_class_detail.session'),
    date: sessionDate.value ?? attDateStr.value,
  };
});

const ATTENDANCE_OPTIONS = computed(() => [
  { label: t('common.present'), value: 'present', severity: 'success', icon: 'pi pi-check' },
  { label: t('common.absent'), value: 'absent', severity: 'danger', icon: 'pi pi-times' },
  { label: t('common.late'), value: 'late', severity: 'warn', icon: 'pi pi-clock' },
  { label: t('common.excused'), value: 'excused', severity: 'info', icon: 'pi pi-info-circle' },
]);

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

const EXAM_TYPES = computed(() => [
  { label: t('grade_analytics.eval_continue'), value: 'evaluation_continue' },
  { label: t('grade_analytics.devoir_label'), value: 'devoir' },
  { label: t('grade_analytics.composition_label'), value: 'composition' },
]);

const SEMESTERS = computed(() => [
  { label: t('grade_analytics.trimester_1'), value: 'Trimester 1' },
  { label: t('grade_analytics.trimester_2'), value: 'Trimester 2' },
  { label: t('grade_analytics.trimester_3'), value: 'Trimester 3' },
]);

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
    toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('teacher_class_detail.warn_select_subject'), life: 3000 });
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
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('teacher_class_detail.attendance_saved'), life: 3000 });
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
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_class_detail.fetch_exams_error'), life: 3000 });
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
    toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('teacher_class_detail.warn_max_exceeded', { max }), life: 3000 });
  }
}

async function saveGrades() {
  if (!gradeSelectedExam.value) {
    toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('teacher_class_detail.warn_select_exam'), life: 3000 });
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
        toast.add({ severity: 'warn', summary: t('common.validation_error'), detail: `${student.first_name} ${student.last_name}: ${ex.level_name} ${t('teacher_class_detail.cannot_exceed')} ${ex.max_note}`, life: 4000 });
        return;
      }
    }

    allGrades.push(payload);
  }

  if (allGrades.length === 0) {
    toast.add({ severity: 'info', summary: t('teacher_class_detail.nothing_to_save'), detail: t('teacher_class_detail.enter_one_grade'), life: 3000 });
    return;
  }

  gradeSaving.value = true;
  try {
    await GradeService.bulkCreateGrades(allGrades);
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('teacher_class_detail.grades_saved_count', { count: allGrades.length }), life: 3000 });
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
  <div class="p-4 md:p-8 max-w-[1600px] mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center gap-6 mb-8 animate-fade-in">
      <Button icon="pi pi-arrow-left" text raised class="rounded-2xl w-12 h-12 bg-white dark:bg-surface-800 shadow-sm" @click="router.push({ name: 'teacher-portal' })" />
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-[10px] font-black uppercase tracking-[0.2em] text-surface-400 dark:text-surface-500">{{ $t('teacher_class_detail.my_classes') }}</span>
          <span v-if="classData?.academic_year" class="px-2 py-0.5 rounded-md bg-surface-100 dark:bg-surface-800 text-surface-500 text-[10px] font-bold">{{ classData.academic_year }}</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-black text-surface-900 dark:text-surface-0 tracking-tight">
          {{ classData?.name ?? $t('teacher_class_detail.loading') }}
        </h1>
      </div>

      <!-- Class info chips -->
      <div v-if="classData" class="flex flex-wrap gap-2 md:justify-end">
        <div class="glass-panel px-4 py-2 rounded-2xl flex items-center gap-2 shadow-sm">
          <i class="pi pi-tag text-primary-500"></i>
          <span class="text-sm font-black text-surface-700 dark:text-surface-200 uppercase tracking-tighter">{{ t('levels.' + (classData.level?.toLowerCase() || 'default')) }}</span>
        </div>
        <div class="glass-panel px-4 py-2 rounded-2xl flex items-center gap-2 shadow-sm">
          <i class="pi pi-users text-green-500"></i>
          <span class="text-sm font-black text-surface-700 dark:text-surface-200">{{ students.length }} <span class="text-[10px] text-surface-400 uppercase ml-0.5 tracking-widest">{{ $t('teacher_portal.students') }}</span></span>
        </div>
      </div>
    </div>

    <!-- Session banner (shown when navigated from Today's Sessions) -->
    <div v-if="sessionMode && sessionInfo" class="flex items-center gap-4 mb-8 p-6 bg-gradient-to-r from-primary-600 to-primary-400 dark:from-primary-700 dark:to-primary-900/60 rounded-3xl shadow-xl shadow-primary-500/20 text-white animate-fade-in border border-primary-500/20">
      <div class="bg-white/20 backdrop-blur-md rounded-2xl p-4">
        <i class="pi pi-clock text-2xl"></i>
      </div>
      <div class="flex-1">
        <div class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-100 mb-1">{{ $t('teacher_class_detail.session') }}</div>
        <div class="text-xl font-black">
          {{ sessionInfo.subjectName }} <span class="mx-2 opacity-50">|</span> {{ sessionInfo.date }}
        </div>
      </div>
      <div class="hidden sm:flex px-4 py-2 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 items-center gap-2">
        <i class="pi pi-lock text-xs"></i>
        <span class="text-xs font-black uppercase tracking-widest">{{ $t('teacher_class_detail.locked') }}</span>
      </div>
    </div>

    <div v-if="classLoading" class="flex justify-center py-24">
      <ProgressSpinner style="width: 60px; height: 60px" />
    </div>

    <div v-else-if="!classData" class="flex flex-col items-center justify-center py-24 text-surface-400 bg-surface-50 dark:bg-surface-800/40 rounded-[2.5rem] border-2 border-dashed border-surface-200 dark:border-surface-700">
      <i class="pi pi-exclamation-triangle text-6xl mb-6 opacity-20"></i>
      <p class="text-xl font-black tracking-tight">{{ $t('teacher_class_detail.class_not_found') }}</p>
    </div>

    <Tabs v-else v-model:value="activeTab" class="premium-tabs">
      <TabList class="gap-4 border-none mb-8">
        <Tab v-if="false" value="0" class="rounded-2xl px-6 py-3 font-black uppercase text-xs tracking-widest transition-premium">
          <div class="flex items-center gap-3">
            <i class="pi pi-check-circle text-lg"></i>
            <span>{{ $t('teacher_class_detail.attendance_tab') }}</span>
          </div>
        </Tab>
        <Tab value="1" class="rounded-2xl px-6 py-3 font-black uppercase text-xs tracking-widest transition-premium">
          <div class="flex items-center gap-3">
            <i class="pi pi-star text-lg"></i>
            <span>{{ $t('teacher_class_detail.grades_tab') }}</span>
          </div>
        </Tab>
      </TabList>

      <TabPanels class="bg-transparent p-0">
        <!-- ═══════════ GRADES ═══════════ -->
        <TabPanel value="1">
          <div class="animate-fade-in">
            <!-- Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
              <div class="flex flex-col gap-2">
                <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_class_detail.subject_label') }}</label>
                <Select
                  v-model="gradeSubjectId"
                  :options="subjects"
                  option-label="name"
                  option-value="id"
                  class="w-full premium-select"
                  :disabled="subjects.length <= 1"
                />
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_class_detail.trimester_label') }}</label>
                <Select v-model="gradeSemester" :options="SEMESTERS" option-label="label" option-value="value" class="w-full premium-select" />
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_class_detail.exam_label') }}</label>
                <Select v-model="gradeSelectedExam" :options="availableExams" class="w-full premium-select" :placeholder="$t('teacher_class_detail.exam_placeholder')">
                  <template #value="slotProps">
                    <div v-if="slotProps.value" class="flex items-center gap-2">
                      <span class="font-black uppercase text-xs">{{ $t(`teacher_class_detail.${slotProps.value.exam_type}`) }}</span>
                      <span class="px-2 py-0.5 rounded-lg bg-surface-100 dark:bg-surface-700 text-surface-500 text-[10px] font-bold">{{ slotProps.value.max_grade }} {{ $t('teacher_class_detail.points_short') }}</span>
                    </div>
                    <span v-else>{{ slotProps.placeholder }}</span>
                  </template>
                  <template #option="slotProps">
                    <div class="flex items-center justify-between w-full">
                      <span class="font-black uppercase text-xs">{{ $t(`teacher_class_detail.${slotProps.option.exam_type}`) }}</span>
                      <span class="text-surface-400 text-[10px] font-bold">{{ slotProps.option.max_grade }} {{ $t('teacher_class_detail.points_short') }}</span>
                    </div>
                  </template>
                </Select>
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_class_detail.academic_year_label') }}</label>
                <InputText v-model="gradeAcademicYear" class="w-full premium-input font-bold" />
              </div>
            </div>

            <div v-if="gradeLoading" class="flex justify-center py-20">
              <ProgressSpinner style="width: 40px; height: 40px" />
            </div>

            <div v-else-if="!gradeSelectedExam" class="flex flex-col items-center justify-center py-24 text-surface-400 bg-surface-50 dark:bg-surface-800/40 rounded-[2.5rem] border-2 border-dashed border-surface-200 dark:border-surface-700">
               <i class="pi pi-file-edit text-6xl mb-6 opacity-20"></i>
               <p class="text-xl font-black tracking-tight">{{ $t('teacher_class_detail.select_exam_to_grade') }}</p>
            </div>

            <template v-else>
              <div class="sticky-table-container rounded-3xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 shadow-sm modern-scroll mb-8">
                <table class="w-full text-sm border-collapse">
                  <thead>
                    <tr class="bg-surface-50 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-700">
                      <th class="p-4 text-center w-12 text-surface-400 sticky left-0 z-20 bg-surface-50 dark:bg-surface-900 border-r border-surface-200 dark:border-surface-700">#</th>
                      <th class="text-left p-4 font-black uppercase text-[10px] tracking-widest text-surface-500 sticky left-12 z-20 bg-surface-50 dark:bg-surface-900 min-w-[200px] border-r-2 border-surface-200 dark:border-surface-700 sticky-col-shadow">
                        {{ $t('teacher_class_detail.student_col') }}
                      </th>
                      
                      <th v-for="ex in gradeSelectedExam.exercises" :key="ex.id" class="text-center p-4 font-black uppercase text-[10px] tracking-[0.2em] text-surface-500 border-l border-surface-100 dark:border-surface-700 min-w-[120px]">
                        <div class="flex flex-col">
                          <span>{{ ex.level_name || $t('teacher_class_detail.exercise_fallback') }}</span>
                          <span class="text-primary-500 opacity-60">/ {{ ex.max_note }}</span>
                        </div>
                      </th>
                      
                      <th class="text-center p-4 font-black uppercase text-[10px] tracking-[0.2em] text-primary-600 dark:text-primary-400 bg-primary-50/50 dark:bg-primary-900/20 border-l-2 border-primary-100 dark:border-primary-800 sticky right-0 z-20 shadow-[-5px_0_10px_-5px_rgba(0,0,0,0.1)]">
                        <div class="flex flex-col">
                          <span>{{ $t('teacher_class_detail.total_col') }}</span>
                          <span>/ {{ gradeSelectedExam.max_grade }}</span>
                        </div>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(student, idx) in students"
                      :key="student.id"
                      class="border-b border-surface-100 dark:border-surface-700 hover:bg-surface-50/50 dark:hover:bg-surface-900/30 transition-colors"
                    >
                      <td class="p-4 text-center text-surface-400 font-bold text-xs sticky left-0 z-10 bg-white dark:bg-surface-800 border-r border-surface-100 dark:border-surface-700">{{ idx + 1 }}</td>
                      <td class="p-4 font-black text-surface-900 dark:text-surface-0 sticky left-12 z-10 bg-white dark:bg-surface-800 border-r-2 border-surface-200 dark:border-surface-700 sticky-col-shadow">
                         <div class="flex flex-col">
                           <span>{{ student.first_name }} {{ student.last_name }}</span>
                           <span class="text-[10px] text-surface-400 font-bold uppercase tracking-tighter">{{ student.code || '#' + student.id }}</span>
                         </div>
                      </td>
                      
                      <td v-for="ex in gradeSelectedExam.exercises" :key="ex.id" class="p-3 text-center border-l border-surface-50 dark:border-surface-700">
                        <InputNumber 
                          v-model="exerciseValues[student.id][ex.id]" 
                          @blur="clampExerciseValue(student.id, ex.id, ex.max_note)"
                          :min="0" :max="ex.max_note" :step="0.25"
                          inputClass="w-20 text-center font-black text-lg bg-transparent border-none focus:ring-0 text-surface-700 dark:text-surface-200"
                          class="mx-auto"
                        />
                      </td>

                      <td class="p-4 text-center font-black text-xl text-primary-600 dark:text-primary-400 bg-primary-50/30 dark:bg-primary-900/10 sticky right-0 z-10 shadow-[-4px_0_10px_-4px_rgba(0,0,0,0.1)]">
                        {{ calculateStudentTotal(student.id) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Summary bar & Save -->
              <div class="flex flex-col lg:flex-row items-center justify-between gap-8 p-8 glass-panel rounded-[2.5rem] shadow-xl">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-8 w-full lg:w-auto">
                  <div class="flex flex-col">
                    <span class="text-[10px] font-black uppercase text-surface-400 tracking-widest mb-1">{{ $t('teacher_class_detail.average_label') }}</span>
                    <span class="text-3xl font-black text-primary-600">{{ gradeAverage || '—' }}</span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[10px] font-black uppercase text-surface-400 tracking-widest mb-1">{{ $t('teacher_class_detail.filled_label') }}</span>
                    <div class="flex items-end gap-2">
                      <span class="text-3xl font-black text-surface-700 dark:text-surface-200">{{ gradeFilled }}</span>
                      <span class="text-sm font-bold text-surface-400 mb-1">/ {{ students.length }}</span>
                    </div>
                  </div>
                </div>
                <Button 
                  :label="$t('teacher_class_detail.save_grades')" 
                  icon="pi pi-check-circle" 
                  :loading="gradeSaving" 
                  raised
                  class="w-full lg:w-auto rounded-2xl px-12 py-5 font-black uppercase tracking-widest shadow-xl shadow-primary-500/30 text-lg"
                  @click="saveGrades" 
                />
              </div>
            </template>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.5s ease-out forwards;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

:deep(.premium-tabs .p-tablist-tab-list) {
  background: transparent;
}

:deep(.premium-tabs .p-tab) {
  color: var(--p-surface-500);
}

:deep(.premium-tabs .p-tab-active) {
  background: var(--p-primary-500) !important;
  color: white !important;
  box-shadow: 0 10px 15px -3px rgba(var(--p-primary-500-rgb), 0.3);
}



.sticky-table-container::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
.sticky-table-container::-webkit-scrollbar-thumb {
  background: rgba(0,0,0,0.1);
  border-radius: 10px;
}
.app-dark .sticky-table-container::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.2);
}
</style>
