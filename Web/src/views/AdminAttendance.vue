<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import ApiService from '@/service/ApiService';
import AttendanceService, { type AttendanceRecord } from '@/service/AttendanceService';

const toast = useToast();
const { t } = useI18n();

// ─── State ────────────────────────────────────────────────
const classes = ref<any[]>([]);
const classesLoading = ref(false);

const selectedClassId = ref<number | null>(null);
const selectedDate = ref<Date>(new Date());

// View mode: day or week
const viewMode = ref<'day' | 'week'>('day');
const viewModeOptions = computed(() => [
  { label: t('common.day'), value: 'day', icon: 'pi pi-calendar' },
  { label: t('common.week'), value: 'week', icon: 'pi pi-calendar-plus' },
]);

// Period filter (selected schedule slot IDs; empty = show all)
const selectedPeriodIds = ref<number[]>([]);

const activeTab = ref('0');

// All schedule slots for the selected class (all days)
const allClassSlots = ref<any[]>([]);
const scheduleLoading = ref(false);

// All attendance records for selected class+date(s)
const attendanceRecords = ref<AttendanceRecord[]>([]);
const attendanceLoading = ref(false);

// Students in the selected class
const classStudents = ref<any[]>([]);

// Overview (shown when no class is selected) — reacts to date/viewMode filter
// Stores pre-aggregated counts from the single /attendances/overview endpoint
const overviewRecords = ref<Array<{
  classId: number;
  present: number;
  absent: number;
  late: number;
  excused: number;
  marked: number;
}>>([])
const overviewLoading = ref(false);

// Student attendance dialog
const studentDialog = ref(false);
const selectedStudent = ref<any>(null);
const studentAttendances = ref<AttendanceRecord[]>([]);
const studentAttLoading = ref(false);

// Excuse in-flight tracking
const excusing = ref<number[]>([]);

