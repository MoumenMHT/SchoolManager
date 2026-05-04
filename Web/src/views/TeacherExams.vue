<template>
  <div class="p-4 md:p-8 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10 animate-fade-in">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span class="px-3 py-1 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 text-xs font-bold uppercase tracking-wider">
            {{ $t('teacher_exams.subtitle') }}
          </span>
        </div>
        <h1 class="text-4xl md:text-5xl font-black text-surface-900 dark:text-surface-0 tracking-tight flex items-center gap-4">
          <div class="bg-primary-500 text-white p-3 rounded-2xl shadow-lg shadow-primary-500/20">
            <i class="pi pi-file-edit text-2xl"></i>
          </div>
          {{ $t('teacher_exams.title') }}
        </h1>
      </div>
    </div>

    <Tabs value="create" class="premium-tabs">
      <TabList class="mb-10 gap-4 border-none">
        <Tab value="create" class="rounded-2xl px-6 py-3 font-black uppercase text-xs tracking-widest transition-premium">
          <div class="flex items-center gap-2">
            <i class="pi pi-plus-circle"></i>
            <span>{{ $t('teacher_exams.create_tab') }}</span>
          </div>
        </Tab>
        <Tab value="manage" @click="loadExams" class="rounded-2xl px-6 py-3 font-black uppercase text-xs tracking-widest transition-premium">
          <div class="flex items-center gap-2">
            <i class="pi pi-list"></i>
            <span>{{ $t('teacher_exams.manage_tab') }}</span>
          </div>
        </Tab>
      </TabList>

      <TabPanels class="bg-transparent p-0">
        <TabPanel value="create">
          <div v-if="loading" class="flex justify-center py-24">
            <ProgressSpinner style="width: 60px; height: 60px" />
          </div>

          <div v-else class="space-y-10 animate-fade-in">
            <!-- Step 1: Select Level -->
            <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-[2rem] shadow-sm p-8 premium-shadow transition-premium">
              <h2 class="text-xl font-black text-surface-900 dark:text-surface-0 mb-6 flex items-center gap-4">
                <span class="bg-primary-500 text-white w-10 h-10 rounded-xl flex items-center justify-center text-sm font-black shadow-lg shadow-primary-500/20">1</span>
                {{ $t('teacher_exams.step_level') }}
              </h2>
              <Select
                v-model="selectedLevel"
                :options="availableLevels"
                optionLabel="label"
                optionValue="value"
                :placeholder="$t('teacher_exams.level_placeholder')"
                class="w-full md:w-96 premium-select"
                @change="onLevelChange"
              />
            </div>

            <!-- Step 2: Select Classes -->
            <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-[2rem] shadow-sm p-8 premium-shadow transition-premium" v-if="selectedLevel">
              <div class="flex flex-wrap items-center justify-between mb-8 gap-4">
                <h2 class="text-xl font-black text-surface-900 dark:text-surface-0 flex items-center gap-4">
                  <span class="bg-primary-500 text-white w-10 h-10 rounded-xl flex items-center justify-center text-sm font-black shadow-lg shadow-primary-500/20">2</span>
                  {{ $t('teacher_exams.step_classes') }}
                </h2>
                <div class="flex items-center bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 p-3 px-4 rounded-2xl cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-premium shadow-sm" @click="toggleAllClasses">
                  <Checkbox :modelValue="isAllClassesSelected" :binary="true" readonly class="pointer-events-none" />
                  <span class="ml-3 font-black text-surface-700 dark:text-surface-200 uppercase text-xs tracking-widest select-none">{{ $t('teacher_exams.select_all') }}</span>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="cls in filteredClasses" :key="cls.id">
                  <div 
                    class="flex items-center p-5 rounded-2xl cursor-pointer transition-premium border-2 relative overflow-hidden group"
                    :class="selectedClassIds.includes(cls.id) ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 shadow-lg shadow-primary-500/10' : 'border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50 hover:border-primary-300 dark:hover:border-primary-600 shadow-sm'"
                    @click="() => {
                      const idx = selectedClassIds.indexOf(cls.id);
                      if (idx > -1) selectedClassIds.splice(idx, 1);
                      else selectedClassIds.push(cls.id);
                    }"
                  >
                    <div class="absolute top-0 right-0 w-16 h-16 bg-primary-500/5 rounded-full -mr-8 -mt-8 transition-transform group-hover:scale-150"></div>
                    <Checkbox v-model="selectedClassIds" :value="cls.id" :inputId="`cls-${cls.id}`" class="pointer-events-none" />
                    <label class="ml-4 flex-1 cursor-pointer font-black text-surface-900 dark:text-surface-0 text-xl m-0 select-none tracking-tight">{{ cls.name }}</label>
                  </div>
                </div>
              </div>
              <div v-if="!filteredClasses.length" class="p-6 mt-4 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-2xl flex items-center gap-4 font-black uppercase text-xs tracking-widest border border-red-100 dark:border-red-900/30">
                <i class="pi pi-exclamation-circle text-2xl"></i>
                {{ $t('teacher_exams.no_classes_found') }}
              </div>
            </div>

            <!-- Step 3: Exam Configuration -->
            <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-[2rem] shadow-sm p-8 premium-shadow transition-premium" v-if="selectedClassIds.length">
              <h2 class="text-xl font-black text-surface-900 dark:text-surface-0 mb-8 flex items-center gap-4">
                <span class="bg-primary-500 text-white w-10 h-10 rounded-xl flex items-center justify-center text-sm font-black shadow-lg shadow-primary-500/20">3</span>
                {{ $t('teacher_exams.step_config') }}
              </h2>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="flex flex-col gap-2">
                  <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_exams.subject') }} <span class="text-red-500">*</span></label>
                  <Select v-model="examSubject" :options="availableSubjects" optionLabel="name" optionValue="id" :placeholder="$t('teacher_exams.subject_placeholder')" class="w-full premium-select" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_exams.type') }} <span class="text-red-500">*</span></label>
                  <Select v-model="examType" :options="examTypeOptions" optionLabel="label" optionValue="value" :placeholder="$t('teacher_exams.type_placeholder')" class="w-full premium-select" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_exams.trimester') }} <span class="text-red-500">*</span></label>
                  <Select v-model="examSemester" :options="semesterOptions" optionLabel="label" optionValue="value" :placeholder="$t('teacher_exams.trimester_placeholder')" class="w-full premium-select" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_exams.academic_year') }}</label>
                  <InputText :value="currentAcademicYear" readonly class="w-full premium-input font-black text-surface-400 bg-surface-50 dark:bg-surface-900/50 border-none opacity-60" />
                </div>
              </div>
            </div>

            <!-- Step 4: Exercises Breakdown -->
            <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-[2.5rem] shadow-sm p-8 premium-shadow transition-premium" v-if="selectedClassIds.length">
              <div class="flex flex-wrap justify-between items-center mb-10 gap-6">
                <h2 class="text-xl font-black text-surface-900 dark:text-surface-0 flex items-center gap-4">
                  <span class="bg-primary-500 text-white w-10 h-10 rounded-xl flex items-center justify-center text-sm font-black shadow-lg shadow-primary-500/20">4</span>
                  {{ $t('teacher_exams.step_exercises') }}
                </h2>
                <Button :label="$t('teacher_exams.add_exercise')" icon="pi pi-plus" raised class="rounded-2xl font-black uppercase text-xs tracking-widest px-6 py-3" @click="addExerciseRow" />
              </div>

              <div class="space-y-6">
                <div v-for="(ex, idx) in examExercises" :key="idx" 
                     class="flex flex-col md:flex-row gap-6 items-end bg-surface-50 dark:bg-surface-900/40 p-8 rounded-[2rem] border border-surface-200 dark:border-surface-700 transition-premium hover:border-primary-200 dark:hover:border-primary-800 shadow-sm relative group">
                  <div class="flex-1 w-full">
                    <label class="block mb-2 text-xs font-black uppercase tracking-widest text-surface-400 ml-1">{{ $t('teacher_exams.exercise_name') }}</label>
                    <InputText v-model="ex.level_name" :placeholder="$t('teacher_exams.exercise_name_placeholder')" class="w-full premium-input font-black text-lg tracking-tight" />
                  </div>
                  <div class="w-full md:w-56">
                    <label class="block mb-2 text-xs font-black uppercase tracking-widest text-surface-400 ml-1 text-center">{{ $t('teacher_exams.max_grade') }}</label>
                    <InputNumber v-model="ex.max_note" :min="1" :max="100" class="w-full" inputClass="w-full text-center font-black text-2xl text-primary-600 dark:text-primary-400 h-14 bg-transparent border-none focus:ring-0" showButtons buttonLayout="horizontal" incrementButtonIcon="pi pi-plus" decrementButtonIcon="pi pi-minus" />
                  </div>
                  <div class="flex items-center justify-center">
                    <Button icon="pi pi-trash" severity="danger" text raised rounded size="large" class="w-14 h-14 bg-white dark:bg-surface-800 shadow-sm transition-premium hover:shadow-red-500/20" @click="removeExerciseRow(idx)" :disabled="examExercises.length <= 1" />
                  </div>
                </div>
              </div>

              <div class="mt-10 p-10 rounded-[3rem] gradient-card-primary text-white flex flex-col md:flex-row justify-between items-center shadow-2xl shadow-primary-500/30 overflow-hidden relative">
                <div class="absolute top-0 left-0 w-64 h-64 bg-white/5 rounded-full -ml-32 -mt-32 transition-transform group-hover:scale-150"></div>
                
                <div class="flex items-center gap-6 relative z-10">
                  <div class="p-5 bg-white/20 backdrop-blur-md rounded-[2rem] border border-white/20">
                    <i class="pi pi-chart-line text-4xl text-white"></i>
                  </div>
                  <div>
                    <h4 class="text-white font-black text-3xl tracking-tight m-0">{{ $t('teacher_exams.overall_grade') }}</h4>
                    <p class="text-primary-100 font-bold uppercase text-[10px] tracking-[0.2em] m-0 mt-2 opacity-80">{{ $t('teacher_exams.calculated_auto') }}</p>
                  </div>
                </div>
                <div class="flex items-baseline gap-3 mt-8 md:mt-0 relative z-10">
                  <span class="text-7xl font-black text-white drop-shadow-2xl">{{ examMaxGrade }}</span>
                  <span class="text-xl font-black text-primary-100 uppercase tracking-widest opacity-80">{{ $t('teacher_exams.points') }}</span>
                </div>
              </div>
            </div>

            <!-- Submit Action -->
            <div class="flex justify-end mt-12" v-if="selectedClassIds.length">
              <Button :label="$t('teacher_exams.submit')" icon="pi pi-check-circle" size="large" :loading="saving" raised class="w-full md:w-auto px-12 py-5 text-xl font-black uppercase tracking-widest rounded-[2rem] shadow-2xl shadow-primary-500/40" @click="saveExam" />
            </div>
          </div>
        </TabPanel>

        <TabPanel value="manage">
          <div class="bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-[2.5rem] shadow-sm p-8 animate-fade-in">
            <div class="flex flex-wrap items-center justify-between gap-6 mb-8">
              <h3 class="text-2xl font-black text-surface-900 dark:text-surface-0 tracking-tight m-0">{{ $t('teacher_exams.exams_list') }}</h3>
              <div class="flex flex-wrap items-center gap-4">
                <Select v-model="manageFilters.exam_type" :options="manageTypeOptions" optionLabel="label" optionValue="value" :placeholder="$t('teacher_exams.type')" class="w-full md:w-56 premium-select" @change="loadExams" showClear />
                <Select v-model="manageFilters.semester" :options="manageSemesterOptions" optionLabel="label" optionValue="value" :placeholder="$t('teacher_exams.trimester')" class="w-full md:w-56 premium-select" @change="loadExams" showClear />
                <Button icon="pi pi-refresh" rounded text raised class="bg-white dark:bg-surface-900 w-12 h-12" @click="loadExams" />
              </div>
            </div>

            <DataTable :value="exams" :loading="loadingExams" responsiveLayout="scroll" :paginator="true" :rows="10" 
                       class="premium-table"
                       paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                       currentPageReportTemplate="Showing {first} to {last} of {totalRecords} exams">
              <template #empty>
                <div class="text-center py-24 text-surface-400">
                  <i class="pi pi-search text-6xl opacity-10 mb-6 block"></i>
                  <p class="text-xl font-black tracking-tight">{{ $t('teacher_exams.no_exams_found') }}</p>
                </div>
              </template>
              <Column field="exam_type" :header="$t('teacher_exams.type')" class="font-black uppercase text-xs tracking-widest">
                <template #body="slotProps">
                  <span class="text-surface-900 dark:text-surface-0">{{ $t(`teacher_class_detail.${slotProps.data.exam_type}`) }}</span>
                </template>
              </Column>
              <Column field="subject.name" :header="$t('teacher_exams.subject')" class="font-bold"></Column>
              <Column field="semester" :header="$t('teacher_exams.trimester')">
                <template #body="slotProps">
                   <span class="px-3 py-1 rounded-lg bg-surface-100 dark:bg-surface-700 text-surface-600 dark:text-surface-300 text-[10px] font-black uppercase tracking-widest">
                     {{ $t(`grade_analytics.trimester_${slotProps.data.semester.slice(-1)}`) }}
                   </span>
                </template>
              </Column>
              <Column field="max_grade" :header="$t('teacher_exams.max_grade')">
                <template #body="slotProps">
                  <span class="text-xl font-black text-primary-600 dark:text-primary-400">{{ slotProps.data.max_grade }}</span>
                </template>
              </Column>
              <Column :header="$t('common.actions')" class="text-center">
                <template #body="slotProps">
                  <div class="flex items-center justify-center gap-2">
                    <Button icon="pi pi-pencil" rounded text raised severity="success" class="bg-white dark:bg-surface-900" @click="openEditDialog(slotProps.data)" />
                    <Button icon="pi pi-trash" rounded text raised severity="danger" class="bg-white dark:bg-surface-900" @click="confirmDelete(slotProps.data)" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Edit Exam Dialog -->
    <Dialog v-model:visible="editDialogVisible" :header="$t('teacher_exams.edit_exam')" modal class="w-full max-w-4xl premium-dialog" :breakpoints="{'960px': '75vw', '641px': '100vw'}">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5 py-2">
        <div>
          <label class="block font-semibold text-surface-700 dark:text-surface-300 mb-2">{{ $t('teacher_exams.type') }} <span class="text-red-500">*</span></label>
          <Select v-model="editExamForm.exam_type" :options="examTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div>
          <label class="block font-semibold text-surface-700 dark:text-surface-300 mb-2">{{ $t('teacher_exams.trimester') }} <span class="text-red-500">*</span></label>
          <Select v-model="editExamForm.semester" :options="semesterOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
      </div>

      <div class="mt-6">
        <div class="flex justify-between items-center mb-4">
          <h4 class="font-bold text-surface-800 dark:text-surface-100 m-0">{{ $t('teacher_exams.step_exercises') }}</h4>
          <Button :label="$t('teacher_exams.add_exercise')" icon="pi pi-plus" size="small" outlined @click="addEditExerciseRow" />
        </div>
        <div class="flex flex-col gap-3 max-h-96 overflow-y-auto pr-2">
          <div v-for="(ex, idx) in editExamForm.exercises" :key="idx" 
               class="flex flex-col sm:flex-row gap-4 items-center bg-surface-50 dark:bg-surface-900/50 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
            <div class="flex-1 w-full">
              <InputText v-model="ex.level_name" :placeholder="$t('teacher_exams.exercise_name_placeholder')" class="w-full" />
            </div>
            <div class="w-full sm:w-40">
              <InputNumber v-model="ex.max_note" :min="1" :max="100" class="w-full" inputClass="text-center font-bold" />
            </div>
            <Button icon="pi pi-trash" severity="danger" text rounded @click="removeEditExerciseRow(idx)" :disabled="editExamForm.exercises.length <= 1" />
          </div>
        </div>
      </div>

      <div class="mt-6 p-4 rounded-xl bg-surface-100 dark:bg-surface-900 flex justify-between items-center">
        <span class="font-bold text-surface-700 dark:text-surface-200">{{ $t('teacher_exams.overall_grade') }}</span>
        <span class="text-2xl font-bold text-primary-600">{{ editExamMaxGrade }}</span>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="editDialogVisible = false" />
        <Button :label="$t('common.save')" icon="pi pi-check" :loading="updating" @click="updateExam" />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import ApiService from '@/service/ApiService';
