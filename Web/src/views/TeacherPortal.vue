<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import apiService from '@/service/ApiService';
import ApiService from '@/service/ApiService';

const { t } = useI18n();
const router = useRouter();
const toast = useToast();
const loading = ref(false);
const classes = ref<any[]>([]);
const scheduleDialog = ref(false);
const scheduleLoading = ref(false);
const scheduleByDay = ref<Record<string, any[]>>({});
const todaysSessions = ref<any[]>([]);
const sessionsLoading = ref(false);
const sessionAttMap = ref<Record<number, { present: number; absent: number; late: number; total: number }>>({});
const sessionAttLoading = ref(false);

const WEEK_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const translatedWeekDays = computed(() => [
  t('common.monday'),
  t('common.tuesday'),
  t('common.wednesday'),
  t('common.thursday'),
  t('common.friday'),
  t('common.saturday'),
]);

const currentUser = computed(() => apiService.getUser());
const teacher = computed(() => currentUser.value?.teacher ?? null);

const teacherName = computed(() => {
  if (teacher.value) {
    return `${teacher.value.first_name} ${teacher.value.last_name}`;
  }
  return currentUser.value?.username ?? 'Teacher';
});

const levelColors: Record<string, string> = {
  default: 'bg-blue-100 text-blue-800',
  primary: 'bg-green-100 text-green-800',
  middle: 'bg-yellow-100 text-yellow-800',
  high: 'bg-purple-100 text-purple-800',
  college: 'bg-red-100 text-red-800',
};

function getLevelColor(level: string) {
  const key = level?.toLowerCase() ?? 'default';
  return levelColors[key] ?? levelColors.default;
}

function todayDateStr(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function todayDayName(): string {
  return DAY_NAMES[new Date().getDay()];
}

async function loadClasses() {
  loading.value = true;
  try {
    const response = await ApiService.get<any[]>('/teacher/classes');
    const raw = (response.data as any)?.data ?? response.data ?? [];
    classes.value = Array.isArray(raw) ? raw : Object.values(raw);
  } catch (e: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_classes_error'), life: 3000 });
  } finally {
    loading.value = false;
  }
}

async function loadTodaysSessions() {
  sessionsLoading.value = true;
  try {
    const params: Record<string, any> = { per_page: 200, with_relations: 'class,subject,teacher' };
    if (teacher.value?.id) params.teacher_id = teacher.value.id;
    const response = await ApiService.get<any>('/my-schedule', params);
    const raw: any[] = (response.data as any)?.data ?? response.data ?? [];
    const today = todayDayName();
    const filtered = raw.filter((s: any) => {
      const day = s.day ?? s.assignment?.day ?? '';
      return day.toLowerCase() === today.toLowerCase();
    });
    filtered.sort((a: any, b: any) => (a.start_time ?? '').localeCompare(b.start_time ?? ''));
    todaysSessions.value = filtered;
    loadSessionAttendanceSummaries();
  } catch {
    // silently fail — today's sessions are a convenience feature
  } finally {
    sessionsLoading.value = false;
  }
}

async function loadSessionAttendanceSummaries() {
  if (todaysSessions.value.length === 0) return;
  sessionAttLoading.value = true;
  const today = todayDateStr();
  const promises = todaysSessions.value.map(async (slot: any) => {
    try {
      const response = await ApiService.get<any>(`/schedules/${slot.id}/attendances`, { date: today });
      const records: any[] = (response.data as any)?.data ?? response.data ?? [];
      const counts = { present: 0, absent: 0, late: 0, total: records.length };
      for (const r of records) {
        if (r.status === 'present') counts.present++;
        else if (r.status === 'absent') counts.absent++;
        else if (r.status === 'late') counts.late++;
      }
      sessionAttMap.value[slot.id] = counts;
    } catch {
      sessionAttMap.value[slot.id] = { present: 0, absent: 0, late: 0, total: 0 };
    }
  });
  await Promise.all(promises);
  sessionAttLoading.value = false;
}

async function openSchedule() {
  scheduleDialog.value = true;
  if (Object.keys(scheduleByDay.value).length > 0) return;
  scheduleLoading.value = true;
  try {
    const params: Record<string, any> = { per_page: 200, with_relations: 'class,subject,teacher' };
    if (teacher.value?.id) params.teacher_id = teacher.value.id;
    const response = await ApiService.get<any>('/my-schedule', params);
    const raw: any[] = (response.data as any)?.data ?? response.data ?? [];
    const grouped: Record<string, any[]> = {};
    for (const slot of raw) {
      const day = slot.day ?? slot.assignment?.day ?? 'Unknown';
      const capitalDay = day.charAt(0).toUpperCase() + day.slice(1).toLowerCase();
      if (!grouped[capitalDay]) grouped[capitalDay] = [];
      grouped[capitalDay].push(slot);
    }
    for (const day of Object.keys(grouped)) {
      grouped[day].sort((a: any, b: any) => (a.start_time ?? '').localeCompare(b.start_time ?? ''));
    }
    scheduleByDay.value = grouped;
  } catch (e: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_portal.load_schedule_error'), life: 3000 });
  } finally {
    scheduleLoading.value = false;
  }
}

function goToClass(cls: any) {
  router.push({ name: 'teacher-class', params: { classId: cls.id } });
}

function openSessionAttendance(slot: any) {
  const classId = slot.assignment?.class?.id ?? slot.class?.id;
  const subjectId = slot.assignment?.subject?.id ?? slot.subject?.id;
  if (!classId) return;
  router.push({
    name: 'teacher-class',
    params: { classId },
    query: {
      tab: 'attendance',
      scheduleId: slot.id,
      subjectId,
      date: todayDateStr(),
    },
  });
}

function getSlotForDayTime(day: string, hour: number) {
  const slots = scheduleByDay.value[day] ?? [];
  return slots.filter((s: any) => {
    const start = parseInt((s.start_time ?? '00:00').split(':')[0]);
    return start === hour;
  });
}

const scheduleHours = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17];

