<template>
  <div class="p-4 md:p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-1 flex items-center gap-3">
          <div class="bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 p-2 rounded-xl flex items-center justify-center">
            <i class="pi pi-file-edit text-2xl"></i>
          </div>
          Exams Management
        </h1>
        <p class="text-surface-500 dark:text-surface-400">Create and configure new exams for your classes</p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <i class="pi pi-spin pi-spinner text-5xl text-primary-500"></i>
    </div>

    <div v-else class="space-y-6">
      <!-- Step 1: Select Level -->
      <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-2xl shadow-sm p-5 md:p-6">
        <h2 class="text-lg font-bold text-surface-800 dark:text-surface-100 mb-4 flex items-center gap-3">
          <span class="bg-primary-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm shadow-sm">1</span>
          Select Level
        </h2>
        <Select
          v-model="selectedLevel"
          :options="availableLevels"
          optionLabel="label"
          optionValue="value"
          placeholder="Choose the educational level..."
          class="w-full md:w-80"
          @change="onLevelChange"
        />
      </div>

      <!-- Step 2: Select Classes -->
      <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-2xl shadow-sm p-5 md:p-6" v-if="selectedLevel">
        <div class="flex flex-wrap items-center justify-between mb-4 gap-3">
          <h2 class="text-lg font-bold text-surface-800 dark:text-surface-100 flex items-center gap-3">
            <span class="bg-primary-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm shadow-sm">2</span>
            Select Classes
          </h2>
          <div class="flex items-center bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 p-2 px-3 rounded-xl cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors" @click="toggleAllClasses">
            <Checkbox :modelValue="isAllClassesSelected" :binary="true" readonly class="pointer-events-none" />
            <span class="ml-2 font-bold text-surface-700 dark:text-surface-200 select-none">Select All Classes</span>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
          <div v-for="cls in filteredClasses" :key="cls.id">
            <div 
              class="flex items-center p-3 rounded-xl cursor-pointer transition-all border-2"
              :class="selectedClassIds.includes(cls.id) ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 shadow-sm' : 'border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50 hover:border-primary-300 dark:hover:border-primary-600'"
              @click="() => {
                const idx = selectedClassIds.indexOf(cls.id);
                if (idx > -1) selectedClassIds.splice(idx, 1);
                else selectedClassIds.push(cls.id);
              }"
            >
              <Checkbox v-model="selectedClassIds" :value="cls.id" :inputId="`cls-${cls.id}`" class="pointer-events-none" />
              <label class="ml-3 flex-1 cursor-pointer font-bold text-surface-700 dark:text-surface-200 text-lg m-0 select-none">{{ cls.name }}</label>
            </div>
          </div>
        </div>
        <div v-if="!filteredClasses.length" class="p-4 mt-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-xl flex items-center gap-2 font-medium">
          <i class="pi pi-exclamation-circle text-xl"></i>
          No classes found for this level.
        </div>
      </div>

      <!-- Step 3: Exam Configuration -->
      <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-2xl shadow-sm p-5 md:p-6" v-if="selectedClassIds.length">
        <h2 class="text-lg font-bold text-surface-800 dark:text-surface-100 mb-5 flex items-center gap-3">
          <span class="bg-primary-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm shadow-sm">3</span>
          Exam Configuration
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="block font-semibold text-surface-700 dark:text-surface-300 mb-2">Subject <span class="text-red-500">*</span></label>
            <Select v-model="examSubject" :options="availableSubjects" optionLabel="name" optionValue="id" placeholder="Select Subject" class="w-full" />
          </div>
          <div>
            <label class="block font-semibold text-surface-700 dark:text-surface-300 mb-2">Exam Type <span class="text-red-500">*</span></label>
            <Select v-model="examType" :options="examTypeOptions" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full" />
          </div>
          <div>
            <label class="block font-semibold text-surface-700 dark:text-surface-300 mb-2">Trimester <span class="text-red-500">*</span></label>
            <Select v-model="examSemester" :options="semesterOptions" optionLabel="label" optionValue="value" placeholder="Select Trimester" class="w-full" />
          </div>
          <div>
            <label class="block font-semibold text-surface-700 dark:text-surface-300 mb-2">Academic Year</label>
            <InputText :value="currentAcademicYear" readonly class="w-full bg-surface-100 dark:bg-surface-900 text-surface-600 dark:text-surface-400 font-bold border-none" />
          </div>
        </div>
      </div>

      <!-- Step 4: Exercises Breakdown -->
      <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-2xl shadow-sm p-5 md:p-6" v-if="selectedClassIds.length">
        <div class="flex flex-wrap justify-between items-center mb-5 gap-3">
          <h2 class="text-lg font-bold text-surface-800 dark:text-surface-100 flex items-center gap-3">
            <span class="bg-primary-600 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm shadow-sm">4</span>
            Exercises Breakdown
          </h2>
          <Button label="Add Exercise" icon="pi pi-plus" outlined @click="addExerciseRow" class="rounded-xl font-bold" />
        </div>

        <div class="flex flex-col gap-3">
          <div v-for="(ex, idx) in examExercises" :key="idx" 
               class="flex flex-col sm:flex-row gap-4 items-center bg-surface-50 dark:bg-surface-900/50 p-4 rounded-xl border border-surface-200 dark:border-surface-700 transition-all">
            <div class="flex-1 w-full">
              <label class="block mb-2 font-semibold text-sm text-surface-600 dark:text-surface-400">Exercise Name</label>
              <InputText v-model="ex.level_name" placeholder="e.g. Exercice 1" class="w-full font-medium" />
            </div>
            <div class="w-full sm:w-48">
              <label class="block mb-2 font-semibold text-sm text-surface-600 dark:text-surface-400 text-center">Max Grade</label>
              <InputNumber v-model="ex.max_note" :min="1" :max="100" class="w-full" inputClass="w-full text-center font-bold text-primary-600 dark:text-primary-400 text-lg" showButtons buttonLayout="horizontal" incrementButtonIcon="pi pi-plus" decrementButtonIcon="pi pi-minus" />
            </div>
            <div class="flex items-center justify-center sm:pt-6">
              <Button icon="pi pi-trash" severity="danger" text rounded size="large" @click="removeExerciseRow(idx)" :disabled="examExercises.length <= 1" v-tooltip.top="'Remove'" />
            </div>
          </div>
        </div>

        <div class="mt-6 p-6 rounded-2xl bg-gradient-to-br from-primary-900 to-primary-700 dark:from-primary-900 dark:to-primary-800 text-white flex flex-col md:flex-row justify-between items-center shadow-lg">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
              <i class="pi pi-chart-line text-3xl text-white"></i>
            </div>
            <div>
              <h4 class="text-white font-bold text-xl m-0">Overall Exam Grade</h4>
              <p class="text-sm text-primary-100 m-0 mt-1">Calculated automatically from exercises</p>
            </div>
          </div>
          <div class="text-5xl font-bold text-white mt-4 md:mt-0 drop-shadow-md">
            {{ examMaxGrade }} <span class="text-xl font-medium text-primary-100">points</span>
          </div>
        </div>
      </div>

      <!-- Submit Action -->
      <div class="flex justify-end mt-6" v-if="selectedClassIds.length">
        <Button label="Create Exam" icon="pi pi-check-circle" size="large" :loading="saving" @click="saveExam" class="w-full md:w-auto px-8 py-4 text-lg font-bold rounded-xl shadow-md" />
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import ApiService from '@/service/ApiService';