import GradeService from '@/service/GradeService';

const { t } = useI18n();
const toast = useToast();
const confirm = useConfirm();

const loading = ref(false);
const saving = ref(false);
const teacherId = ref<number | null>(null);

// ─── Data ──────────────────────────────────────────────────────────────────────
const myClasses = ref<any[]>([]);
const exams = ref<any[]>([]);
const loadingExams = ref(false);

const manageFilters = ref({
  exam_type: null,
  semester: null
});

// ─── Form State ────────────────────────────────────────────────────────────────
const selectedLevel = ref<string | null>(null);
const selectedClassIds = ref<number[]>([]);
const examSubject = ref<number | null>(null);
const examType = ref<string | null>(null);
const examSemester = ref<string | null>(null);
const examExercises = ref<Array<{ level_name: string; max_note: number }>>([
  { level_name: `${t('teacher_exams.exercise_prefix')} 1`, max_note: 5 }
]);

const examTypeOptions = computed(() => [
  { label: t('grade_analytics.eval_continue'), value: 'evaluation_continue' },
  { label: t('grade_analytics.devoir_label'), value: 'devoir' },
  { label: t('grade_analytics.composition_label'), value: 'composition' }
]);

const semesterOptions = computed(() => [
  { label: t('grade_analytics.trimester_1'), value: 'Trimester 1' },
  { label: t('grade_analytics.trimester_2'), value: 'Trimester 2' },
  { label: t('grade_analytics.trimester_3'), value: 'Trimester 3' },
]);