function formatTime(t: string) {
  return t ? t.slice(0, 5) : '';
}

function getSubjectName(slot: any): string {
  return slot.assignment?.subject?.name ?? slot.subject?.name ?? 'Subject';
}

function getClassName(slot: any): string {
  return slot.assignment?.class?.name ?? slot.class?.name ?? '';
}

onMounted(() => {
  loadClasses();
  loadTodaysSessions();
});
</script>

<template>
  <div class="p-4 md:p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-1">
          {{ t('teacher_portal.title', { name: teacherName }) }}
        </h1>
        <p class="text-surface-500 dark:text-surface-400">{{ t('teacher_portal.subtitle') }}</p>
      </div>
      <Button
        :label="t('teacher_portal.my_schedule')"
        icon="pi pi-calendar"
        severity="secondary"
        outlined
        @click="openSchedule"
      />
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 flex items-center gap-3">
        <div class="bg-blue-500 text-white rounded-lg p-2">
          <i class="pi pi-building text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ classes.length }}</div>
          <div class="text-sm text-blue-600 dark:text-blue-400">{{ t('teacher_portal.classes') }}</div>
        </div>
      </div>
      <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 flex items-center gap-3">
        <div class="bg-green-500 text-white rounded-lg p-2">
          <i class="pi pi-users text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-bold text-green-700 dark:text-green-300">
            {{ classes.reduce((s, c) => s + (c.students_count ?? 0), 0) }}
          </div>
          <div class="text-sm text-green-600 dark:text-green-400">{{ t('teacher_portal.total_students') }}</div>
        </div>
      </div>
      <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 flex items-center gap-3">
        <div class="bg-purple-500 text-white rounded-lg p-2">
          <i class="pi pi-book text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-bold text-purple-700 dark:text-purple-300">
            {{ [...new Set(classes.flatMap(c => (c.subjects ?? []).map((s: any) => s.id)))].length }}
          </div>
          <div class="text-sm text-purple-600 dark:text-purple-400">{{ t('teacher_portal.subjects_taught') }}</div>
        </div>
      </div>
    </div>

    <!-- Today's Sessions -->
    <div class="mb-6">
      <div class="flex items-center gap-2 mb-3">
        <h2 class="text-xl font-semibold text-surface-800 dark:text-surface-100">{{ t('teacher_portal.todays_sessions') }}</h2>
        <span class="text-xs text-surface-400 dark:text-surface-500 bg-surface-100 dark:bg-surface-700 px-2 py-0.5 rounded-full">
          {{ todayDayName() }}
        </span>
      </div>

      <div v-if="sessionsLoading" class="flex gap-3">
        <div v-for="n in 3" :key="n" class="h-24 w-52 bg-surface-100 dark:bg-surface-700 rounded-xl animate-pulse"></div>
      </div>

      <div v-else-if="todaysSessions.length === 0" class="flex items-center gap-3 p-4 bg-surface-50 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 text-surface-400">
        <i class="pi pi-calendar-times text-xl"></i>
        <span class="text-sm">{{ t('teacher_portal.no_sessions_today') }}</span>
      </div>

      <div v-else class="flex flex-wrap gap-3">
        <div
          v-for="slot in todaysSessions"
          :key="slot.id"
          class="group flex flex-col justify-between bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl p-4 min-w-48 max-w-56 shadow-sm hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500 transition-all cursor-pointer"
          @click="openSessionAttendance(slot)"
        >
          <div>
            <div class="text-sm font-bold text-surface-900 dark:text-surface-0 mb-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
              {{ getSubjectName(slot) }}
            </div>
            <div class="text-xs text-surface-500 dark:text-surface-400 mb-2">
              {{ getClassName(slot) }}
            </div>
            <div class="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 font-medium">
              <i class="pi pi-clock text-[10px]"></i>
              {{ formatTime(slot.start_time) }} – {{ formatTime(slot.end_time) }}
            </div>
            <div v-if="slot.room" class="flex items-center gap-1 text-xs text-surface-400 dark:text-surface-500 mt-0.5">
              <i class="pi pi-map-marker text-[10px]"></i>
              {{ slot.room }}
            </div>
          </div>
          <div class="mt-3 pt-2 border-t border-surface-100 dark:border-surface-700">
            <!-- Loading summaries -->
            <span v-if="!(slot.id in sessionAttMap)" class="text-xs text-surface-400 dark:text-surface-500 flex items-center gap-1">
              <i class="pi pi-spin pi-spinner text-[10px]"></i> {{ t('teacher_portal.checking') }}
            </span>
            <!-- Attendance already marked -->
            <template v-else-if="sessionAttMap[slot.id].total > 0">
              <div class="flex items-center gap-1 mb-1">
                <span class="text-xs text-green-600 dark:text-green-400 font-semibold flex items-center gap-1">
                  <i class="pi pi-check-circle text-[10px]"></i> {{ t('teacher_portal.attendance_marked') }}
                </span>
              </div>
              <div class="flex gap-2 text-[10px]">
                <span class="text-green-600 dark:text-green-400 font-medium">{{ sessionAttMap[slot.id].present }} {{ t('teacher_portal.present') }}</span>
                <span v-if="sessionAttMap[slot.id].absent > 0" class="text-red-500 dark:text-red-400 font-medium">{{ sessionAttMap[slot.id].absent }} {{ t('teacher_portal.absent') }}</span>
                <span v-if="sessionAttMap[slot.id].late > 0" class="text-amber-500 dark:text-amber-400 font-medium">{{ sessionAttMap[slot.id].late }} {{ t('teacher_portal.late') }}</span>
              </div>
            </template>
            <!-- Not yet marked -->
            <span v-else class="text-xs text-primary-600 dark:text-primary-400 font-medium flex items-center gap-1">
              <i class="pi pi-check-circle text-[10px]"></i>
              {{ t('teacher_portal.take_attendance') }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Classes grid -->
    <div class="mb-4 flex items-center justify-between">
      <h2 class="text-xl font-semibold text-surface-800 dark:text-surface-100">{{ t('teacher_portal.my_classes') }}</h2>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <ProgressSpinner style="width: 48px; height: 48px" />
    </div>

    <div v-else-if="classes.length === 0" class="flex flex-col items-center py-16 text-surface-400">
      <i class="pi pi-building text-5xl mb-3"></i>
      <p class="text-lg">{{ t('teacher_portal.no_classes') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="cls in classes"
        :key="cls.id"
        class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-2xl shadow-sm hover:shadow-md transition-shadow cursor-pointer group"
        @click="goToClass(cls)"
      >
        <div class="p-5">
          <!-- Class name & level badge -->
          <div class="flex items-start justify-between mb-3">
            <h3 class="text-lg font-bold text-surface-900 dark:text-surface-0 group-hover:text-primary-600 transition-colors">
              {{ cls.name }}
            </h3>
            <span class="text-xs font-semibold px-2 py-1 rounded-full" :class="getLevelColor(cls.level)">
              {{ cls.level }}
            </span>
          </div>

          <!-- Academic year -->
          <p v-if="cls.academic_year" class="text-sm text-surface-500 dark:text-surface-400 mb-3">
            <i class="pi pi-calendar-plus mr-1"></i>{{ cls.academic_year }}
          </p>

          <!-- Subjects taught -->
          <div v-if="cls.subjects && cls.subjects.length > 0" class="flex flex-wrap gap-1 mb-4">
            <span
              v-for="subject in cls.subjects"
              :key="subject.id"
              class="text-xs bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 px-2 py-0.5 rounded-full"
            >
              {{ subject.name }}
            </span>
          </div>

          <!-- Divider -->
          <div class="border-t border-surface-100 dark:border-surface-700 pt-3 flex items-center justify-between">
            <div class="flex items-center gap-1 text-surface-600 dark:text-surface-300 text-sm">
              <i class="pi pi-users text-xs"></i>
              <span>{{ cls.students_count ?? (cls.students?.length ?? 0) }} {{ t('teacher_portal.students') }}</span>
            </div>
            <div class="flex gap-2">

              <Button
                icon="pi pi-star"
                size="small"
                :label="t('teacher_portal.grades')"
                text
                severity="info"
                @click.stop="router.push({ name: 'teacher-class', params: { classId: cls.id }, query: { tab: 'grades' } })"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Schedule Dialog -->
    <Dialog
      v-model:visible="scheduleDialog"
      :header="t('teacher_portal.my_weekly_schedule')"
      :style="{ width: '90vw', maxWidth: '1100px' }"
      :modal="true"
      :closable="true"
    >
      <div v-if="scheduleLoading" class="flex justify-center py-10">
        <ProgressSpinner style="width: 40px; height: 40px" />
      </div>

      <div v-else-if="Object.keys(scheduleByDay).length === 0" class="flex flex-col items-center py-10 text-surface-400">
        <i class="pi pi-calendar text-4xl mb-2"></i>
        <p>{{ t('teacher_portal.no_schedule') }}</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr>
              <th class="border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 p-2 text-left w-20 text-surface-500">
                {{ t('common.time') }}
              </th>
              <th
                v-for="(day, idx) in translatedWeekDays"
                :key="WEEK_DAYS[idx]"
                class="border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 p-2 text-center font-semibold text-surface-700 dark:text-surface-200"
              >
                {{ day }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="hour in scheduleHours" :key="hour">
              <td class="border border-surface-200 dark:border-surface-700 p-2 text-surface-400 text-xs text-center whitespace-nowrap">
                {{ String(hour).padStart(2, '0') }}:00
              </td>
              <td
                v-for="day in WEEK_DAYS"
                :key="day"
                class="border border-surface-200 dark:border-surface-700 p-1 align-top min-w-28"
              >
                <div
                  v-for="slot in getSlotForDayTime(day, hour)"
                  :key="slot.id"
                  class="bg-primary-100 dark:bg-primary-900/40 text-primary-800 dark:text-primary-200 rounded-lg p-2 mb-1 text-xs"
                >
                  <div class="font-semibold">{{ getSubjectName(slot) }}</div>
                  <div class="text-primary-600 dark:text-primary-400">{{ getClassName(slot) }}</div>
                  <div class="text-primary-500 dark:text-primary-500 mt-0.5">
                    {{ formatTime(slot.start_time) }} - {{ formatTime(slot.end_time) }}
                  </div>
                  <div v-if="slot.room" class="text-primary-500 dark:text-primary-500">
                    <i class="pi pi-map-marker text-[10px]"></i> {{ slot.room }}
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Dialog>
  </div>
</template>
