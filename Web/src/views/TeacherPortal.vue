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

const WEEK_DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const translatedWeekDays = computed(() => [
  t('common.sunday'),
  t('common.monday'),
  t('common.tuesday'),
  t('common.wednesday'),
  t('common.thursday'),
]);

const currentUser = computed(() => apiService.getUser());
const teacher = computed(() => currentUser.value?.teacher ?? null);

const teacherName = computed(() => {
  if (teacher.value) {
    return `${teacher.value.first_name} ${teacher.value.last_name}`;
  }
  return currentUser.value?.username ?? t('teacher_portal.teacher_fallback');
});

const levelColors: Record<string, string> = {
  default: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300 border border-blue-500/20',
  primary: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300 border border-green-500/20',
  middle: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-300 border border-yellow-500/20',
  high: 'bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-300 border border-purple-500/20',
  college: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300 border border-red-500/20',
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
      const day = slot.day ?? slot.assignment?.day ?? t('teacher_portal.unknown_day');
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
  return slot.assignment?.subject?.name ?? slot.subject?.name ?? t('teacher_portal.subject_fallback');
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
  <div class="p-4 md:p-8 max-w-[1600px] mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
      <div class="animate-fade-in">
        <div class="flex items-center gap-2 mb-2">
          <span class="px-3 py-1 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 text-xs font-bold uppercase tracking-wider">
            {{ t('teacher_portal.subtitle') }}
          </span>
        </div>
        <h1 class="text-4xl md:text-5xl font-black text-surface-900 dark:text-surface-0 tracking-tight">
          {{ t('teacher_portal.title', { name: teacherName }) }}
        </h1>
      </div>
      <div class="flex items-center gap-3">
        <Button
          :label="t('teacher_portal.my_schedule')"
          icon="pi pi-calendar"
          severity="primary"
          raised
          class="rounded-xl px-6 py-3 font-bold shadow-lg shadow-primary-500/20"
          @click="openSchedule"
        />
      </div>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
      <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/40 rounded-3xl p-6 flex items-center gap-5 premium-shadow transition-premium hover:-translate-y-1 border border-blue-200/50 dark:border-blue-500/10">
        <div class="bg-white/80 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-2xl p-4 shadow-sm backdrop-blur-md">
          <i class="pi pi-building text-3xl"></i>
        </div>
        <div>
          <div class="text-4xl font-black text-blue-900 dark:text-blue-100 leading-none mb-1">{{ classes.length }}</div>
          <div class="text-blue-700/70 dark:text-blue-300/70 font-bold uppercase text-[10px] tracking-widest">{{ t('teacher_portal.classes') }}</div>
        </div>
      </div>
      <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-900/40 rounded-3xl p-6 flex items-center gap-5 premium-shadow transition-premium hover:-translate-y-1 border border-green-200/50 dark:border-green-500/10">
        <div class="bg-white/80 dark:bg-green-500/10 text-green-600 dark:text-green-400 rounded-2xl p-4 shadow-sm backdrop-blur-md">
          <i class="pi pi-users text-3xl"></i>
        </div>
        <div>
          <div class="text-4xl font-black text-green-900 dark:text-green-100 leading-none mb-1">
            {{ classes.reduce((s, c) => s + (c.students_count ?? 0), 0) }}
          </div>
          <div class="text-green-700/70 dark:text-green-300/70 font-bold uppercase text-[10px] tracking-widest">{{ t('teacher_portal.total_students') }}</div>
        </div>
      </div>
      <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-900/40 rounded-3xl p-6 flex items-center gap-5 premium-shadow transition-premium hover:-translate-y-1 sm:col-span-2 lg:col-span-1 border border-purple-200/50 dark:border-purple-500/10">
        <div class="bg-white/80 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-2xl p-4 shadow-sm backdrop-blur-md">
          <i class="pi pi-book text-3xl"></i>
        </div>
        <div>
          <div class="text-4xl font-black text-purple-900 dark:text-purple-100 leading-none mb-1">
            {{ [...new Set(classes.flatMap(c => (c.subjects ?? []).map((s: any) => s.id)))].length }}
          </div>
          <div class="text-purple-700/70 dark:text-purple-300/70 font-bold uppercase text-[10px] tracking-widest">{{ t('teacher_portal.subjects_taught') }}</div>
        </div>
      </div>
    </div>

    <!-- Today's Sessions -->
    <div class="mb-12">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center text-surface-600 dark:text-surface-400">
            <i class="pi pi-clock text-xl"></i>
          </div>
          <div>
            <h2 class="text-2xl font-black text-surface-900 dark:text-surface-0">{{ t('teacher_portal.todays_sessions') }}</h2>
            <p class="text-sm text-surface-500 font-medium">{{ t('common.' + todayDayName().toLowerCase()) }}</p>
          </div>
        </div>
      </div>

      <div v-if="sessionsLoading" class="flex gap-4 overflow-x-auto pb-4 modern-scroll">
        <div v-for="n in 3" :key="n" class="h-40 min-w-[280px] bg-surface-100 dark:bg-surface-800 rounded-3xl animate-pulse"></div>
      </div>

      <div v-else-if="todaysSessions.length === 0" class="flex flex-col items-center justify-center py-12 bg-surface-50 dark:bg-surface-800/40 rounded-3xl border-2 border-dashed border-surface-200 dark:border-surface-700 text-surface-400">
        <i class="pi pi-calendar-times text-4xl mb-4 opacity-20"></i>
        <span class="font-bold tracking-wide">{{ t('teacher_portal.no_sessions_today') }}</span>
      </div>

      <div v-else class="flex gap-6 overflow-x-auto pb-6 modern-scroll snap-x">
        <div
          v-for="slot in todaysSessions"
          :key="slot.id"
          class="group flex flex-col justify-between bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-3xl p-6 min-w-[280px] max-w-[320px] shadow-sm hover:shadow-xl hover:border-primary-500 transition-premium cursor-pointer snap-start relative overflow-hidden"
          @click="openSessionAttendance(slot)"
        >
          <div class="absolute top-0 right-0 w-24 h-24 bg-primary-500/5 rounded-full -mr-8 -mt-8 transition-transform group-hover:scale-150"></div>
          
          <div class="relative z-10">
            <div class="flex items-start justify-between mb-4">
              <span class="px-3 py-1 rounded-lg bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 text-xs font-black uppercase tracking-wider">
                {{ formatTime(slot.start_time) }}
              </span>
              <div v-if="slot.room" class="flex items-center gap-1 text-xs text-surface-400 font-medium">
                <i class="pi pi-map-marker"></i>
                {{ slot.room }}
              </div>
            </div>
            
            <h3 class="text-xl font-black text-surface-900 dark:text-surface-0 mb-1 group-hover:text-primary-600 transition-colors">
              {{ getSubjectName(slot) }}
            </h3>
            <p class="text-surface-500 font-bold mb-4">{{ getClassName(slot) }}</p>
          </div>

          <div class="relative z-10 flex items-center justify-between pt-4 border-t border-surface-100 dark:border-surface-700">
            <div class="text-xs text-surface-400 font-bold">
              {{ formatTime(slot.end_time) }} {{ t('common.end') }}
            </div>
            <i class="pi pi-arrow-right text-primary-500 opacity-0 group-hover:opacity-100 transition-all transform translate-x-2 group-hover:translate-x-0"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Classes grid -->
    <div class="mb-6">
      <div class="flex items-center gap-3 mb-8">
        <div class="w-10 h-10 rounded-xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center text-surface-600 dark:text-surface-400">
          <i class="pi pi-th-large text-xl"></i>
        </div>
        <h2 class="text-2xl font-black text-surface-900 dark:text-surface-0">{{ t('teacher_portal.my_classes') }}</h2>
      </div>

      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <div v-for="n in 6" :key="n" class="h-64 bg-surface-100 dark:bg-surface-800 rounded-3xl animate-pulse"></div>
      </div>

      <div v-else-if="classes.length === 0" class="flex flex-col items-center justify-center py-24 text-surface-400">
        <i class="pi pi-building text-6xl mb-6 opacity-20"></i>
        <p class="text-xl font-bold">{{ t('teacher_portal.no_classes') }}</p>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <div
          v-for="cls in classes"
          :key="cls.id"
          class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-[2rem] shadow-sm hover:shadow-2xl transition-premium cursor-pointer group relative overflow-hidden hover:border-primary-500/50"
          @click="goToClass(cls)"
        >
          <!-- Background decoration -->
          <div class="absolute -top-10 -right-10 w-32 h-32 bg-surface-50 dark:bg-surface-900 rounded-full transition-transform group-hover:scale-150 group-hover:bg-primary-50 dark:group-hover:bg-primary-900/10"></div>
          
          <div class="p-8 relative z-10">
            <!-- Class name & level badge -->
            <div class="flex items-start justify-between mb-6">
              <div>
                <h3 class="text-2xl font-black text-surface-900 dark:text-surface-0 group-hover:text-primary-600 transition-colors leading-tight mb-1">
                  {{ cls.name }}
                </h3>
                <p v-if="cls.academic_year" class="text-xs font-bold text-surface-400 uppercase tracking-widest">
                  {{ cls.academic_year }}
                </p>
              </div>
              <span class="text-[10px] font-black px-3 py-1.5 rounded-xl uppercase tracking-tighter shadow-sm" :class="getLevelColor(cls.level)">
                {{ t('levels.' + (cls.level?.toLowerCase() || 'default')) }}
              </span>
            </div>

            <!-- Subjects taught -->
            <div v-if="cls.subjects && cls.subjects.length > 0" class="flex flex-wrap gap-2 mb-8">
              <span
                v-for="subject in cls.subjects"
                :key="subject.id"
                class="text-[11px] font-bold bg-surface-100 dark:bg-surface-700 text-surface-600 dark:text-surface-300 px-3 py-1 rounded-lg"
              >
                {{ subject.name }}
              </span>
            </div>

            <!-- Footer Stats -->
            <div class="flex items-center justify-between pt-6 border-t border-surface-100 dark:border-surface-700">
              <div class="flex items-center gap-3">
                <div class="flex -space-x-2">
                   <div v-for="i in 3" :key="i" class="w-8 h-8 rounded-full border-2 border-white dark:border-surface-800 bg-surface-200 dark:bg-surface-700 flex items-center justify-center text-[10px] font-bold text-surface-500">
                     <i class="pi pi-user"></i>
                   </div>
                </div>
                <span class="text-sm font-black text-surface-700 dark:text-surface-200">
                  {{ cls.students_count ?? (cls.students?.length ?? 0) }} <span class="text-surface-400 font-bold ml-1 uppercase text-[10px] tracking-widest">{{ t('teacher_portal.students') }}</span>
                </span>
              </div>
              <Button
                icon="pi pi-chart-bar"
                size="small"
                text
                severity="primary"
                class="rounded-xl font-black uppercase text-[11px] tracking-wider"
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
      :style="{ width: '95vw', maxWidth: '1200px' }"
      :modal="true"
      :closable="true"
      class="premium-dialog"
      contentClass="modern-scroll"
    >
      <div v-if="scheduleLoading" class="flex justify-center py-20">
        <ProgressSpinner style="width: 50px; height: 50px" />
      </div>

      <div v-else-if="Object.keys(scheduleByDay).length === 0" class="flex flex-col items-center py-20 text-surface-400">
        <i class="pi pi-calendar text-6xl mb-6 opacity-20"></i>
        <p class="font-bold text-xl">{{ t('teacher_portal.no_schedule') }}</p>
      </div>

      <div v-else>
        <!-- Mobile Schedule View (List) -->
        <div class="md:hidden space-y-8">
           <div v-for="day in WEEK_DAYS" :key="day" class="animate-fade-in">
              <h3 class="text-lg font-black text-primary-600 dark:text-primary-400 mb-4 border-b border-primary-100 dark:border-primary-900/30 pb-2">
                {{ t(`common.${day.toLowerCase()}`) }}
              </h3>
              <div v-if="(scheduleByDay[day] ?? []).length === 0" class="text-sm text-surface-400 italic py-2">
                {{ t('teacher_portal.no_sessions') }}
              </div>
              <div v-else class="space-y-3">
                 <div v-for="slot in scheduleByDay[day]" :key="slot.id" class="bg-surface-50 dark:bg-surface-900/50 p-4 rounded-2xl border border-surface-200 dark:border-surface-700">
                    <div class="flex justify-between items-start mb-2">
                       <span class="font-black text-surface-900 dark:text-surface-0">{{ getSubjectName(slot) }}</span>
                       <span class="text-[10px] font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 px-2 py-0.5 rounded">{{ formatTime(slot.start_time) }}</span>
                    </div>
                    <div class="text-sm text-surface-500 font-medium mb-2">{{ getClassName(slot) }}</div>
                    <div v-if="slot.room" class="text-xs text-surface-400 flex items-center gap-1">
                       <i class="pi pi-map-marker"></i> {{ slot.room }}
                    </div>
                 </div>
              </div>
           </div>
        </div>

        <!-- Desktop Schedule View (Table) -->
        <div class="hidden md:block overflow-x-auto rounded-3xl border border-surface-200 dark:border-surface-700">
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr>
                <th class="bg-surface-50 dark:bg-surface-900 p-4 text-center w-24 border-b border-r border-surface-200 dark:border-surface-700 font-black text-surface-400 uppercase text-[10px] tracking-widest">
                  {{ t('common.time') }}
                </th>
                <th
                  v-for="(day, idx) in translatedWeekDays"
                  :key="WEEK_DAYS[idx]"
                  class="bg-surface-50 dark:bg-surface-900 p-4 text-center font-black text-surface-700 dark:text-surface-200 border-b border-r border-surface-200 dark:border-surface-700 uppercase text-xs tracking-widest last:border-r-0"
                >
                  {{ day }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="hour in scheduleHours" :key="hour">
                <td class="p-4 bg-surface-50/50 dark:bg-surface-900/50 text-surface-400 font-black text-[10px] text-center border-b border-r border-surface-200 dark:border-surface-700">
                  {{ String(hour).padStart(2, '0') }}:00
                </td>
                <td
                  v-for="day in WEEK_DAYS"
                  :key="day"
                  class="p-2 align-top min-w-[150px] border-b border-r border-surface-200 dark:border-surface-700 last:border-r-0 h-24"
                >
                  <div
                    v-for="slot in getSlotForDayTime(day, hour)"
                    :key="slot.id"
                    class="bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 text-primary-800 dark:text-primary-200 rounded-xl p-3 mb-2 shadow-sm transition-premium hover:shadow-md hover:scale-[1.02]"
                  >
                    <div class="font-black text-xs mb-1">{{ getSubjectName(slot) }}</div>
                    <div class="text-[10px] font-bold opacity-70 mb-2">{{ getClassName(slot) }}</div>
                    <div class="flex items-center justify-between text-[9px] font-black uppercase opacity-60">
                      <span>{{ formatTime(slot.start_time) }} - {{ formatTime(slot.end_time) }}</span>
                      <span v-if="slot.room"><i class="pi pi-map-marker mr-0.5"></i>{{ slot.room }}</span>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.modern-scroll::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
.modern-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.modern-scroll::-webkit-scrollbar-thumb {
  background: rgba(var(--p-primary-500-rgb), 0.1);
  border-radius: 10px;
}
.app-dark .modern-scroll::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
}

.animate-fade-in {
  animation: fadeIn 0.5s ease-out forwards;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

:deep(.premium-dialog) {
  border-radius: 2rem;
  overflow: hidden;
  border: none;
}

:deep(.premium-dialog .p-dialog-header) {
  padding: 2rem;
}
</style>
