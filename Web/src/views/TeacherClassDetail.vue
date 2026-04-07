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
const activeTab = ref<string>((route.query.tab as string) === 'grades' ? '1' : '0');

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
const gradeExamType = ref<string>('evaluation_continue');
const gradeAcademicYear = ref<string>(computeAcademicYear());
const gradeMaxGrade = ref<number>(20);
const gradeLoading = ref(false);
const gradeSaving = ref(false);
const existingGradeMap = ref<Map<number, GradeRecord>>(new Map());
const gradeValues = ref<Record<number, string>>({});

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

    // Session mode: pre-select subject from URL
    if (sessionMode.value && sessionSubjectId.value) {
      attSubjectId.value = sessionSubjectId.value;
    } else if (classData.value && subjects.value.length >= 1) {
      attSubjectId.value = subjects.value[0].id;
      gradeSubjectId.value = subjects.value[0].id;
    }

    // Session mode: pre-fill date from URL
    if (sessionMode.value && sessionDate.value) {
      attDateObj.value = new Date(sessionDate.value + 'T00:00:00');
    }

    initAttStatuses();
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
async function loadGrades() {
  if (!gradeSubjectId.value) return;
  gradeLoading.value = true;
  try {
    const records = await GradeService.getClassGrades(classId.value, {
      subject_id: gradeSubjectId.value,
      semester: gradeSemester.value,
      academic_year: gradeAcademicYear.value,
    });
    const filtered = (records as GradeRecord[]).filter((r) => r.exam_type === gradeExamType.value);
    const map = new Map<number, GradeRecord>();
    for (const r of filtered) map.set(r.student_id, r);
    existingGradeMap.value = map;

    const vals: Record<number, string> = {};
    for (const s of students.value) {
      const ex = map.get(s.id);
      vals[s.id] = ex ? String(ex.grade) : '';
    }
    gradeValues.value = vals;
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_grades_error'), life: 3000 });
  } finally {
    gradeLoading.value = false;
  }
}

async function saveGrades() {
  if (!gradeSubjectId.value) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'Please select a subject', life: 3000 });
    return;
  }
  const toCreate: any[] = [];
  const toUpdate: Array<{ id: number; data: any }> = [];

  for (const s of students.value) {
    const raw = gradeValues.value[s.id];
    if (raw === '' || raw === null || raw === undefined) continue;
    const num = parseFloat(raw);
    if (isNaN(num)) continue;

    const payload = {
      student_id: s.id,
      subject_id: gradeSubjectId.value!,
      teacher_id: teacherId.value,
      exam_type: gradeExamType.value,
      grade: num,
      max_grade: gradeMaxGrade.value,
      semester: gradeSemester.value,
      academic_year: gradeAcademicYear.value,
    };

    const ex = existingGradeMap.value.get(s.id);
    ex ? toUpdate.push({ id: ex.id, data: payload }) : toCreate.push(payload);
  }

  if (toCreate.length === 0 && toUpdate.length === 0) {
    toast.add({ severity: 'info', summary: 'Nothing to save', detail: 'Enter at least one grade', life: 3000 });
    return;
  }

  gradeSaving.value = true;
  try {
    const ops: Promise<any>[] = [];
    if (toCreate.length > 0) ops.push(GradeService.bulkCreateGrades(toCreate));
    for (const item of toUpdate) ops.push(GradeService.updateGrade(item.id, item.data));
    await Promise.all(ops);
    toast.add({ severity: 'success', summary: 'Saved', detail: `${toCreate.length + toUpdate.length} grade(s) saved`, life: 3000 });
    await loadGrades();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.save_grades_error'), life: 3000 });
  } finally {
    gradeSaving.value = false;
  }
}

const gradeAverage = computed(() => {
  const vals = Object.values(gradeValues.value).filter(v => v !== '' && !isNaN(parseFloat(v as string)));
  if (vals.length === 0) return null;
  return (vals.reduce((s, v) => s + parseFloat(v as string), 0) / vals.length).toFixed(1);
});

const gradeFilled = computed(() =>
  Object.values(gradeValues.value).filter(v => v !== '' && v !== null && v !== undefined).length
);

// ─── Watchers ─────────────────────────────────────────────
watch(attDateStr, () => { if (attSubjectId.value) loadAttendance(); });
watch(attSubjectId, () => loadAttendance());
watch([gradeSubjectId, gradeSemester, gradeExamType, gradeAcademicYear], () => loadGrades());

onMounted(async () => {
  await loadClass();
  if (attSubjectId.value) await loadAttendance();
  if (gradeSubjectId.value) await loadGrades();
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
        <Tab value="0">
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
        <TabPanel value="0">
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
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Exam Type</label>
                <Select v-model="gradeExamType" :options="EXAM_TYPES" option-label="label" option-value="value" class="w-full sm:w-44" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Academic Year</label>
                <InputText v-model="gradeAcademicYear" placeholder="e.g. 2024-2025" class="w-full sm:w-36" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Max Grade</label>
                <InputNumber v-model="gradeMaxGrade" :min="1" :max="100" class="w-full sm:w-28" />
              </div>
            </div>

            <div v-if="gradeLoading" class="flex justify-center py-8">
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
                      <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">
                        Grade <span class="text-surface-400 font-normal">/ {{ gradeMaxGrade }}</span>
                      </th>
                      <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400 hidden md:table-cell">%</th>
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
                          v-if="existingGradeMap.has(student.id)"
                          class="ml-2 text-[10px] bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-1.5 py-0.5 rounded-full align-middle"
                        >saved</span>
                      </td>
                      <td class="p-3 font-mono text-xs text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ student.code }}</td>
                      <td class="p-3">
                        <div class="flex justify-center">
                          <InputText
                            v-model="gradeValues[student.id]"
                            :placeholder="`0–${gradeMaxGrade}`"
                            class="w-24 text-center"
                          />
                        </div>
                      </td>
                      <td class="p-3 text-center text-surface-500 dark:text-surface-400 hidden md:table-cell">
                        <span v-if="gradeValues[student.id] !== '' && !isNaN(parseFloat(gradeValues[student.id]))">
                          {{
                            Math.round((parseFloat(gradeValues[student.id]) / gradeMaxGrade) * 100)
                          }}%
                        </span>
                        <span v-else class="text-surface-300 dark:text-surface-600">—</span>
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
                      {{ gradeAverage !== null ? `${gradeAverage} / ${gradeMaxGrade}` : '—' }}
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
