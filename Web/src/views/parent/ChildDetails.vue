<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { ParentPortalService } from '@/service/ParentPortalService';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const route = useRoute();
const toast = useToast();
const { t } = useI18n();

const studentId = ref(route.params.id);
const loading = ref(true);

const reportCard = ref(null);
const reportCardLoading = ref(false);
const selectedSemester = ref('1');
const selectedAcademicYear = ref(`${new Date().getFullYear()}-${new Date().getFullYear() + 1}`);

// Raw grouped-by-day object from the API: { monday: [...], tuesday: [...] }
const scheduleRaw = ref({});
const attendance = ref([]);
const grades = ref([]);

// Days and hours for schedule grid
const weekDays = computed(() => [
  { key: 'sunday',    label: t('common.sunday') },
  { key: 'monday',    label: t('common.monday') },
  { key: 'tuesday',   label: t('common.tuesday') },
  { key: 'wednesday', label: t('common.wednesday') },
  { key: 'thursday',  label: t('common.thursday') }
]);

const schoolHours = [
  { hour: 8,  label: '08:00 - 09:00' },
  { hour: 9,  label: '09:00 - 10:00' },
  { hour: 10, label: '10:00 - 11:00' },
  { hour: 11, label: '11:00 - 12:00' },
  { hour: 12, label: '12:00 - 13:00' },
  { hour: 13, label: '13:00 - 14:00' },
  { hour: 14, label: '14:00 - 15:00' },
  { hour: 15, label: '15:00 - 16:00' },
  { hour: 16, label: '16:00 - 17:00' },
  { hour: 17, label: '17:00 - 18:00' }
];

const getScheduleForSlot = (dayKey, hour) => {
  const daySchedules = scheduleRaw.value[dayKey] || [];
  const startTime = `${hour.toString().padStart(2, '0')}:00:00`;
  const endTime = `${(hour + 1).toString().padStart(2, '0')}:00:00`;

  return daySchedules.find(schedule => {
    const scheduleStart = schedule.start_time;
    const scheduleEnd = schedule.end_time;
    return scheduleStart <= startTime && scheduleEnd > startTime;
  }) || null;
};

const loadReportCard = async () => {
    try {
        reportCardLoading.value = true;
        const data = await ParentPortalService.getChildReportCard(studentId.value, selectedSemester.value, selectedAcademicYear.value);
        reportCard.value = data;
    } catch (error) {
        reportCard.value = null;
    } finally {
        reportCardLoading.value = false;
    }
};

