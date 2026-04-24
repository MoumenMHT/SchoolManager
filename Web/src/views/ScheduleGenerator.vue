<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import ScheduleService, { type GenerateScheduleResponse } from '@/service/ScheduleService';
import ClassesService from '@/service/ClassesService';

const { t } = useI18n();
const toast = useToast();
const router = useRouter();

const getDefaultAcademicYear = (): string => {
  const now = new Date();
  const year = now.getFullYear();
  const startYear = now.getMonth() < 8 ? year - 1 : year;
  return `${startYear}-${startYear + 1}`;
};

const loading = ref(false);
const exporting = ref(false);
const summary = ref<GenerateScheduleResponse | null>(null);
const academicYear = ref(getDefaultAcademicYear());
const clearExisting = ref(true);
const assignmentsCount = ref<number>(0);

const loadAssignmentsCount = async () => {
  try {
    const rows = await ClassesService.getAllAssignments(academicYear.value);
    assignmentsCount.value = rows.length;
  } catch {
    assignmentsCount.value = 0;
  }
};

const saveProblemsToStorage = (summaryData: GenerateScheduleResponse) => {
  if (summaryData.unfilled?.length) {
    localStorage.setItem('schedule_problems', JSON.stringify({
      year: academicYear.value,
      unfilled: summaryData.unfilled
    }));
  } else {
    localStorage.removeItem('schedule_problems');
  }
};

const loadProblemsFromStorage = () => {
  const stored = localStorage.getItem('schedule_problems');
  if (stored) {
    try {
      const data = JSON.parse(stored);
      if (!summary.value) {
        summary.value = {
          message: '',
          success: true,
          summary: {
            academic_year: data.year,
            generated_sessions: 0,
            saved_sessions: 0,
            unfilled_items: data.unfilled.length,
            clear_existing: false,
            saved: false
          },
          unfilled: data.unfilled
        };
      }
    } catch {
      localStorage.removeItem('schedule_problems');
    }
  }
};

const generateSchedules = async () => {
  try {
    loading.value = true;
    const result = await ScheduleService.generateSchedules({
      academic_year: academicYear.value,
      clear_existing: clearExisting.value,
      save: true
    });

    summary.value = result;
    saveProblemsToStorage(result);

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: result.message,
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error?.response?.data?.message || error?.message || 'Failed to generate schedules',
      life: 4000
    });
  } finally {
    loading.value = false;
  }
};

const exportExcel = async () => {
  try {
    exporting.value = true;
    await ScheduleService.exportSchedulesExcel(academicYear.value);
    toast.add({
      severity: 'success',
      summary: 'Exported',
      detail: 'Excel export downloaded successfully.',
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Export Failed',
      detail: error?.message || 'Could not export schedules.',
      life: 4000
    });
  } finally {
    exporting.value = false;
  }
};

const goToManualAdjustments = () => {
  router.push('/classes');
};

const downloadJSON = () => {
  if (!summary.value?.unfilled) return;
  const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(summary.value.unfilled, null, 2));
  const el = document.createElement("a");
  el.setAttribute("href", dataStr);
  el.setAttribute("download", "schedule_problems.json");
  document.body.appendChild(el);
  el.click();
  el.remove();
};

const checkProblems = async () => {
  try {
    loading.value = true;
    const result = await ScheduleService.generateSchedules({
      academic_year: academicYear.value,
      clear_existing: false,
      save: false
    });

    summary.value = result;
    saveProblemsToStorage(result);

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: result.unfilled?.length ? 'Problems found.' : 'No problems found.',
      life: 3000
    });
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error?.response?.data?.message || 'Failed to check problems',
      life: 4000
    });
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await loadAssignmentsCount();
  loadProblemsFromStorage();
});
</script>