const manageTypeOptions = computed(() => [
  { label: t('grade_analytics.all_exam_types'), value: null },
  ...examTypeOptions.value.filter(o => o.value !== null)
]);

const manageSemesterOptions = computed(() => [
  { label: t('grade_analytics.all_trimesters'), value: null },
  ...semesterOptions.value
]);

// ─── Edit State ──────────────────────────────────────────────────────────────
const editDialogVisible = ref(false);
const updating = ref(false);
const editExamForm = ref<any>({
  id: null,
  exam_type: null,
  semester: null,
  exercises: []
});

// ─── Computed ──────────────────────────────────────────────────────────────────
const currentAcademicYear = computed(() => {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth() + 1;
  return month >= 9 ? `${year}-${year + 1}` : `${year - 1}-${year}`;
});

const availableLevels = computed(() => {
  const levels = new Set<string>();
  myClasses.value.forEach(cls => {
    if (cls.level) levels.add(cls.level);
  });
  return Array.from(levels).map(l => ({ label: l, value: l }));
});

const filteredClasses = computed(() => {
  if (!selectedLevel.value) return [];
  return myClasses.value.filter(cls => cls.level === selectedLevel.value);
});

const availableSubjects = computed(() => {
  if (!selectedClassIds.value.length) return [];
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

const editExamMaxGrade = computed(() => {
  return editExamForm.value.exercises.reduce((acc: number, ex: any) => acc + (ex.max_note || 0), 0);
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
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_exams.load_classes_error'), life: 4000 });
  } finally {
    loading.value = false;
  }
};

const loadExams = async () => {
  if (!teacherId.value) return;
  loadingExams.value = true;
  try {
    const results = await GradeService.getExams({ 
      teacher_id: teacherId.value,
      exam_type: manageFilters.value.exam_type || undefined,
      semester: manageFilters.value.semester || undefined
    });
    exams.value = results;
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_class_detail.fetch_exams_error'), life: 4000 });
  } finally {
    loadingExams.value = false;
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
  examExercises.value.push({ level_name: `${t('teacher_exams.exercise_prefix')} ${examExercises.value.length + 1}`, max_note: 5 });
};

const removeExerciseRow = (index: any) => {
  examExercises.value.splice(Number(index), 1);
};

const saveExam = async () => {
  if (!teacherId.value) {
    toast.add({ severity: 'error', summary: t('teacher_exams.access_error'), detail: t('teacher_exams.no_profile'), life: 4000 });
    return;
  }
  if (!selectedLevel.value) {
    toast.add({ severity: 'warn', summary: t('common.validation'), detail: t('teacher_exams.val_level'), life: 3000 });
    return;
  }
  if (!selectedClassIds.value.length) {
    toast.add({ severity: 'warn', summary: t('common.validation'), detail: t('teacher_exams.val_classes'), life: 3000 });
    return;
  }
  if (!examSubject.value || !examType.value || !examSemester.value) {
    toast.add({ severity: 'warn', summary: t('common.validation'), detail: t('teacher_exams.val_details'), life: 3000 });
    return;
  }
  if (!examExercises.value.length) {
    toast.add({ severity: 'warn', summary: t('common.validation'), detail: t('teacher_exams.val_exercise'), life: 3000 });
    return;
  }
  
  saving.value = true;
  try {
    await GradeService.createExam({
      subject_id: examSubject.value,
      teacher_id: teacherId.value,
      exam_type: examType.value,
      semester: examSemester.value,
      academic_year: currentAcademicYear.value,
      max_grade: examMaxGrade.value,
      class_ids: selectedClassIds.value,
      exercises: examExercises.value,
    });
    
    toast.add({ severity: 'success', summary: t('teacher_exams.saved'), detail: t('teacher_exams.success'), life: 3000 });
    
    // Reset form
    selectedLevel.value = null;
    selectedClassIds.value = [];
    examSubject.value = null;
    examType.value = null;
    examSemester.value = null;
    examExercises.value = [{ level_name: `${t('teacher_exams.exercise_prefix')} 1`, max_note: 5 }];
    
  } catch (err: any) {
    let errorDetail = t('teacher_exams.save_error');
    if (err?.response?.data?.errors) {
      const errs = err.response.data.errors;
      const firstKey = Object.keys(errs)[0];
      errorDetail = errs[firstKey][0];
    } else if (err?.response?.data?.message) {
      errorDetail = err?.response?.data?.message;
    }
    toast.add({ severity: 'error', summary: t('common.validation_error'), detail: errorDetail, life: 5000 });
  } finally {
    saving.value = false;
  }
};

// ─── Manage Methods ────────────────────────────────────────────────────────────
const openEditDialog = (exam: any) => {
  editExamForm.value = {
    id: exam.id,
    exam_type: exam.exam_type,
    semester: exam.semester,
    exercises: exam.exercises ? JSON.parse(JSON.stringify(exam.exercises)) : []
  };
  editDialogVisible.value = true;
};

const addEditExerciseRow = () => {
  editExamForm.value.exercises.push({ level_name: `${t('teacher_exams.exercise_prefix')} ${editExamForm.value.exercises.length + 1}`, max_note: 5 });
};

const removeEditExerciseRow = (idx: any) => {
  editExamForm.value.exercises.splice(Number(idx), 1);
};

const updateExam = async () => {
  updating.value = true;
  try {
    await GradeService.updateExam(editExamForm.value.id, {
      exam_type: editExamForm.value.exam_type,
      semester: editExamForm.value.semester,
      exercises: editExamForm.value.exercises,
      max_grade: editExamMaxGrade.value
    });
    
    toast.add({ severity: 'success', summary: t('common.success'), detail: t('teacher_exams.update_success'), life: 3000 });
    editDialogVisible.value = false;
    await loadExams();
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_exams.save_error'), life: 3000 });
  } finally {
    updating.value = false;
  }
};

const confirmDelete = (exam: any) => {
  confirm.require({
    message: t('teacher_exams.delete_confirm_msg'),
    header: t('teacher_exams.delete_confirm_title'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await GradeService.deleteExam(exam.id);
        toast.add({ severity: 'success', summary: t('common.success'), detail: t('teacher_exams.delete_success'), life: 3000 });
        await loadExams();
      } catch (err: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('teacher_exams.delete_error'), life: 3000 });
      }
    }
  });
};
</script>

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

:deep(.premium-input), :deep(.premium-select) {
  border-radius: 1rem !important;
  border: 1px solid var(--p-surface-200);
  background: var(--p-surface-0);
  padding: 0.25rem;
  transition: all 0.2s;
}

:deep(.dark .premium-input), :deep(.dark .premium-select) {
  border-color: var(--p-surface-700);
  background: var(--p-surface-800);
}

:deep(.premium-input:focus), :deep(.premium-select:focus) {
  border-color: var(--p-primary-500);
  box-shadow: 0 0 0 2px rgba(var(--p-primary-500-rgb), 0.1);
}

.premium-shadow {
  box-shadow: 0 4px 20px -2px rgba(0,0,0,0.05);
}
.dark .premium-shadow {
  box-shadow: 0 4px 20px -2px rgba(0,0,0,0.2);
}

.transition-premium {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

:deep(.p-checkbox.pointer-events-none) {
  pointer-events: none;
}
</style>