const { t } = useI18n();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const teacherId = ref<number | null>(null);

// ─── Data ──────────────────────────────────────────────────────────────────────
const myClasses = ref<any[]>([]);

// ─── Form State ────────────────────────────────────────────────────────────────
const selectedLevel = ref<string | null>(null);
const selectedClassIds = ref<number[]>([]);
const examSubject = ref<number | null>(null); // Now directly stores ID
const examType = ref<string | null>(null);
const examSemester = ref<string | null>(null);
const examExercises = ref<Array<{ level_name: string; max_note: number }>>([
  { level_name: 'Exercice 1', max_note: 5 }
]);

const examTypeOptions = [
  { label: 'Évaluation Continue', value: 'evaluation_continue' },
  { label: 'Devoir', value: 'devoir' },
  { label: 'Composition', value: 'composition' },
  { label: 'Quiz', value: 'quiz' },
  { label: 'Exam', value: 'exam' }
];

const semesterOptions = [
  { label: 'Trimester 1', value: 'Trimester 1' },
  { label: 'Trimester 2', value: 'Trimester 2' },
  { label: 'Trimester 3', value: 'Trimester 3' },
];

// ─── Computed ──────────────────────────────────────────────────────────────────
const currentAcademicYear = computed(() => {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth() + 1;
  return month >= 9 ? `${year}-${year + 1}` : `${year - 1}-${year}`;
});