// ─── Helpers ──────────────────────────────────────────────
function formatDateStr(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const DAY_ORDER = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

// ─── Date computeds ───────────────────────────────────────
const selectedDateStr = computed(() => formatDateStr(selectedDate.value));

const selectedDayName = computed(() => DAY_NAMES[selectedDate.value.getDay()]);

// Week: Sun-Sat containing selectedDate
const weekDates = computed(() => {
  const d = new Date(selectedDate.value);
  const dayOfWeek = d.getDay();
  const sunday = new Date(d);
  sunday.setDate(d.getDate() - dayOfWeek);
  return Array.from({ length: 7 }, (_, i) => {
    const date = new Date(sunday);
    date.setDate(sunday.getDate() + i);
    return date;
  });
});

const weekStartStr = computed(() => formatDateStr(weekDates.value[0]));
const weekEndStr = computed(() => formatDateStr(weekDates.value[6]));

const weekLabel = computed(() => `${weekStartStr.value} – ${weekEndStr.value}`);

// ─── Schedule slots computeds ─────────────────────────────

// Real schedule slots plus virtual ones based on attendance data (for subject-only attendance)
const combinedClassSlots = computed(() => {
  const slots = [...allClassSlots.value];
  const virtualMap = new Map();
  for (const r of attendanceRecords.value) {
    if (!r.schedule_id && r.subject_id) {
      const d = new Date(r.date);
      const dayName = DAY_NAMES[d.getDay()]?.toLowerCase() || '';

      // Check if there is an existing schedule slot for this subject on this day
      const existingSlot = slots.find(s => 
        (s.day ?? s.assignment?.day ?? '').toLowerCase() === dayName && 
        (s.subject_id === r.subject_id || s.assignment?.subject_id === r.subject_id)
      );

      if (!existingSlot) {
        const key = `virtual_${r.date}_${r.subject_id}`;
        if (!virtualMap.has(key)) {
          virtualMap.set(key, {
            id: key,
            isVirtual: true,
            subject: r.subject,
            subject_id: r.subject_id,
            day: dayName,
            start_time: '--:--',
            end_time: '--:--',
            date: r.date
          });
        }
      }
    }
  }
  slots.push(...Array.from(virtualMap.values()));
  return slots;
});

// Slots visible for the current view (all days in week mode, current day in day mode)
const scheduleSlots = computed(() => {
  if (viewMode.value === 'week') {
    return [...combinedClassSlots.value].sort((a: any, b: any) => {
      const da = DAY_ORDER.indexOf((a.day ?? a.assignment?.day ?? '').toLowerCase());
      const db = DAY_ORDER.indexOf((b.day ?? b.assignment?.day ?? '').toLowerCase());
      if (da !== db) return da - db;
      return (a.start_time ?? '').localeCompare(b.start_time ?? '');
    });
  }
  const day = selectedDayName.value.toLowerCase();
  return combinedClassSlots.value
    .filter((s: any) => (s.day ?? s.assignment?.day ?? '').toLowerCase() === day)
    .sort((a: any, b: any) => (a.start_time ?? '').localeCompare(b.start_time ?? ''));
});

// Options for the period MultiSelect – always drawn from all class slots
const periodOptions = computed(() => {
  return [...combinedClassSlots.value].sort((a: any, b: any) => {
    const da = DAY_ORDER.indexOf((a.day ?? a.assignment?.day ?? '').toLowerCase());
    const db = DAY_ORDER.indexOf((b.day ?? b.assignment?.day ?? '').toLowerCase());
    if (da !== db) return da - db;
    return (a.start_time ?? '').localeCompare(b.start_time ?? '');
  }).map((slot: any) => {
    const dayPart = `${(slot.day ?? slot.assignment?.day ?? '').slice(0, 3)} · `;
    const subject = slot.assignment?.subject?.name ?? slot.subject?.name ?? 'Session';
    return {
      id: slot.id,
      label: `${dayPart}${formatTime(slot.start_time)}–${formatTime(slot.end_time)} · ${subject}`,
    };
  });
});

// Slots after applying the period filter
const filteredScheduleSlots = computed(() => {
  if (selectedPeriodIds.value.length === 0) return scheduleSlots.value;
  return scheduleSlots.value.filter((s: any) => selectedPeriodIds.value.includes(s.id));
});

// ─── Attendance computeds ─────────────────────────────────

// Group all records by schedule_id (or virtual id)
const attBySchedule = computed(() => {
  const map: Record<string, AttendanceRecord[]> = {};
  for (const rec of attendanceRecords.value) {
    let key = '-1';
    if (rec.schedule_id) {
      key = String(rec.schedule_id);
    } else if (rec.subject_id) {
      const d = new Date(rec.date);
      const dayName = DAY_NAMES[d.getDay()]?.toLowerCase() || '';
      
      const existingSlot = allClassSlots.value.find(s => 
        (s.day ?? s.assignment?.day ?? '').toLowerCase() === dayName && 
        (s.subject_id === rec.subject_id || s.assignment?.subject_id === rec.subject_id)
      );

      if (existingSlot) {
        key = String(existingSlot.id);
      } else {
        key = `virtual_${rec.date}_${rec.subject_id}`;
      }
    }
    
    if (!map[key]) map[key] = [];
    map[key].push(rec);
  }
  return map;
});

// ── Day mode computeds ────────────────────────────────────

// Session summaries for day mode only
const sessionSummaries = computed(() => {
  return filteredScheduleSlots.value.map((slot: any) => {
    const records = (attBySchedule.value[slot.id] ?? []).filter(r => r.date === selectedDateStr.value);
    const present = records.filter(r => r.status === 'present').length;
    const absent = records.filter(r => r.status === 'absent').length;
    const late = records.filter(r => r.status === 'late').length;
    const excused = records.filter(r => r.status === 'excused').length;
    const total = classStudents.value.length;
    const marked = records.length;
    return { slot, present, absent, late, excused, total, marked };
  });
});

// Grid: students × sessions (day mode)
const attendanceGrid = computed(() => {
  return classStudents.value.map((student: any) => {
    const sessions = filteredScheduleSlots.value.map((slot: any) => {
      const records = (attBySchedule.value[slot.id] ?? []).filter(r => r.date === selectedDateStr.value);
      const rec = records.find(r => r.student_id === student.id);
      return { slot, status: rec?.status ?? null };
    });
    return { student, sessions };
  });
});

// ── Week mode computeds ───────────────────────────────────

// Per-day session summaries for week mode
const weekDaySummaries = computed(() => {
  return weekDates.value.map(date => {
    const dayName = DAY_NAMES[date.getDay()].toLowerCase();
    const dateStr = formatDateStr(date);
    const daySlots = filteredScheduleSlots.value.filter((s: any) =>
      (s.day ?? s.assignment?.day ?? '').toLowerCase() === dayName
    );
    const summaries = daySlots.map((slot: any) => {
      const records = (attBySchedule.value[slot.id] ?? []).filter(r => r.date === dateStr);
      const present = records.filter(r => r.status === 'present').length;
      const absent = records.filter(r => r.status === 'absent').length;
      const late = records.filter(r => r.status === 'late').length;
      const excused = records.filter(r => r.status === 'excused').length;
      const total = classStudents.value.length;
      const marked = records.length;
      return { slot, present, absent, late, excused, total, marked };
    });
    return {
      date,
      dateStr,
      dayName: DAY_NAMES[date.getDay()],
      summaries,
      hasSchedule: daySlots.length > 0,
    };
  }).filter(day => day.hasSchedule);
});

// Per-student weekly summary
const weekStudentSummaries = computed(() => {
  return classStudents.value.map((student: any) => {
    const recs = attendanceRecords.value.filter(r => r.student_id === student.id);
    const present = recs.filter(r => r.status === 'present').length;
    const absent = recs.filter(r => r.status === 'absent').length;
    const late = recs.filter(r => r.status === 'late').length;
    const excused = recs.filter(r => r.status === 'excused').length;
    const total = recs.length;
    const rate = total > 0 ? Math.round((present / total) * 100) : null;
    return { student, present, absent, late, excused, total, rate };
  });
});

// Columns for week grid view: (slot, date) pairs ordered by day then time
const weekGridColumns = computed(() => {
  return weekDates.value.flatMap(date => {
    const dayName = DAY_NAMES[date.getDay()].toLowerCase();
    const dateStr = formatDateStr(date);
    return filteredScheduleSlots.value
      .filter((s: any) => (s.day ?? s.assignment?.day ?? '').toLowerCase() === dayName)
      .map((slot: any) => ({
        slot,
        date,
        dateStr,
        dayLabel: DAY_NAMES[date.getDay()].slice(0, 3),
      }));
  });
});

// Grid rows for week mode
const weekAttendanceGrid = computed(() => {
  return classStudents.value.map((student: any) => {
    const cells = weekGridColumns.value.map(col => {
      const rec = (attBySchedule.value[col.slot.id] ?? [])
        .find(r => r.student_id === student.id && r.date === col.dateStr);
      return { ...col, status: rec?.status ?? null };
    });
    return { student, cells };
  });
});

// ─── Today overview computeds ─────────────────────────────

const overviewSummaries = computed(() =>
  classes.value
    .filter((cls: any) => (cls.students ?? []).length > 0)
    .map((cls: any) => {
      const entry = overviewRecords.value.find(r => r.classId === cls.id);
      const present = entry?.present ?? 0;
      const absent  = entry?.absent  ?? 0;
      const late    = entry?.late    ?? 0;
      const excused = entry?.excused ?? 0;
      const marked  = entry?.marked  ?? 0;
      const total   = (cls.students ?? []).length;
      const rate    = marked > 0 ? Math.round((present / marked) * 100) : null;
      return { cls, present, absent, late, excused, total, marked, rate };
    })
);

// Human-readable label for the overview header
const overviewLabel = computed(() => {
  if (viewMode.value === 'week') return `${weekStartStr.value} – ${weekEndStr.value}`;
  return selectedDateStr.value;
});

// True when the endpoint returned at least one record for the selected date/period
const overviewHasData = computed(() => overviewRecords.value.length > 0);

// ─── Load ─────────────────────────────────────────────────
async function loadClasses() {
  classesLoading.value = true;
  try {
    const response = await ApiService.get<any[]>('/classes');
    const raw = (response.data as any)?.data ?? response.data ?? [];
    classes.value = Array.isArray(raw) ? raw : Object.values(raw);
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('attendance.load_classes_error'), life: 3000 });
  } finally {
    classesLoading.value = false;
  }
}

