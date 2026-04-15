<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import apiService from '@/service/ApiService';
import ApiService from '@/service/ApiService';
import SupervisorService from '@/service/SupervisorService';
import AttendanceService from '@/service/AttendanceService';

const toast = useToast();
const { t } = useI18n();

// ─── State ────────────────────────────────────────────────
const loading = ref(false);
const activeTab = ref('0');

const currentUser = computed(() => apiService.getUser());
const supervisor = computed(() => currentUser.value?.supervisor ?? null);

const supervisorName = computed(() => {
  if (supervisor.value) {
    return `${supervisor.value.first_name} ${supervisor.value.last_name}`;
  }
  return currentUser.value?.username ?? 'Supervisor';
});

// Dashboard data
const dashboardData = ref<any[]>([]);
const dashboardLoading = ref(false);

// Student detail dialog (attendance drill-down)
const studentDialog = ref(false);
const selectedClass = ref<any>(null);
const excusing = ref<number[]>([]);

// Schedule page
const myClasses = ref<any[]>([]);
const scheduleDialog = ref(false);
const scheduleLoading = ref(false);
const scheduleData = ref<any>(null);
const selectedScheduleClass = ref<any>(null);

// ─── Attendance dialog (same logic as TeacherPanel) ────────
const attendanceDialog = ref(false);
const attendanceClass = ref<any>(null);
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

// ─── Computed ─────────────────────────────────────────────
const currentTime = computed(() => {
  const now = new Date();
  return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
});

const todayStr = computed(() => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

const attendanceSubjectOptions = computed(() => attendanceClass.value?.subjects || []);

// Supervisor dashboard stats
const totalClassesCount = computed(() => myClasses.value?.length || 0);

const totalStudentsCount = computed(() => {
  return dashboardData.value.reduce((acc, curr) => acc + (curr.total_students || 0), 0);
});

const totalAbsentTodayCount = computed(() => {
  return dashboardData.value.reduce((acc, curr) => acc + (curr.absent || 0), 0);
});

// ─── Load ─────────────────────────────────────────────────
async function loadDashboard() {
  dashboardLoading.value = true;
  try {
    dashboardData.value = await SupervisorService.getDashboard();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('supervisor.load_dashboard_error'), life: 3000 });
  } finally {
    dashboardLoading.value = false;
  }
}

async function loadClasses() {
  loading.value = true;
  try {
    myClasses.value = await SupervisorService.getMyClasses();
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('supervisor.load_classes_error'), life: 3000 });
  } finally {
    loading.value = false;
  }
}

// ─── Dashboard: Student Drill-down ────────────────────────
function openClassDetail(cls: any) {
  selectedClass.value = cls;
  studentDialog.value = true;
}

function getStudentAttendances(studentId: number): any[] {
  if (!selectedClass.value?.attendances) return [];
  return selectedClass.value.attendances.filter((a: any) => a.student_id === studentId);
}

async function excuseStudent(attId: number) {
  excusing.value.push(attId);
  try {
    await AttendanceService.updateAttendance(attId, { status: 'excused' });
    // Update local data
    const att = selectedClass.value.attendances.find((a: any) => a.id === attId);
    if (att) att.status = 'excused';
    // Also update dashboard summary
    const dashItem = dashboardData.value.find((d: any) => d.class_id === selectedClass.value.class_id);
    if (dashItem) {
      dashItem.absent = Math.max(0, dashItem.absent - 1);
      dashItem.excused = (dashItem.excused || 0) + 1;
    }
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('supervisor.excuse_success'), life: 2000 });
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('supervisor.excuse_error'), life: 3000 });
  } finally {
    excusing.value = excusing.value.filter(id => id !== attId);
  }
}

// ─── Schedule ─────────────────────────────────────────────
const isMarkingAttendanceForClass = ref(false);

async function openSchedule(cls: any, isMarking = false) {
  isMarkingAttendanceForClass.value = isMarking;
  selectedScheduleClass.value = cls;
  scheduleDialog.value = true;
  scheduleLoading.value = true;
  try {
    scheduleData.value = await SupervisorService.getClassScheduleToday(cls.id);
  } catch {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('supervisor.load_schedule_error'), life: 3000 });
  } finally {
    scheduleLoading.value = false;
  }
}

function isCurrentSession(schedule: any): boolean {
  const now = currentTime.value;
  const start = schedule.start_time?.slice(0, 5) ?? '';
  const end = schedule.end_time?.slice(0, 5) ?? '';
  return now >= start && now <= end;
}

