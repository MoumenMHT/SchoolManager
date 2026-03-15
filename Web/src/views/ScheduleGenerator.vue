<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import ScheduleService, { type GenerateScheduleResponse } from '@/service/ScheduleService';
import ClassesService from '@/service/ClassesService';

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

const generateSchedules = async () => {
  try {
    loading.value = true;
    const result = await ScheduleService.generateSchedules({
      academic_year: academicYear.value,
      clear_existing: clearExisting.value,
      save: true
    });

    summary.value = result;

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

onMounted(async () => {
  await loadAssignmentsCount();
});
</script>

<template>
  <div class="card">
    <Toast />

    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-2xl font-semibold mb-1">Schedule Generator</h2>
        <p class="text-muted-color m-0">
          First assign teachers to classes, then generate weekly schedules for all classes.
        </p>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4 mb-4">
      <div class="col-span-12 md:col-span-4">
        <label class="block font-semibold mb-2">Academic Year</label>
        <InputText v-model="academicYear" class="w-full" placeholder="2025-2026" />
      </div>

      <div class="col-span-12 md:col-span-4 flex items-end">
        <div class="flex items-center gap-2">
          <Checkbox v-model="clearExisting" :binary="true" inputId="clearExisting" />
          <label for="clearExisting">Clear existing schedules first</label>
        </div>
      </div>

      <div class="col-span-12 md:col-span-4 flex items-end justify-end gap-2">
        <Button
          label="Generate All Schedules"
          icon="pi pi-cog"
          :loading="loading"
          @click="generateSchedules"
        />
        <Button
          label="Export Excel"
          icon="pi pi-file-excel"
          severity="success"
          :loading="exporting"
          @click="exportExcel"
        />
      </div>
    </div>

    <Message severity="info" :closable="false" class="mb-4">
      Manual class assignments are required before generation.
      Current assignment check (sample): {{ assignmentsCount }} records.
    </Message>

    <div v-if="summary" class="border border-surface-200 dark:border-surface-700 rounded p-4 mt-4">
      <h3 class="text-lg font-semibold mb-3">Generation Result</h3>

      <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-12 md:col-span-3">Generated: <strong>{{ summary.summary.generated_sessions }}</strong></div>
        <div class="col-span-12 md:col-span-3">Saved: <strong>{{ summary.summary.saved_sessions }}</strong></div>
        <div class="col-span-12 md:col-span-3">Unfilled: <strong>{{ summary.summary.unfilled_items }}</strong></div>
        <div class="col-span-12 md:col-span-3">Year: <strong>{{ summary.summary.academic_year }}</strong></div>
      </div>

      <div v-if="summary.unfilled?.length" class="mb-3">
        <h4 class="font-semibold mb-2">Unfilled Items</h4>
        <ul class="m-0 pl-5">
          <li v-for="(item, idx) in summary.unfilled" :key="idx" class="mb-3">
            <div>
              <strong>Assignment #{{ item.assignment_id }}</strong>
              <span v-if="item.class_name"> | Class: {{ item.class_name }}</span>
              <span v-if="item.subject_name"> | Subject: {{ item.subject_name }}</span>
              <span v-if="item.teacher_name"> | Teacher: {{ item.teacher_name }}</span>
            </div>
            <div class="text-sm">
              Needed: <strong>{{ item.required }}</strong>, Placed: <strong>{{ item.placed }}</strong>
            </div>
            <div class="text-sm">Reason: {{ item.reason }}</div>
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

      <div class="flex gap-2 mt-3">
        <Button
          label="Adjust Manually in Classes"
          icon="pi pi-pencil"
          severity="secondary"
          @click="goToManualAdjustments"
        />
      </div>
    </div>
  </div>
</template>