async function loadOverview() {
  if (classes.value.length === 0) return;
  overviewLoading.value = true;
  try {
    const params = viewMode.value === 'week'
      ? { start_date: weekStartStr.value, end_date: weekEndStr.value }
      : { date: selectedDateStr.value };
    // Single request — the backend aggregates counts for ALL classes in one SQL query
    const response = await ApiService.get<any>('/attendances/overview', params);
    const raw: any[] = (response as any)?.data ?? [];
    overviewRecords.value = raw.map((s: any) => ({
      classId: Number(s.class_id),
      present: Number(s.present  ?? 0),
      absent:  Number(s.absent   ?? 0),
      late:    Number(s.late     ?? 0),
      excused: Number(s.excused  ?? 0),
      marked:  Number(s.marked   ?? 0),
    }));
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('attendance.load_attendance_error'), life: 3000 });
  } finally {
    overviewLoading.value = false;
  }
}

async function loadSchedule() {
  if (!selectedClassId.value) return;
  scheduleLoading.value = true;
  try {
    const response = await ApiService.get<any>('/schedules', {
      class_id: selectedClassId.value,
      per_page: 200,
      with_relations: 'class,subject,teacher',
    });
    const raw: any[] = (response.data as any)?.data ?? response.data ?? [];
    allClassSlots.value = raw;
  } catch {
    allClassSlots.value = [];
  } finally {
    scheduleLoading.value = false;
  }
}

async function loadAttendance() {
  if (!selectedClassId.value) return;
  attendanceLoading.value = true;
  try {
    const params = viewMode.value === 'week'
      ? { start_date: weekStartStr.value, end_date: weekEndStr.value }
      : { date: selectedDateStr.value };

    const records = await AttendanceService.getClassAttendances(selectedClassId.value, params);
    attendanceRecords.value = records;

    const cls = classes.value.find((c: any) => c.id === selectedClassId.value);
    classStudents.value = cls?.students ?? [];
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('attendance.load_attendance_error'), life: 3000 });
  } finally {
    attendanceLoading.value = false;
  }
}

async function loadClassData() {
  await Promise.all([loadSchedule(), loadAttendance()]);
}

async function openStudentTimeline(student: any) {
  selectedStudent.value = student;
  studentDialog.value = true;
  studentAttLoading.value = true;
  try {
    const params = viewMode.value === 'week'
      ? { start_date: weekStartStr.value, end_date: weekEndStr.value }
      : { start_date: selectedDateStr.value, end_date: selectedDateStr.value };

    const response = await ApiService.get<any>(`/students/${student.id}/attendances`, params);
    const raw = (response.data as any)?.data ?? response.data ?? [];
    studentAttendances.value = Array.isArray(raw) ? raw : Object.values(raw);
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('attendance.load_student_error'), life: 3000 });
  } finally {
    studentAttLoading.value = false;
  }
}

async function excuseAttendance(record: AttendanceRecord) {
  excusing.value.push(record.id);
  try {
    await AttendanceService.updateAttendance(record.id, { status: 'excused' });
    const idx = attendanceRecords.value.findIndex(r => r.id === record.id);
    if (idx !== -1) attendanceRecords.value[idx] = { ...attendanceRecords.value[idx], status: 'excused' };
    const sidx = studentAttendances.value.findIndex(r => r.id === record.id);
    if (sidx !== -1) studentAttendances.value[sidx] = { ...studentAttendances.value[sidx], status: 'excused' };
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('attendance.excuse_success'), life: 2000 });
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('attendance.excuse_error'), life: 3000 });
  } finally {
    excusing.value = excusing.value.filter(id => id !== record.id);
  }
}

const studentDialogHeader = computed(() => {
  return viewMode.value === 'week'
    ? `${name} – ${weekLabel.value}`
    : `${name} – ${selectedDateStr.value}`;
});

function getScheduleSlot(scheduleId: number | string | null): any | null {
  if (!scheduleId) return null;
  return combinedClassSlots.value.find((s: any) => String(s.id) === String(scheduleId)) ?? null;
}

function formatTime(t: string) {
  return t ? t.slice(0, 5) : '';
}

function statusColor(status: string | null) {
  switch (status) {
    case 'present': return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
    case 'absent': return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    case 'late': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
    case 'excused': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
    default: return 'bg-surface-100 text-surface-400 dark:bg-surface-700 dark:text-surface-500';
  }
}

function statusLabel(status: string | null) {
  if (!status) return '—';
  switch (status) {
    case 'present': return t('common.present');
    case 'absent': return t('common.absent');
    case 'late': return t('common.late');
    case 'excused': return t('common.excused');
    default: return status.charAt(0).toUpperCase() + status.slice(1);
  }
}

function statusIcon(status: string | null) {
  switch (status) {
    case 'present': return 'pi pi-check';
    case 'absent': return 'pi pi-times';
    case 'late': return 'pi pi-clock';
    case 'excused': return 'pi pi-info-circle';
    default: return 'pi pi-minus';
  }
}