<template>
  <div class="card">
    <Toast />

    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-2xl font-semibold mb-1">{{ t('classes.schedule_generator') }}</h2>
        <p class="text-muted-color m-0">
          {{ t('classes.generator_desc') }}
        </p>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4 mb-4">
      <div class="col-span-12 md:col-span-4">
        <label class="block font-semibold mb-2">{{ t('classes.academic_year') }}</label>
        <InputText v-model="academicYear" class="w-full" placeholder="2025-2026" />
      </div>

      <div class="col-span-12 md:col-span-4 flex items-end">
        <div class="flex items-center gap-2">
          <Checkbox v-model="clearExisting" :binary="true" inputId="clearExisting" />
          <label for="clearExisting">{{ t('classes.clear_existing') }}</label>
        </div>
      </div>

      <div class="col-span-12 md:col-span-4 flex flex-wrap items-end justify-end gap-2">
        <Button
          :label="t('classes.generate_all')"
          icon="pi pi-cog"
          :loading="loading"
          @click="generateSchedules"
        />
        <Button
          :label="t('classes.recheck_problems')"
          icon="pi pi-search"
          severity="info"
          :loading="loading"
          @click="checkProblems"
        />
        <Button
          :label="t('classes.export_excel')"
          icon="pi pi-file-excel"
          severity="success"
          :loading="exporting"
          @click="exportExcel"
        />
      </div>
    </div>

    <Message severity="info" :closable="false" class="mb-4">
      {{ t('classes.manual_assign_req') }}<br/>
      {{ t('classes.current_assign_check', { count: assignmentsCount }) }}
    </Message>

    <div v-if="summary" class="border border-surface-200 dark:border-surface-700 rounded p-4 mt-4">
      <h3 class="text-lg font-semibold mb-3">{{ t('classes.gen_result') }}</h3>

      <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-12 md:col-span-3">{{ t('classes.generated') }}: <strong>{{ summary.summary.generated_sessions }}</strong></div>
        <div class="col-span-12 md:col-span-3">{{ t('classes.saved') }}: <strong>{{ summary.summary.saved_sessions }}</strong></div>
        <div class="col-span-12 md:col-span-3">{{ t('classes.unfilled') }}: <strong>{{ summary.summary.unfilled_items }}</strong></div>
        <div class="col-span-12 md:col-span-3">{{ t('classes.year') }}: <strong>{{ summary.summary.academic_year }}</strong></div>
      </div>

      <div v-if="summary.unfilled?.length" class="mb-3">
        <h4 class="font-semibold mb-2">⚠ {{ t('classes.unfilled_items') }} ({{ summary.unfilled.length }})</h4>
        <ul class="m-0 pl-5">
          <li v-for="(item, idx) in summary.unfilled" :key="idx" class="mb-3">
            <div>
              <strong>{{ t('classes.assignment') }} #{{ item.assignment_id }}</strong>
              <span v-if="item.class_name"> | {{ t('classes.class') }}: {{ item.class_name }}</span>
              <span v-if="item.subject_name"> | {{ t('classes.subject') }}: {{ item.subject_name }}</span>
              <span v-if="item.teacher_name"> | {{ t('classes.teacher') }}: {{ item.teacher_name }}</span>
            </div>
            <div class="text-sm">
              {{ t('classes.needed') }}: <strong>{{ item.required }}</strong>, {{ t('classes.placed') }}: <strong>{{ item.placed }}</strong>
            </div>
            <div class="text-sm">{{ t('classes.reason') }}: {{ item.reason }}</div>
            <div v-if="item.diagnostics" class="text-xs text-muted-color">
              availability: {{ item.diagnostics.outside_teacher_availability }},
              teacher-conflicts: {{ item.diagnostics.teacher_slot_conflict }},
              class-conflicts: {{ item.diagnostics.class_slot_conflict }},
              important-limit: {{ item.diagnostics.important_subject_daily_limit }},
              day-capacity: {{ item.diagnostics.class_day_capacity_reached }}
            </div>
          </li>
        </ul>
      </div>
      <div v-else class="mb-3 text-green-500 font-semibold">
        <i class="pi pi-check-circle mr-1"></i> {{ t('classes.no_problems') }}
      </div>

      <div class="flex flex-wrap gap-2 mt-3">
        <Button
          :label="t('classes.adjust_manually')"
          icon="pi pi-pencil"
          severity="secondary"
          @click="goToManualAdjustments"
        />
      </div>
    </div>
  </div>
</template>