function formatTime(time: string): string {
  return time ? time.slice(0, 5) : '';
}

// ─── Attendance (same logic as TeacherPanel) ──────────────
const selectedSessionData = ref<any>(null);

async function openAttendanceForSession(session: any) {
  selectedSessionData.value = session;
  attendanceClass.value = selectedScheduleClass.value;
  attendanceSubject.value = session.assignment?.subject ?? session.subject;
  attendanceDate.value = new Date();
  attendanceDialog.value = true;
  await loadAttendanceRows();
}

async function openAttendance(cls: any) {
  // Automatically open schedule dialog to pick a session
  await openSchedule(cls, true);
}

async function loadAttendanceRows() {
  if (!attendanceClass.value || !selectedSessionData.value) return;
  attendanceLoading.value = true;
  try {
    const dateStr = formatDate(attendanceDate.value);

    // Build rows from students
    const students: any[] = attendanceClass.value.students || [];

    // Load existing attendance specifically for this session schedule or fallback to day's records
    const dayAttendances = await AttendanceService.getClassAttendances(attendanceClass.value.id, {
      start_date: dateStr,
      end_date: dateStr,
    } as any);

    // Group existing records by student purely for this session
    const sessionMap = new Map<number, any>();
    // Group last known record for today for fallback
    const latestTodayMap = new Map<number, any>();

    const sessionId = selectedSessionData.value.id;
    const subjectId = attendanceSubject.value?.id;

    dayAttendances.forEach((rec) => {
      // Keep track of the latest record of the day by ID for fallback
      latestTodayMap.set(rec.student_id, rec);

      // Exact match for the session
      if (rec.schedule_id === sessionId) {
        sessionMap.set(rec.student_id, rec);
      } else if (!rec.schedule_id && rec.subject_id === subjectId) {
        // Fallback for logic where teacher marks attendance by subject without schedule id
        sessionMap.set(rec.student_id, rec);
      }
    });

    attendanceRows.value = students.map((student) => {
      const existing = sessionMap.get(student.id);
      const fallback = latestTodayMap.get(student.id);

      // If existing session record, it shows as already marked.
      // If not, we fallback to the last marked status for the student today.
      const statusToUse = existing ? existing.status : (fallback ? fallback.status : 'present');
      const reasonToUse = existing ? (existing.reason || '') : (fallback ? (fallback.reason || '') : '');

      return {
        student,
        status: statusToUse,
        reason: reasonToUse,
        existingId: existing ? existing.id : null,
      };
    });
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('supervisor.load_students_error'), life: 3000 });
  } finally {
    attendanceLoading.value = false;
  }
}

async function onAttendanceDateChange() {
  await loadAttendanceRows();
}

async function onAttendanceSubjectChange() {
  await loadAttendanceRows();
}

async function saveAttendance() {
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
      teacher_id: null as any, // supervisor marks, no teacher
      schedule_id: selectedSessionData.value?.id ?? null,
      status: row.status as any,
      reason: row.reason || null,
      date: dateStr,
    }));
    await AttendanceService.saveClassAttendance(entries, existingMap);

    toast.add({ severity: 'success', summary: t('common.success'), detail: t('supervisor.attendance_saved'), life: 3000 });
    attendanceDialog.value = false;

    // Refresh dashboard
    await loadDashboard();
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: err?.response?.data?.message || t('supervisor.save_attendance_error'), life: 4000 });
  } finally {
    savingAttendance.value = false;
  }
}

// ─── Helpers ──────────────────────────────────────────────
function formatDate(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

// ─── Lifecycle ────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([loadDashboard(), loadClasses()]);
});
</script>