// Unique levels from the teacher's classes
const availableLevels = computed(() => {
  const levels = new Set<string>();
  myClasses.value.forEach(cls => {
    if (cls.level) levels.add(cls.level);
  });
  return Array.from(levels).map(l => ({ label: l, value: l }));
});

// Classes that belong to the selected level
const filteredClasses = computed(() => {
  if (!selectedLevel.value) return [];
  return myClasses.value.filter(cls => cls.level === selectedLevel.value);
});

// Subjects based on the selected classes (intersection or union)
const availableSubjects = computed(() => {
  if (!selectedClassIds.value.length) return [];
  // For simplicity, get all unique subjects from the selected classes
  const subjectMap = new Map();
  myClasses.value.forEach(cls => {
    if (selectedClassIds.value.includes(cls.id)) {
      cls.subjects?.forEach((sub: any) => {
        if (!subjectMap.has(sub.id)) subjectMap.set(sub.id, sub);
      });
    }
  });
  return Array.from(subjectMap.values());
});

const isAllClassesSelected = computed(() => {
  return filteredClasses.value.length > 0 && selectedClassIds.value.length === filteredClasses.value.length;
});

const examMaxGrade = computed(() => {
  return examExercises.value.reduce((acc, ex) => acc + (ex.max_note || 0), 0);
});

// ─── Methods ───────────────────────────────────────────────────────────────────
onMounted(async () => {
  await loadClasses();
});

const loadClasses = async () => {
  loading.value = true;
  try {
    const currentUser = ApiService.getUser();
    teacherId.value = currentUser?.teacher?.id ?? null;

    const classesResp = await ApiService.get<any[]>('/teacher/classes');
    myClasses.value = (classesResp.data as any) || [];
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load classes', life: 4000 });
  } finally {
    loading.value = false;
  }
};

const toggleAllClasses = () => {
  if (isAllClassesSelected.value) {
    selectedClassIds.value = [];
  } else {
    selectedClassIds.value = filteredClasses.value.map(c => c.id);
  }
};

const onLevelChange = () => {
  selectedClassIds.value = [];
  examSubject.value = null;
};

const addExerciseRow = () => {
  examExercises.value.push({ level_name: `Exercice ${examExercises.value.length + 1}`, max_note: 5 });
};

const removeExerciseRow = (index: number) => {
  examExercises.value.splice(index, 1);
};

const saveExam = async () => {
  if (!teacherId.value) {
    toast.add({ severity: 'error', summary: 'Access Error', detail: 'No teacher profile associated with your account.', life: 4000 });
    return;
  }
  if (!selectedLevel.value) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select a level', life: 3000 });
    return;
  }
  if (!selectedClassIds.value.length) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select at least one class', life: 3000 });
    return;
  }
  if (!examSubject.value || !examType.value || !examSemester.value) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please fill all required exam details', life: 3000 });
    return;
  }
  if (!examExercises.value.length) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please add at least one exercise', life: 3000 });
    return;
  }
  
  saving.value = true;
  try {
    await ApiService.post('/exams', {
      subject_id: examSubject.value, // Now directly sending the ID
      teacher_id: teacherId.value,
      exam_type: examType.value,
      semester: examSemester.value,
      academic_year: currentAcademicYear.value,
      max_grade: examMaxGrade.value,
      class_ids: selectedClassIds.value,
      exercises: examExercises.value,
    });
    
    toast.add({ severity: 'success', summary: 'Saved', detail: 'Exam created successfully', life: 3000 });
    
    // Reset form
    selectedLevel.value = null;
    selectedClassIds.value = [];
    examSubject.value = null;
    examType.value = null;
    examSemester.value = null;
    examExercises.value = [{ level_name: 'Exercice 1', max_note: 5 }];
    
  } catch (err: any) {
    let errorDetail = 'Failed to create exam';
    if (err?.response?.data?.errors) {
      const errs = err.response.data.errors;
      const firstKey = Object.keys(errs)[0];
      errorDetail = errs[firstKey][0];
      console.error('Validation errors:', errs);
    } else if (err?.response?.data?.message) {
      errorDetail = err?.response?.data?.message;
    }
    toast.add({ severity: 'error', summary: 'Validation Error', detail: errorDetail, life: 5000 });
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped>
/* To make the Select All checkbox completely unclickable/pointer-events-none but look active */
:deep(.p-checkbox.pointer-events-none) {
  pointer-events: none;
}
</style>