function rateColor(rate: number | null) {
  if (rate === null) return 'text-surface-400 dark:text-surface-500';
  if (rate >= 90) return 'text-green-600 dark:text-green-400';
  if (rate >= 75) return 'text-amber-600 dark:text-amber-400';
  return 'text-red-600 dark:text-red-400';
}

// ─── Watchers ─────────────────────────────────────────────
watch(viewMode, () => {
  selectedPeriodIds.value = [];
  if (selectedClassId.value) loadClassData();
  else loadOverview();
});

watch(selectedClassId, () => {
  selectedPeriodIds.value = [];
  if (selectedClassId.value) loadClassData();
  else loadOverview();
});

watch(selectedDate, () => {
  if (selectedClassId.value) loadClassData();
  else loadOverview();
});

onMounted(async () => {
  await loadClasses();
  await loadOverview();
});
</script>

<template>
  <div class="p-4 md:p-6">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-1">{{ t('attendance.title') }}</h1>
      <p class="text-surface-500 dark:text-surface-400">{{ t('attendance.subtitle') }}</p>
    </div>

    <!-- Controls -->
    <div class="flex flex-wrap gap-4 mb-6 p-4 bg-white dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
      <!-- Class selector -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('attendance.class') }}</label>
        <Select
          v-model="selectedClassId"
          :options="classes"
          option-label="name"
          option-value="id"
          :placeholder="t('attendance.all_classes')"
          :loading="classesLoading"
          show-clear
          class="w-full sm:w-64"
        />
      </div>

      <!-- View mode toggle -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('attendance.view') }}</label>
        <SelectButton
          v-model="viewMode"
          :options="viewModeOptions"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
        />
      </div>

      <!-- Date picker (day mode) -->
      <div v-if="viewMode === 'day'" class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('attendance.date') }}</label>
        <DatePicker
          v-model="selectedDate"
          show-icon
          dateFormat="yy-mm-dd"
          :manual-input="false"
          class="w-full sm:w-48"
        />
      </div>

      <!-- Week picker (week mode) -->
      <div v-else class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('attendance.week_label') }}</label>
        <div class="flex items-center gap-2">
          <DatePicker
            v-model="selectedDate"
            show-icon
            dateFormat="yy-mm-dd"
            :manual-input="false"
            class="w-full sm:w-48"
          />
          <span v-if="selectedClassId" class="text-sm text-surface-500 dark:text-surface-400 bg-surface-50 dark:bg-surface-700 px-3 py-2 rounded-lg border border-surface-200 dark:border-surface-600 whitespace-nowrap">
            {{ weekLabel }}
          </span>
        </div>
      </div>

      <!-- Day label (day mode) -->
      <div v-if="viewMode === 'day' && selectedClassId" class="flex items-end">
        <span class="text-sm text-surface-500 dark:text-surface-400 bg-surface-50 dark:bg-surface-700 px-3 py-2 rounded-lg border border-surface-200 dark:border-surface-600">
          <i class="pi pi-calendar mr-1.5"></i>{{ t('common.' + selectedDayName.toLowerCase()) }}
        </span>
      </div>

      <!-- Period filter -->
      <div v-if="selectedClassId && allClassSlots.length > 0" class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
          {{ t('attendance.periods') }}
          <span v-if="selectedPeriodIds.length > 0" class="ml-1 text-xs text-primary-600 dark:text-primary-400">{{ t('attendance.periods_selected', { count: selectedPeriodIds.length }) }}</span>
        </label>
        <MultiSelect
          v-model="selectedPeriodIds"
          :options="periodOptions"
          option-label="label"
          option-value="id"
          :placeholder="t('attendance.all_periods')"
          :max-selected-labels="2"
          class="w-full sm:w-72"
        />
      </div>
    </div>

    <!-- No class selected → Attendance Overview -->
    <div v-if="!selectedClassId">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">
            {{ viewMode === 'week' ? t('attendance.week_overview') : t('attendance.todays_attendance') }}
          </h2>
          <p class="text-sm text-surface-500 dark:text-surface-400">
            <span class="inline-flex items-center gap-1.5">
              <i class="pi pi-calendar text-xs"></i>
              {{ overviewLabel }}
            </span>
            <span class="ml-2">{{ t('attendance.all_classes_subtitle') }}</span>
          </p>
        </div>
        <Button icon="pi pi-refresh" text rounded :loading="overviewLoading" @click="loadOverview" />
      </div>

      <div v-if="overviewLoading && overviewSummaries.length === 0" class="flex justify-center py-16">
        <ProgressSpinner style="width: 40px; height: 40px" />
      </div>

      <div v-else-if="overviewSummaries.length === 0" class="flex flex-col items-center py-20 text-surface-400">
        <i class="pi pi-building text-5xl mb-3"></i>
        <p class="text-lg">{{ t('attendance.no_classes') }}</p>
      </div>

      <div v-else class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
        <!-- No records for selected date/period -->
        <div
          v-if="!overviewHasData"
          class="flex flex-col items-center py-10 text-surface-400 dark:text-surface-500"
        >
          <i class="pi pi-calendar-times text-4xl mb-3"></i>
          <p class="font-medium">{{ t('attendance.no_records_date') }}</p>
          <p class="text-sm mt-1">{{ overviewLabel }}</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="bg-surface-50 dark:bg-surface-800/80">
              <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400">{{ t('attendance.class') }}</th>
              <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">
                <span class="flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>{{ t('common.present') }}</span>
              </th>
              <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">
                <span class="flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>{{ t('common.absent') }}</span>
              </th>
              <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400 hidden sm:table-cell">
                <span class="flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>{{ t('common.late') }}</span>
              </th>
              <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400 hidden md:table-cell">{{ t('common.students') }}</th>
              <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">{{ t('attendance.rate') }}</th>
              <th class="p-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in overviewSummaries"
              :key="row.cls.id"
              class="border-t border-surface-100 dark:border-surface-700 hover:bg-surface-50/50 dark:hover:bg-surface-800/40 transition-colors"
            >
              <td class="p-3">
                <div class="font-medium text-surface-900 dark:text-surface-100">{{ row.cls.name }}</div>
                <div v-if="row.cls.level" class="text-xs text-surface-400 dark:text-surface-500">{{ row.cls.level }}</div>
              </td>
              <td class="p-3 text-center font-semibold text-green-700 dark:text-green-300">
                <span v-if="overviewRecords.find(r => r.classId === row.cls.id)">{{ row.present }}</span>
                <span v-else class="text-surface-300 dark:text-surface-600">–</span>
              </td>
              <td class="p-3 text-center font-semibold text-red-700 dark:text-red-300">
                <span v-if="overviewRecords.find(r => r.classId === row.cls.id)">{{ row.absent }}</span>
                <span v-else class="text-surface-300 dark:text-surface-600">–</span>
              </td>
              <td class="p-3 text-center font-semibold text-amber-700 dark:text-amber-300 hidden sm:table-cell">
                <span v-if="overviewRecords.find(r => r.classId === row.cls.id)">{{ row.late }}</span>
                <span v-else class="text-surface-300 dark:text-surface-600">–</span>
              </td>
              <td class="p-3 text-center text-surface-500 dark:text-surface-400 hidden md:table-cell">
                <span class="text-xs font-medium">{{ row.total }}</span>
              </td>
              <td class="p-3 text-center">
                <span v-if="row.rate !== null" class="font-semibold text-sm" :class="rateColor(row.rate)">{{ row.rate }}%</span>
                <span v-else class="text-xs text-surface-400 dark:text-surface-500 italic">{{ t('attendance.not_rated') }}</span>
              </td>
              <td class="p-3 text-right">
                <Button
                  :label="t('common.view')"
                  icon="pi pi-arrow-right"
                  size="small"
                  text
                  severity="secondary"
                  @click="selectedClassId = row.cls.id"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main content -->
    <template v-else>
      <Tabs v-model:value="activeTab">
        <TabList>
          <Tab value="0">
            <span class="flex items-center gap-2">
              <i class="pi pi-calendar"></i>
              <span>{{ t('attendance.session_overview') }}</span>
            </span>
          </Tab>
          <Tab value="1">
            <span class="flex items-center gap-2">
              <i class="pi pi-users"></i>
              <span>{{ t('attendance.student_list') }}</span>
            </span>
          </Tab>
          <Tab value="2">
            <span class="flex items-center gap-2">
              <i class="pi pi-table"></i>
              <span>{{ t('attendance.grid_view') }}</span>
            </span>
          </Tab>
        </TabList>

        <TabPanels>

          <!-- ═══════════ TAB 0: SESSION OVERVIEW ═══════════ -->
          <TabPanel value="0">
            <div class="pt-4">
              <div v-if="scheduleLoading || attendanceLoading" class="flex justify-center py-12">
                <ProgressSpinner style="width: 40px; height: 40px" />
              </div>

              <!-- ── DAY MODE ── -->
              <template v-else-if="viewMode === 'day'">
                <div v-if="filteredScheduleSlots.length === 0" class="flex flex-col items-center py-12 text-surface-400">
                  <i class="pi pi-calendar-times text-4xl mb-3"></i>
                  <p>{{ t('attendance.no_sessions_day', { day: t('common.' + selectedDayName.toLowerCase()) }) }}</p>
                </div>

                <div v-else class="space-y-3">
                  <div
                    v-for="summary in sessionSummaries"
                    :key="summary.slot.id"
                    class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden"
                  >
                    <div class="flex flex-wrap items-center gap-4 p-4">
                      <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 rounded-lg p-2 shrink-0">
                          <i class="pi pi-clock text-sm"></i>
                        </div>
                        <div class="min-w-0">
                          <div class="font-semibold text-surface-900 dark:text-surface-0 truncate">
                            {{ summary.slot.assignment?.subject?.name ?? summary.slot.subject?.name ?? 'Session' }}
                          </div>
                          <div class="text-xs text-surface-500 dark:text-surface-400">
                            {{ formatTime(summary.slot.start_time) }} – {{ formatTime(summary.slot.end_time) }}
                            <span v-if="summary.slot.room" class="ml-2">
                              <i class="pi pi-map-marker text-[10px]"></i> {{ summary.slot.room }}
                            </span>
                          </div>
                        </div>
                      </div>
                      <div class="flex items-center gap-3 shrink-0">
                        <div v-if="summary.marked === 0" class="text-xs text-surface-400 dark:text-surface-500 italic">{{ t('attendance.not_marked_yet') }}</div>
                        <template v-else>
                          <span class="flex items-center gap-1.5 text-sm">
                            <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                            <span class="font-semibold text-green-700 dark:text-green-300">{{ summary.present }}</span>
                          </span>
                          <span class="flex items-center gap-1.5 text-sm">
                            <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                            <span class="font-semibold text-red-700 dark:text-red-300">{{ summary.absent }}</span>
                          </span>
                          <span v-if="summary.late > 0" class="flex items-center gap-1.5 text-sm">
                            <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                            <span class="font-semibold text-amber-700 dark:text-amber-300">{{ summary.late }}</span>
                          </span>
                          <span class="text-xs text-surface-400 dark:text-surface-500">/ {{ summary.total }}</span>
                        </template>
                      </div>
                    </div>
                    <div v-if="summary.marked > 0 && summary.total > 0" class="h-1.5 bg-surface-100 dark:bg-surface-700 flex">
                      <div class="bg-green-500 h-full transition-all" :style="{ width: `${(summary.present / summary.total) * 100}%` }"></div>
                      <div class="bg-amber-500 h-full transition-all" :style="{ width: `${(summary.late / summary.total) * 100}%` }"></div>
                      <div class="bg-red-500 h-full transition-all" :style="{ width: `${(summary.absent / summary.total) * 100}%` }"></div>
                    </div>
                  </div>
                </div>
              </template>

              <!-- ── WEEK MODE ── -->
              <template v-else>
                <div v-if="weekDaySummaries.length === 0" class="flex flex-col items-center py-12 text-surface-400">
                  <i class="pi pi-calendar-times text-4xl mb-3"></i>
                  <p>{{ t('attendance.no_sessions_week') }}</p>
                </div>

                <div v-else class="space-y-6">
                  <div v-for="day in weekDaySummaries" :key="day.dateStr">
                    <!-- Day header -->
                    <div class="flex items-center gap-3 mb-3">
                      <div class="bg-surface-100 dark:bg-surface-700 rounded-lg px-3 py-1.5 text-sm font-semibold text-surface-700 dark:text-surface-200">
                        {{ t('common.' + day.dayName.toLowerCase()) }}
                      </div>
                      <span class="text-xs text-surface-400 dark:text-surface-500">{{ day.dateStr }}</span>
                      <div class="flex-1 h-px bg-surface-200 dark:bg-surface-700"></div>
                    </div>

                    <!-- Sessions for that day -->
                    <div class="space-y-2 pl-2">
                      <div
                        v-for="summary in day.summaries"
                        :key="summary.slot.id"
                        class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden"
                      >
                        <div class="flex flex-wrap items-center gap-4 p-4">
                          <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 rounded-lg p-2 shrink-0">
                              <i class="pi pi-clock text-sm"></i>
                            </div>
                            <div class="min-w-0">
                              <div class="font-semibold text-surface-900 dark:text-surface-0 truncate">
                                {{ summary.slot.assignment?.subject?.name ?? summary.slot.subject?.name ?? 'Session' }}
                              </div>
                              <div class="text-xs text-surface-500 dark:text-surface-400">
                                {{ formatTime(summary.slot.start_time) }} – {{ formatTime(summary.slot.end_time) }}
                                <span v-if="summary.slot.room" class="ml-2">
                                  <i class="pi pi-map-marker text-[10px]"></i> {{ summary.slot.room }}
                                </span>
                              </div>
                            </div>
                          </div>
                          <div class="flex items-center gap-3 shrink-0">
                            <div v-if="summary.marked === 0" class="text-xs text-surface-400 dark:text-surface-500 italic">{{ t('attendance.not_marked_yet') }}</div>
                            <template v-else>
                              <span class="flex items-center gap-1.5 text-sm">
                                <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                                <span class="font-semibold text-green-700 dark:text-green-300">{{ summary.present }}</span>
                              </span>
                              <span class="flex items-center gap-1.5 text-sm">
                                <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                <span class="font-semibold text-red-700 dark:text-red-300">{{ summary.absent }}</span>
                              </span>
                              <span v-if="summary.late > 0" class="flex items-center gap-1.5 text-sm">
                                <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                                <span class="font-semibold text-amber-700 dark:text-amber-300">{{ summary.late }}</span>
                              </span>
                              <span class="text-xs text-surface-400 dark:text-surface-500">/ {{ summary.total }}</span>
                            </template>
                          </div>
                        </div>
                        <div v-if="summary.marked > 0 && summary.total > 0" class="h-1.5 bg-surface-100 dark:bg-surface-700 flex">
                          <div class="bg-green-500 h-full transition-all" :style="{ width: `${(summary.present / summary.total) * 100}%` }"></div>
                          <div class="bg-amber-500 h-full transition-all" :style="{ width: `${(summary.late / summary.total) * 100}%` }"></div>
                          <div class="bg-red-500 h-full transition-all" :style="{ width: `${(summary.absent / summary.total) * 100}%` }"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </TabPanel>

          <!-- ═══════════ TAB 1: STUDENT LIST ═══════════ -->
          <TabPanel value="1">
            <div class="pt-4">
              <div v-if="attendanceLoading" class="flex justify-center py-12">
                <ProgressSpinner style="width: 40px; height: 40px" />
              </div>

              <div v-else-if="classStudents.length === 0" class="flex flex-col items-center py-12 text-surface-400">
                <i class="pi pi-users text-4xl mb-3"></i>
                <p>{{ t('attendance.no_students') }}</p>
              </div>

              <!-- ── DAY MODE ── -->
              <template v-else-if="viewMode === 'day'">
                <div class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-surface-50 dark:bg-surface-800/80">
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 w-10">#</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400">{{ t('common.name') }}</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ t('attendance.student_code') }}</th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">{{ t('attendance.sessions_today') }}</th>
                        <th class="text-right p-3 font-semibold text-surface-500 dark:text-surface-400"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(student, idx) in classStudents"
                        :key="student.id"
                        class="border-t border-surface-100 dark:border-surface-700 hover:bg-surface-50/50 dark:hover:bg-surface-800/40 transition-colors"
                      >
                        <td class="p-3 text-surface-400 text-xs">{{ idx + 1 }}</td>
                        <td class="p-3 font-medium text-surface-900 dark:text-surface-100">
                          {{ student.first_name }} {{ student.last_name }}
                        </td>
                        <td class="p-3 font-mono text-xs text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ student.code }}</td>
                        <td class="p-3">
                          <div class="flex justify-center gap-2 flex-wrap">
                            <template v-for="slot in filteredScheduleSlots" :key="slot.id">
                              <div class="flex items-center gap-1">
                                <div
                                  class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                  :class="statusColor((attBySchedule[slot.id] ?? []).find(r => r.student_id === student.id)?.status ?? null)"
                                >
                                  {{ formatTime(slot.start_time) }}
                                </div>

                              </div>
                            </template>
                            <span v-if="filteredScheduleSlots.length === 0" class="text-surface-300 dark:text-surface-600 text-xs">—</span>
                          </div>
                        </td>
                        <td class="p-3 text-right">
                          <Button
                            :label="t('common.view')"
                            icon="pi pi-eye"
                            size="small"
                            text
                            severity="secondary"
                            @click="openStudentTimeline(student)"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <!-- ── WEEK MODE ── -->
              <template v-else>
                <div class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-surface-50 dark:bg-surface-800/80">
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 w-10">#</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400">{{ t('common.name') }}</th>
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ t('attendance.student_code') }}</th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">
                          <span class="flex items-center justify-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>{{ t('common.present') }}
                          </span>
                        </th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">
                          <span class="flex items-center justify-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>{{ t('common.absent') }}
                          </span>
                        </th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400 hidden md:table-cell">
                          <span class="flex items-center justify-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>{{ t('common.late') }}
                          </span>
                        </th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400 hidden md:table-cell">
                          <span class="flex items-center justify-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>{{ t('common.excused') }}
                          </span>
                        </th>
                        <th class="text-center p-3 font-semibold text-surface-500 dark:text-surface-400">{{ t('attendance.rate') }}</th>
                        <th class="text-right p-3 font-semibold text-surface-500 dark:text-surface-400"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(row, idx) in weekStudentSummaries"
                        :key="row.student.id"
                        class="border-t border-surface-100 dark:border-surface-700 hover:bg-surface-50/50 dark:hover:bg-surface-800/40 transition-colors"
                      >
                        <td class="p-3 text-surface-400 text-xs">{{ idx + 1 }}</td>
                        <td class="p-3 font-medium text-surface-900 dark:text-surface-100">
                          {{ row.student.first_name }} {{ row.student.last_name }}
                        </td>
                        <td class="p-3 font-mono text-xs text-surface-500 dark:text-surface-400 hidden sm:table-cell">{{ row.student.code }}</td>
                        <td class="p-3 text-center font-semibold text-green-700 dark:text-green-300">{{ row.present }}</td>
                        <td class="p-3 text-center font-semibold text-red-700 dark:text-red-300">{{ row.absent }}</td>
                        <td class="p-3 text-center font-semibold text-amber-700 dark:text-amber-300 hidden md:table-cell">{{ row.late }}</td>
                        <td class="p-3 text-center font-semibold text-blue-700 dark:text-blue-300 hidden md:table-cell">{{ row.excused }}</td>
                        <td class="p-3 text-center">
                          <span v-if="row.rate !== null" class="font-semibold text-sm" :class="rateColor(row.rate)">
                            {{ row.rate }}%
                          </span>
                          <span v-else class="text-surface-400 dark:text-surface-500 text-xs">—</span>
                        </td>
                        <td class="p-3 text-right">
                          <Button
                            :label="t('common.view')"
                            icon="pi pi-eye"
                            size="small"
                            text
                            severity="secondary"
                            @click="openStudentTimeline(row.student)"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>
            </div>
          </TabPanel>

          <!-- ═══════════ TAB 2: GRID VIEW ═══════════ -->
          <TabPanel value="2">
            <div class="pt-4">
              <div v-if="attendanceLoading || scheduleLoading" class="flex justify-center py-12">
                <ProgressSpinner style="width: 40px; height: 40px" />
              </div>

              <div v-else-if="filteredScheduleSlots.length === 0" class="flex flex-col items-center py-12 text-surface-400">
                <i class="pi pi-calendar-times text-4xl mb-3"></i>
                <p>{{ viewMode === 'week' ? t('attendance.no_schedule_week') : t('attendance.no_schedule_day', { day: t('common.' + selectedDayName.toLowerCase()) }) }}</p>
              </div>

              <div v-else-if="classStudents.length === 0" class="flex flex-col items-center py-12 text-surface-400">
                <i class="pi pi-users text-4xl mb-3"></i>
                <p>{{ t('attendance.no_students') }}</p>
              </div>

              <!-- ── DAY MODE ── -->
              <template v-else-if="viewMode === 'day'">
                <div class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
                  <table class="text-sm border-collapse w-full">
                    <thead>
                      <tr class="bg-surface-50 dark:bg-surface-800/80">
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 border-b border-surface-200 dark:border-surface-700 min-w-36">
                          {{ t('common.name') }}
                        </th>
                        <th
                          v-for="slot in filteredScheduleSlots"
                          :key="slot.id"
                          class="text-center p-2 font-semibold text-surface-500 dark:text-surface-400 border-b border-surface-200 dark:border-surface-700 min-w-24"
                        >
                          <div class="text-xs font-semibold">{{ slot.assignment?.subject?.name ?? slot.subject?.name ?? 'Session' }}</div>
                          <div class="text-[10px] text-surface-400 font-normal mt-0.5">
                            {{ formatTime(slot.start_time) }}–{{ formatTime(slot.end_time) }}
                          </div>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="row in attendanceGrid"
                        :key="row.student.id"
                        class="border-t border-surface-100 dark:border-surface-700 hover:bg-surface-50/30 dark:hover:bg-surface-800/30 transition-colors"
                      >
                        <td class="p-3 font-medium text-surface-900 dark:text-surface-100 border-r border-surface-100 dark:border-surface-700">
                          {{ row.student.first_name }} {{ row.student.last_name }}
                        </td>
                        <td
                          v-for="cell in row.sessions"
                          :key="cell.slot.id"
                          class="p-2 text-center"
                        >
                          <span
                            class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-semibold"
                            :class="statusColor(cell.status)"
                            :title="statusLabel(cell.status)"
                          >
                            <i :class="statusIcon(cell.status)" class="text-[11px]"></i>
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <!-- ── WEEK MODE ── -->
              <template v-else>
                <div class="overflow-x-auto rounded-xl border border-surface-200 dark:border-surface-700">
                  <table class="text-sm border-collapse">
                    <thead>
                      <!-- Day header row -->
                      <tr class="bg-surface-50 dark:bg-surface-800/80">
                        <th class="text-left p-3 font-semibold text-surface-500 dark:text-surface-400 border-b border-surface-200 dark:border-surface-700 min-w-36 sticky left-0 bg-surface-50 dark:bg-surface-800/80 z-10">
                          {{ t('common.name') }}
                        </th>
                        <th
                          v-for="col in weekGridColumns"
                          :key="`${col.dateStr}-${col.slot.id}`"
                          class="text-center p-2 font-semibold text-surface-500 dark:text-surface-400 border-b border-surface-200 dark:border-surface-700 min-w-20"
                        >
                          <div class="text-xs font-semibold text-surface-700 dark:text-surface-200">{{ col.dayLabel }}</div>
                          <div class="text-[10px] text-surface-500 dark:text-surface-400 font-normal mt-0.5">
                            {{ col.slot.assignment?.subject?.name ?? col.slot.subject?.name ?? 'Session' }}
                          </div>
                          <div class="text-[10px] text-surface-400 font-normal">
                            {{ formatTime(col.slot.start_time) }}
                          </div>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="row in weekAttendanceGrid"
                        :key="row.student.id"
                        class="border-t border-surface-100 dark:border-surface-700 hover:bg-surface-50/30 dark:hover:bg-surface-800/30 transition-colors"
                      >
                        <td class="p-3 font-medium text-surface-900 dark:text-surface-100 border-r border-surface-100 dark:border-surface-700 sticky left-0 bg-white dark:bg-surface-800 z-10 whitespace-nowrap">
                          {{ row.student.first_name }} {{ row.student.last_name }}
                        </td>
                        <td
                          v-for="cell in row.cells"
                          :key="`${cell.dateStr}-${cell.slot.id}`"
                          class="p-2 text-center"
                        >
                          <span
                            class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-semibold"
                            :class="statusColor(cell.status)"
                            :title="statusLabel(cell.status)"
                          >
                            <i :class="statusIcon(cell.status)" class="text-[11px]"></i>
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <!-- Legend -->
              <div v-if="filteredScheduleSlots.length > 0" class="flex flex-wrap gap-4 mt-3 text-xs text-surface-500 dark:text-surface-400">
                <span class="flex items-center gap-1.5">
                  <span class="w-3 h-3 rounded-full bg-green-400 inline-block"></span> {{ t('attendance.legend_present') }}
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="w-3 h-3 rounded-full bg-red-400 inline-block"></span> {{ t('attendance.legend_absent') }}
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> {{ t('attendance.legend_late') }}
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="w-3 h-3 rounded-full bg-blue-400 inline-block"></span> {{ t('attendance.legend_excused') }}
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="w-3 h-3 rounded-full bg-surface-300 inline-block"></span> {{ t('attendance.legend_not_marked') }}
                </span>
              </div>
            </div>
          </TabPanel>

        </TabPanels>
      </Tabs>
    </template>

    <!-- Student Timeline Dialog -->
    <Dialog
      v-model:visible="studentDialog"
      :header="studentDialogHeader"
      :style="{ width: '520px', maxWidth: '95vw' }"
      :modal="true"
      :closable="true"
    >
      <div v-if="studentAttLoading" class="flex justify-center py-8">
        <ProgressSpinner style="width: 36px; height: 36px" />
      </div>

      <div v-else-if="studentAttendances.length === 0" class="flex flex-col items-center py-8 text-surface-400">
        <i class="pi pi-calendar-times text-3xl mb-2"></i>
        <p class="text-sm">{{ t('attendance.no_records', { period: viewMode === 'week' ? t('common.week') : t('common.day') }) }}</p>
      </div>

      <div v-else class="space-y-2 py-2">
        <div
          v-for="rec in studentAttendances"
          :key="rec.id"
          class="flex items-center gap-3 p-3 rounded-lg border"
          :class="{
            'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20': rec.status === 'present',
            'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20': rec.status === 'absent',
            'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20': rec.status === 'late',
            'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20': rec.status === 'excused',
          }"
        >
          <!-- Date (shown in week mode) -->
          <div v-if="viewMode === 'week'" class="shrink-0 text-xs font-mono text-surface-500 dark:text-surface-400 w-24">
            {{ rec.date?.slice(0, 10) ?? '—' }}
          </div>

          <!-- Time slot -->
          <div class="shrink-0 text-xs font-mono text-surface-500 dark:text-surface-400 w-24">
            <template v-if="getScheduleSlot(rec.schedule_id)">
              {{ formatTime(getScheduleSlot(rec.schedule_id)!.start_time) }}
              –
              {{ formatTime(getScheduleSlot(rec.schedule_id)!.end_time) }}
            </template>
            <template v-else>
              {{ rec.time ? rec.time.slice(0,5) : '—' }}
            </template>
          </div>

          <!-- Subject -->
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-surface-800 dark:text-surface-200 truncate">
              {{ rec.subject?.name ?? getScheduleSlot(rec.schedule_id)?.assignment?.subject?.name ?? 'Subject' }}
            </div>
          </div>

          <!-- Status badge + Excuse action -->
          <div class="shrink-0 flex items-center gap-2">
            <span
              class="text-xs font-semibold px-2.5 py-1 rounded-full"
              :class="statusColor(rec.status)"
            >
              <i :class="statusIcon(rec.status)" class="mr-1 text-[10px]"></i>
              {{ statusLabel(rec.status) }}
            </span>
            <button
              v-if="rec.status === 'absent'"
              class="text-[10px] px-2 py-1 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/60 font-semibold border border-blue-200 dark:border-blue-700 transition-colors disabled:opacity-50"
              :disabled="excusing.includes(rec.id)"
              @click="excuseAttendance(rec)"
            >
              <i class="pi pi-info-circle mr-1 text-[9px]"></i>{{ t('attendance.excuse') }}
            </button>
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>