<template>
  <div class="flex flex-col gap-4 w-full p-4 md:p-6">
    <Toast />

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-2">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-1">
          {{ t('supervisor.title') }} - {{ supervisorName }}
        </h1>
        <p class="text-surface-500 dark:text-surface-400">{{ t('supervisor.subtitle') }}</p>
      </div>
      <div>
        <Button 
          icon="pi pi-refresh" 
          severity="secondary" 
          outlined 
          :label="t('common.refresh', 'Refresh')" 
          :loading="dashboardLoading || loading" 
          @click="() => { loadDashboard(); loadClasses(); }" 
        />
      </div>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 flex items-center gap-3 border border-blue-100 dark:border-blue-800">
        <div class="bg-blue-500 text-white rounded-lg p-3">
          <i class="pi pi-building text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ totalClassesCount }}</div>
          <div class="text-sm text-blue-600 dark:text-blue-400">{{ t('supervisor.monitored_classes', 'Monitored Classes') }}</div>
        </div>
      </div>
      <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 flex items-center gap-3 border border-green-100 dark:border-green-800">
        <div class="bg-green-500 text-white rounded-lg p-3">
          <i class="pi pi-users text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ totalStudentsCount }}</div>
          <div class="text-sm text-green-600 dark:text-green-400">{{ t('supervisor.total_students', 'Total Students') }}</div>
        </div>
      </div>
      <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-4 flex items-center gap-3 border border-red-100 dark:border-red-800">
        <div class="bg-red-500 text-white rounded-lg p-3">
          <i class="pi pi-chart-line text-xl"></i>
        </div>
        <div>
          <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ totalAbsentTodayCount }}</div>
          <div class="text-sm text-red-600 dark:text-red-400">{{ t('supervisor.absent_today', 'Absent Today') }}</div>
        </div>
      </div>
    </div>

    <div class="card p-0">
      <Tabs v-model:value="activeTab">
        <TabList>
          <Tab value="0">
            <span class="flex items-center gap-2">
              <i class="pi pi-chart-bar"></i>
              <span>{{ t('supervisor.attendance_dashboard') }}</span>
            </span>
          </Tab>
          <Tab value="1">
            <span class="flex items-center gap-2">
              <i class="pi pi-calendar"></i>
              <span>{{ t('supervisor.class_schedules') }}</span>
            </span>
          </Tab>
        </TabList>

        <TabPanels>
          <!-- ═══════════ TAB 0: ATTENDANCE DASHBOARD ═══════════ -->
          <TabPanel value="0">
            <div class="pt-4">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="text-lg font-semibold m-0">{{ t('supervisor.todays_attendance') }}</h3>
                  <p class="text-sm text-surface-500 mt-1 mb-0">{{ todayStr }}</p>
                </div>
                <Button icon="pi pi-refresh" text rounded :loading="dashboardLoading" @click="loadDashboard" />
              </div>

              <div v-if="dashboardLoading && dashboardData.length === 0" class="flex justify-center py-16">
                <ProgressSpinner style="width: 40px; height: 40px" />
              </div>

              <div v-else-if="dashboardData.length === 0" class="flex flex-col items-center py-20 text-surface-400">
                <i class="pi pi-building text-5xl mb-3"></i>
                <p class="text-lg">{{ t('supervisor.no_classes') }}</p>
              </div>

              <DataTable
                v-else
                :value="dashboardData"
                stripedRows
                removableSort
                class="mt-2 p-datatable-sm w-full"
              >
                <Column field="class_name" :header="t('supervisor.class_name')" sortable style="min-width: 160px">
                  <template #body="{ data }">
                    <div>
                      <div class="font-medium">{{ data.class_name }}</div>
                      <div v-if="data.level" class="text-xs text-surface-400">{{ data.level }}</div>
                    </div>
                  </template>
                </Column>

                <Column field="total_students" :header="t('common.students')" sortable style="width: 100px" class="text-center">
                  <template #body="{ data }">
                    <span class="font-semibold">{{ data.total_students }}</span>
                  </template>
                </Column>

                <Column field="absent" :header="t('common.absent')" sortable style="width: 100px" class="text-center">
                  <template #body="{ data }">
                    <Tag v-if="data.absent > 0" :value="String(data.absent)" severity="danger" class="text-xs" />
                    <span v-else class="text-surface-300">0</span>
                  </template>
                </Column>

                <Column field="late" :header="t('common.late')" sortable style="width: 100px" class="text-center">
                  <template #body="{ data }">
                    <Tag v-if="data.late > 0" :value="String(data.late)" severity="warn" class="text-xs" />
                    <span v-else class="text-surface-300">0</span>
                  </template>
                </Column>

                <Column field="excused" :header="t('common.excused')" sortable style="width: 100px" class="text-center">
                  <template #body="{ data }">
                    <Tag v-if="data.excused > 0" :value="String(data.excused)" severity="info" class="text-xs" />
                    <span v-else class="text-surface-300">0</span>
                  </template>
                </Column>

                <Column :header="t('supervisor.marked')" style="width: 110px" class="text-center">
                  <template #body="{ data }">
                    <span class="text-sm text-surface-500">{{ data.marked }} / {{ data.total_students }}</span>
                  </template>
                </Column>

                <Column :header="t('common.actions')" style="width: 100px" :exportable="false">
                  <template #body="{ data }">
                    <Button icon="pi pi-eye" outlined rounded size="small" @click="openClassDetail(data)" v-tooltip.top="'Details'" />
                  </template>
                </Column>
              </DataTable>
            </div>
          </TabPanel>

          <!-- ═══════════ TAB 1: CLASS SCHEDULES & ATTENDANCE ═══════════ -->
          <TabPanel value="1">
            <div class="pt-4">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="text-lg font-semibold m-0">{{ t('supervisor.class_schedules') }}</h3>
                  <p class="text-sm text-surface-500 mt-1 mb-0">{{ t('supervisor.schedule_subtitle') }}</p>
                </div>
              </div>

              <div v-if="loading" class="flex justify-center py-16">
                <ProgressSpinner style="width: 40px; height: 40px" />
              </div>

              <div v-else-if="myClasses.length === 0" class="flex flex-col items-center py-20 text-surface-400">
                <i class="pi pi-building text-5xl mb-3"></i>
                <p class="text-lg">{{ t('supervisor.no_classes') }}</p>
              </div>

              <!-- Class Cards (like TeacherPanel) -->
              <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="cls in myClasses" :key="cls.id" class="p-5 border rounded-xl bg-surface-0 dark:bg-surface-900 border-surface-200 dark:border-surface-700 shadow-sm flex flex-col gap-4 transition-shadow hover:shadow-md">
                  <!-- Class Header -->
                  <div class="flex items-start justify-between">
                    <div>
                      <h3 class="text-xl font-bold m-0 text-surface-900 dark:text-surface-0">{{ cls.name }}</h3>
                      <span class="text-surface-500 text-sm"><i class="pi pi-bookmark text-xs mr-1"></i>{{ cls.level || 'N/A' }}</span>
                    </div>
                    <Tag :value="cls.academic_year || 'N/A'" severity="info" rounded class="px-3" />
                  </div>

                  <!-- Stats Row -->
                  <div class="flex gap-4 bg-surface-50 dark:bg-surface-800 p-3 rounded-lg">
                    <div class="flex items-center gap-2">
                      <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 flex items-center justify-center">
                        <i class="pi pi-users text-sm"></i>
                      </div>
                      <div class="flex flex-col">
                        <span class="font-bold text-surface-900 dark:text-surface-0 leading-none">{{ cls.students?.length ?? 0 }}</span>
                        <span class="text-surface-500 text-xs mt-1">{{ t('common.students') }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Action Buttons -->
                  <div class="flex gap-3 mt-auto pt-2">
                    <Button
                      :label="t('supervisor.mark_attendance')"
                      icon="pi pi-check-square"
                      class="flex-1"
                      size="small"
                      @click="openAttendance(cls)"
                    />
                    <Button
                      :label="t('supervisor.view_schedule')"
                      icon="pi pi-calendar"
                      severity="secondary"
                      outlined
                      class="flex-1"
                      size="small"
                      @click="openSchedule(cls)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>

    <!-- ═══════════ DIALOG: Student Attendance Detail (Dashboard drill-down) ═══════════ -->
    <Dialog
      v-model:visible="studentDialog"
      :header="(selectedClass?.class_name ?? '') + ' — ' + t('supervisor.student_attendance')"
      :style="{ width: '90vw', maxWidth: '800px' }"
      modal
      :draggable="false"
    >
      <div v-if="selectedClass" class="space-y-2">
        <!-- Summary chips -->
        <div class="flex flex-wrap gap-3 mb-4 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
          <span class="flex items-center gap-1.5 text-sm">
            <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
            <span class="font-semibold text-green-700 dark:text-green-300">{{ selectedClass.present }}</span>
            <span class="text-surface-500">{{ t('common.present') }}</span>
          </span>
          <span class="flex items-center gap-1.5 text-sm">
            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
            <span class="font-semibold text-red-700 dark:text-red-300">{{ selectedClass.absent }}</span>
            <span class="text-surface-500">{{ t('common.absent') }}</span>
          </span>
          <span class="flex items-center gap-1.5 text-sm">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <span class="font-semibold text-amber-700 dark:text-amber-300">{{ selectedClass.late }}</span>
            <span class="text-surface-500">{{ t('common.late') }}</span>
          </span>
          <span class="flex items-center gap-1.5 text-sm">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
            <span class="font-semibold text-blue-700 dark:text-blue-300">{{ selectedClass.excused }}</span>
            <span class="text-surface-500">{{ t('common.excused') }}</span>
          </span>
        </div>

        <!-- Student list -->
        <DataTable
          :value="selectedClass.students || []"
          stripedRows
          scrollable
          scrollHeight="400px"
          class="p-datatable-sm w-full"
        >
          <Column header="#" style="width: 50px">
            <template #body="{ index }">
              <span class="text-surface-500">{{ Number(index) + 1 }}</span>
            </template>
          </Column>
          <Column :header="t('common.name')">
            <template #body="{ data }">
              <div class="font-medium">{{ data.first_name }} {{ data.last_name }}</div>
            </template>
          </Column>
          <Column :header="t('common.status')" style="width: 250px">
            <template #body="{ data }">
              <div v-if="getStudentAttendances(data.id).length > 0" class="flex flex-col gap-2">
                <div 
                  v-for="rec in getStudentAttendances(data.id)" 
                  :key="rec.id" 
                  class="flex items-center justify-between text-[11px] p-1 border rounded"
                >
                  <span class="text-surface-600 dark:text-surface-300 font-medium truncate max-w-[100px] flex-1" v-tooltip.top="rec.subject?.name ?? rec.schedule?.assignment?.subject?.name ?? t('common.session')">
                    {{ rec.subject?.name ?? rec.schedule?.assignment?.subject?.name ?? t('common.session') }}
                  </span>
                  <Tag
                    :value="rec.status"
                    :severity="(statusSeverity[rec.status] ?? 'secondary') as any"
                    class="capitalize text-[10px] px-2 py-0.5"
                  />
                </div>
              </div>
              <span v-else class="text-xs text-surface-400 italic text-center block">{{ t('common.not_marked') }}</span>
            </template>
          </Column>
          <Column :header="t('common.actions')" style="width: 100px" :exportable="false">
            <template #body="{ data }">
              <div class="flex flex-col gap-2">
                <template v-for="rec in getStudentAttendances(data.id)" :key="'btn-'+rec.id">
                  <Button
                    v-if="rec.status === 'absent'"
                    :label="t('supervisor.excuse')"
                    icon="pi pi-check"
                    text
                    severity="info"
                    :loading="excusing.includes(rec.id)"
                    @click.stop="excuseStudent(rec.id)"
                    class="p-0 h-[22px] text-[10px] m-0"
                  />
                  <div v-else class="h-[22px]"></div>
                </template>
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </Dialog>

    <!-- ═══════════ DIALOG: Today's Schedule ═══════════ -->
    <Dialog
      v-model:visible="scheduleDialog"
      :header="(selectedScheduleClass?.name ?? '') + ' — ' + t('supervisor.todays_schedule')"
      :style="{ width: '90vw', maxWidth: '800px' }"
      modal
      :draggable="false"
    >
      <div v-if="scheduleLoading" class="flex justify-center py-8">
        <ProgressSpinner style="width: 40px; height: 40px" />
      </div>

      <div v-else-if="!scheduleData?.schedules?.length" class="flex flex-col items-center py-12 text-surface-400">
        <i class="pi pi-calendar-times text-4xl mb-3"></i>
        <p>{{ t('supervisor.no_schedule_today') }}</p>
      </div>

      <div v-else class="space-y-3">
        <div class="mb-3 text-sm text-surface-500 dark:text-surface-400">
          <i class="pi pi-calendar mr-1"></i> {{ scheduleData.day }} — {{ t('supervisor.current_time') }}: <strong>{{ currentTime }}</strong>
        </div>

        <div
          v-for="session in scheduleData.schedules"
          :key="session.id"
          class="rounded-xl overflow-hidden border transition-all"
          :class="isCurrentSession(session)
            ? 'border-green-400 dark:border-green-600 bg-green-50 dark:bg-green-900/20 ring-2 ring-green-300 dark:ring-green-700'
            : 'border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800'"
        >
          <div class="flex flex-wrap items-center gap-4 p-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div
                class="rounded-lg p-2 shrink-0"
                :class="isCurrentSession(session)
                  ? 'bg-green-200 dark:bg-green-800/60 text-green-800 dark:text-green-200'
                  : 'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300'"
              >
                <i class="pi pi-clock text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="font-semibold text-surface-900 dark:text-surface-0 truncate">
                  {{ session.assignment?.subject?.name ?? 'Session' }}
                </div>
                <div class="text-xs text-surface-500 dark:text-surface-400">
                  {{ formatTime(session.start_time) }} – {{ formatTime(session.end_time) }}
                  <span v-if="session.room" class="ml-2">
                    <i class="pi pi-map-marker text-[10px]"></i> {{ session.room }}
                  </span>
                </div>
                <div v-if="session.assignment?.teacher" class="text-xs text-surface-400 dark:text-surface-500 mt-0.5">
                  <i class="pi pi-user text-[10px]"></i> {{ session.assignment.teacher.first_name }} {{ session.assignment.teacher.last_name }}
                </div>
              </div>
            </div>

            <div class="flex flex-col items-end gap-2 shrink-0">
              <Tag v-if="isCurrentSession(session)" :value="t('supervisor.current_session')" severity="success" class="text-xs" />
              <Button 
                v-if="isMarkingAttendanceForClass" 
                :label="t('supervisor.mark_attendance')" 
                icon="pi pi-check-square" 
                size="small" 
                @click="openAttendanceForSession(session)" 
              />
            </div>
          </div>
        </div>
      </div>
    </Dialog>

    <!-- ═══════════ DIALOG: Mark Attendance ═══════════ -->
    <Dialog
      v-model:visible="attendanceDialog"
      :header="`${t('supervisor.mark_attendance')} — ${attendanceClass?.name ?? ''}`"
      :style="{ width: '85vw', maxWidth: '1000px' }"
      modal
      :draggable="false"
    >
      <!-- Filters Row -->
      <div class="grid mb-4">
        <!-- Selected Session Info -->
        <div class="col-12 md:col-6">
          <label class="block mb-1 font-medium text-sm">{{ t('classes.subject') }} / Session</label>
          <div class="p-2 border rounded-lg bg-surface-50 dark:bg-surface-800 text-surface-900 dark:text-surface-0 font-medium">
            {{ attendanceSubject?.name ?? 'N/A' }} 
            <span class="text-sm font-normal text-surface-500 ml-2" v-if="selectedSessionData">
              ({{ formatTime(selectedSessionData.start_time) }} - {{ formatTime(selectedSessionData.end_time) }})
            </span>
          </div>
        </div>

        <!-- Date Picker -->
        <div class="col-12 md:col-6">
          <label class="block mb-1 font-medium text-sm">{{ t('attendance.date') }}</label>
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
        class="p-datatable-sm w-full"
      >
        <Column header="#" style="width: 50px">
          <template #body="{ index }">
            <span class="text-surface-500">{{ index + 1 }}</span>
          </template>
        </Column>

        <Column :header="t('common.name')">
          <template #body="{ data }">
            <div>
              <div class="font-medium">{{ data.student.first_name }} {{ data.student.last_name }}</div>
              <div class="text-xs text-surface-400">{{ data.student.code }}</div>
            </div>
          </template>
        </Column>

        <Column :header="t('common.status')" style="width: 180px">
          <template #body="{ data }">
            <div class="flex align-items-center gap-2">
              <Tag
                :value="data.status"
                :severity="(statusSeverity[data.status] ?? 'secondary') as any"
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

        <Column :header="t('supervisor.reason')" style="width: 220px">
          <template #body="{ data }">
            <InputText
              v-model="data.reason"
              :placeholder="t('supervisor.reason_placeholder')"
              class="w-full"
              size="small"
              :disabled="data.status === 'present'"
            />
          </template>
        </Column>
      </DataTable>

      <div v-if="!attendanceLoading && !attendanceRows.length" class="text-center text-surface-400 py-4">
        {{ t('supervisor.no_students_message') }}
      </div>

      <!-- Footer -->
      <template #footer>
        <div class="flex justify-content-between align-items-center">
          <div class="text-sm text-surface-400">
            {{ attendanceRows.filter(r => r.status !== 'present').length }} {{ t('supervisor.non_present') }}
            / {{ attendanceRows.length }}
          </div>
          <div class="flex gap-2">
            <Button :label="t('common.cancel')" severity="secondary" outlined @click="attendanceDialog = false" />
            <Button
              :label="t('supervisor.save_attendance')"
              icon="pi pi-save"
              :loading="savingAttendance"
              :disabled="!attendanceRows.length"
              @click="saveAttendance"
            />
          </div>
        </div>
      </template>
    </Dialog>
  </div>
</template>