const loadData = async () => {
    loading.value = true;
    try {
        const [schData, attData, grdData] = await Promise.all([
            ParentPortalService.getChildSchedule(studentId.value),
            ParentPortalService.getChildAttendances(studentId.value),
            ParentPortalService.getChildGrades(studentId.value)
        ]);

        // Schedule comes back as { success, data: { monday: [...], ... } }
        // Extract the inner data object (grouped by day) for our computed flattener
        const rawSch = schData?.data ?? schData;

        // If the schedule comes back as an array, organize it by day
        if (Array.isArray(rawSch)) {
            const organizedSchedules = {};
            rawSch.forEach(schedule => {
                const dayKey = schedule.day ? schedule.day.toLowerCase() : '';
                if (dayKey) {
                    if (!organizedSchedules[dayKey]) {
                        organizedSchedules[dayKey] = [];
                    }
                    organizedSchedules[dayKey].push(schedule);
                }
            });
            scheduleRaw.value = organizedSchedules;
        } else if (rawSch && typeof rawSch === 'object') {
            scheduleRaw.value = rawSch;
        } else {
            scheduleRaw.value = {};
        }

        // Attendance: { success, data: [...] } OR [...]
        const extractedAttendance = attData?.data?.data || attData?.data || attData;
        attendance.value = Array.isArray(extractedAttendance) ? extractedAttendance : [];

        // Grades: { student: {...}, grades: [...] } (due to ApiService unwrapping .data)
        const extractedGrades = grdData?.grades || grdData?.data?.grades || grdData?.data || grdData;
        grades.value = Array.isArray(extractedGrades) ? extractedGrades : [];

        await loadReportCard();

    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error loading data', detail: 'Could not load details for this student.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="card" v-if="!loading">
        <div class="flex justify-between items-center mb-6">
            <h5 class="text-surface-900 dark:text-surface-0 font-semibold m-0">{{ t('parent_portal.student_details') }}</h5>
        </div>
        
        <TabView>
            <TabPanel :header="t('parent_portal.schedule')">
                <div v-if="scheduleRaw && Object.keys(scheduleRaw).length > 0" class="overflow-x-auto">
                    <table class="schedule-table w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="schedule-header time-column">{{ t('common.time') }}</th>
                                <th v-for="day in weekDays" :key="day.key" class="schedule-header">
                                    {{ day.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="timeSlot in schoolHours" :key="timeSlot.hour">
                                <td class="time-column font-semibold text-sm">
                                    {{ timeSlot.label }}
                                </td>
                                <td v-for="day in weekDays" :key="day.key" class="schedule-cell">
                                    <template v-if="getScheduleForSlot(day.key, timeSlot.hour)">
                                        <div class="schedule-content has-schedule">
                                            <div class="font-semibold text-sm">
                                                {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.subject?.name || getScheduleForSlot(day.key, timeSlot.hour)?.subject?.name || '—' }}
                                            </div>
                                            <div class="text-xs mt-1 text-muted-color">
                                                {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.teacher?.first_name || getScheduleForSlot(day.key, timeSlot.hour)?.teacher?.first_name || '' }}
                                                {{ getScheduleForSlot(day.key, timeSlot.hour)?.assignment?.teacher?.last_name || getScheduleForSlot(day.key, timeSlot.hour)?.teacher?.last_name || '' }}
                                            </div>
                                            <div v-if="getScheduleForSlot(day.key, timeSlot.hour)?.room" class="text-xs mt-1">
                                                <i class="pi pi-map-marker"></i> {{ getScheduleForSlot(day.key, timeSlot.hour)?.room }}
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
                <div v-else class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                    <i class="pi pi-calendar-times text-4xl text-muted-color mb-2 block"></i>
                    <p class="text-muted-color">{{ t('parent_portal.no_schedule_specific') }}</p>
                </div>
            </TabPanel>
            <TabPanel :header="t('parent_portal.attendance')">
                <DataTable :value="attendance" dataKey="id" responsiveLayout="scroll" :paginator="true" :rows="10">
                    <Column field="date" :header="t('parent_portal.date')">
                        <template #body="{ data }">
                            {{ new Date(data.date).toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' }) }}
                        </template>
                    </Column>
                    <Column field="time" :header="t('common.time')">
                        <template #body="{ data }">
                            <span class="font-semibold text-primary">{{ data.time ? data.time.substring(0, 5) : '—' }}</span>
                        </template>
                    </Column>
                    <Column field="subject.name" :header="t('parent_portal.subject')">
                        <template #body="{ data }">
                            {{ data.subject ? data.subject.name : '—' }}
                        </template>
                    </Column>
                    <Column :header="t('parent_portal.teacher')">
                        <template #body="{ data }">
                            {{ data.teacher ? data.teacher.first_name + ' ' + data.teacher.last_name : (data.schedule?.assignment?.teacher ? data.schedule.assignment.teacher.first_name + ' ' + data.schedule.assignment.teacher.last_name : '—') }}
                        </template>
                    </Column>
                    <Column field="status" :header="t('parent_portal.status')">
                        <template #body="{ data }">
                            <Tag v-if="data.status" :severity="data.status === 'present' ? 'success' : (data.status === 'absent' ? 'danger' : 'warn')" :value="data.status.toUpperCase()"></Tag>
                        </template>
                    </Column>
                    <Column field="reason" :header="t('parent_portal.notes')"></Column>
                </DataTable>
            </TabPanel>
            <TabPanel :header="t('parent_portal.grades')">
                <div class="mb-4">
                    <Button :label="t('parent_portal.download_report_card')" icon="pi pi-download" @click="downloadReportCard" class="p-button-sm p-button-info" />
                </div>
                <DataTable :value="grades" dataKey="id" responsiveLayout="scroll" :paginator="true" :rows="10">
                    <Column field="exam.subject.name" :header="t('parent_portal.subject')"></Column>
                    <Column field="exam.semester" :header="t('parent_portal.term')">
                        <template #body="{ data }">
                            {{ data.exam?.semester ? t('common.trimester_' + String(data.exam.semester).replace(/\D/g, '')) : '—' }}
                        </template>
                    </Column>
                    <Column field="exam.exam_type" :header="t('parent_portal.type')">
                        <template #body="{ data }">
                            {{ data.exam?.exam_type ? t('dashboard.exam_type_' + data.exam.exam_type) : '—' }}
                        </template>
                    </Column>
                    <Column field="grade" :header="t('parent_portal.grade')">
                        <template #body="{ data }">
                            <span v-if="data.grade !== null" class="font-bold" :class="{'text-green-600': data.grade >= 10, 'text-red-600': data.grade < 10}">
                                {{ data.grade }} / {{ data.exam?.max_grade || 20 }}
                            </span>
                            <span v-else class="text-muted-color italic text-sm">
                                {{ t('parent_portal.not_graded') }}
                            </span>
                        </template>
                    </Column>
                    <Column field="comment" :header="t('parent_portal.feedback')"></Column>
                </DataTable>

                <!-- Report Card Section -->
                <div class="mt-8 pt-8 border-t border-surface-200 dark:border-surface-700">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                        <h3 class="text-xl font-semibold text-primary">{{ t('parent_portal.report_card') }}</h3>
                        
                        <div class="flex gap-2">
                            <!-- Semester Select -->
                            <select v-model="selectedSemester" @change="loadReportCard" class="p-2 border border-surface-300 dark:border-surface-600 rounded-md bg-surface-0 dark:bg-surface-900">
                                <option value="1">{{ t('common.trimester_1') }}</option>
                                <option value="2">{{ t('common.trimester_2') }}</option>
                                <option value="3">{{ t('common.trimester_3') }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="reportCardLoading" class="flex justify-center p-8">
                        <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
                    </div>

                    <div v-else-if="reportCard && reportCard.subjects && reportCard.subjects.length > 0">
                        <div class="mb-4 bg-primary-50 dark:bg-primary-900/30 p-4 rounded-lg flex justify-between items-center border border-primary-200 dark:border-primary-800">
                            <div>
                                <span class="text-muted-color block text-sm">{{ t('common.trimester_' + reportCard.semester) }}</span>
                                <span class="font-bold text-lg">{{ reportCard.academic_year }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-muted-color block text-sm">{{ t('parent_portal.overall_average') }}</span>
                                <span class="text-2xl font-bold" :class="{'text-green-600 dark:text-green-400': reportCard.overall_average >= 10, 'text-red-600 dark:text-red-400': reportCard.overall_average < 10}">
                                    {{ Number(reportCard.overall_average).toFixed(2) }} / 20
                                </span>
                            </div>
                        </div>

                        <DataTable :value="reportCard.subjects" responsiveLayout="scroll" showGridlines stripedRows>
                            <Column field="subject.name" :header="t('parent_portal.subject')"></Column>
                            <Column :header="t('parent_portal.teacher')">
                                <template #body="{ data }">
                                    {{ data.teacher?.first_name }} {{ data.teacher?.last_name }}
                                </template>
                            </Column>
                            <Column field="evaluation_continue" :header="t('parent_portal.cc')" align="center"></Column>
                            <Column field="devoir" :header="t('parent_portal.devoir')" align="center"></Column>
                            <Column field="composition" :header="t('parent_portal.composition')" align="center"></Column>
                            <Column field="average" :header="t('parent_portal.average')" align="center">
                                <template #body="{ data }">
                                    <span class="font-bold" :class="{'text-green-600 dark:text-green-400': data.average >= 10, 'text-red-600 dark:text-red-400': data.average < 10}">
                                        {{ data.average }}
                                    </span>
                                </template>
                            </Column>
                            <Column field="coefficient" :header="t('parent_portal.coef')" align="center"></Column>
                            <Column field="weighted_average" :header="t('parent_portal.weighted_avg')" align="center"></Column>
                        </DataTable>
                    </div>

                    <div v-else class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                        <i class="pi pi-file-excel text-4xl text-muted-color mb-2 block"></i>
                        <p class="text-muted-color">{{ t('parent_portal.no_report_card') }}</p>
                    </div>
                </div>
            </TabPanel>
        </TabView>
    </div>
    
    <div v-else class="card flex justify-center items-center py-12">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
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
</style>
